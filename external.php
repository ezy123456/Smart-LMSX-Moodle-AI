<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/rag_processor.php');

class local_ai_assistant_external extends external_api {

    public static function generate_feedback_parameters() {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'Submission ID', VALUE_DEFAULT, 0),
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
            'userid'       => new external_value(PARAM_INT, 'User ID (Mahasiswa)', VALUE_DEFAULT, 0)
        ]);
    }

    public static function generate_feedback($submissionid, $assignmentid, $userid) {
        global $DB, $CFG;

        self::validate_parameters(self::generate_feedback_parameters(), [
            'submissionid' => $submissionid,
            'assignmentid' => $assignmentid,
            'userid'       => $userid
        ]);

        if (empty($submissionid) && !empty($userid)) {
            try {
                $submission = $DB->get_record('assign_submission', [
                    'assignment' => $assignmentid,
                    'userid'     => $userid,
                    'latest'     => 1
                ]);
                if ($submission) {
                    $submissionid = $submission->id;
                }
            } catch (Exception $e) {
                return ['success' => false, 'error' => "Database error: " . $e->getMessage()];
            }
        }

        if (empty($submissionid)) {
            return ['success' => false, 'error' => "Submission tidak ditemukan."];
        }

        try {
            $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submissionid]);
            if (!$onlinetext || empty($onlinetext->onlinetext)) {
                return ['success' => false, 'error' => 'Mahasiswa belum mengumpulkan jawaban teks.'];
            }
            $student_answer = strip_tags($onlinetext->onlinetext);
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Error saat ambil submission: " . $e->getMessage()];
        }

        try {
            $assignment = $DB->get_record('assign', ['id' => $assignmentid], '*', MUST_EXIST);
            $course     = $DB->get_record('course', ['id' => $assignment->course], '*', MUST_EXIST);
            $task_desc  = strip_tags($assignment->intro);
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Error saat ambil assignment: " . $e->getMessage()];
        }

        try {
            $query_rag = $task_desc . " " . $student_answer;
            $context_chunks = RAGProcessor::retrieve_context($course->id, 0, $query_rag, RAGProcessor::SCOPE_COURSE);
            $context_text = "";
            if (!empty($context_chunks)) {
                foreach ($context_chunks as $chunk) {
                    $context_text .= "- " . $chunk['chunk'] . "\n";
                }
            } else {
                $context_text = "TIDAK DITEMUKAN MATERI REFERENSI DI MOODLE.";
            }
        } catch (Exception $e) {
            $context_text = "Gagal mengambil konteks RAG: " . $e->getMessage();
        }

        $api_key = get_config('local_ai_assistant', 'gemini_api_key');

        if (!$api_key) {
            debugging('Gemini API Key is not configured in local_ai_assistant settings.', DEBUG_DEVELOPER);
        }
        $prompt = "Anda adalah Asisten Dosen untuk mata kuliah '{$course->fullname}'.\n";
        $prompt .= "Tugas Anda adalah menilai jawaban mahasiswa BERDASARKAN MATERI REFERENSI YANG DIBERIKAN.\n\n";
        $prompt .= "=== MATERI REFERENSI ===\n" . $context_text . "\n========================\n\n";
        $prompt .= "SOAL: " . $task_desc . "\n\nJAWABAN: " . $student_answer . "\n\n";
        $prompt .= "Output HARUS format JSON: { \"grade\": 0-100, \"feedback\": \"\", \"strengths\": [], \"improvements\": [] }";

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . urlencode($api_key);
            $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.3]]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
            
            $response_raw = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ['success' => false, 'error' => "Gemini API error (HTTP $http_code)"];
            }

            $response_json = json_decode($response_raw, true);
            $ai_text = $response_json['candidates'][0]['content']['parts'][0]['text'];
            $ai_text = preg_replace('/```json\s*|```\s*/i', '', trim($ai_text));
            $feedback_data = json_decode($ai_text, true);

            if (!$feedback_data) {
                return ['success' => false, 'error' => "JSON Parse Error."];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => "Exception: " . $e->getMessage()];
        }

        try {
            $log = new stdClass();
            $log->assignment_id = $assignmentid;
            $log->submission_id = $submissionid;
            $log->user_id = $userid;
            $log->feedback_text = $feedback_data['feedback'];
            $log->timecreated = time();
            $DB->insert_record('ai_feedback_history', $log);
        } catch (Exception $e) {}

        return [
            'success' => true, 
            'feedback' => [
                'grade' => (int)$feedback_data['grade'],
                'feedback' => $feedback_data['feedback'],
                'strengths' => $feedback_data['strengths'] ?? [],
                'improvements' => $feedback_data['improvements'] ?? []
            ]
        ];
    }

    public static function generate_feedback_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'feedback' => new external_single_structure([
                'grade' => new external_value(PARAM_INT, 'Grade (0-100)', VALUE_OPTIONAL),
                'feedback' => new external_value(PARAM_RAW, 'Feedback text', VALUE_OPTIONAL),
                'strengths' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Strength'), 'Strengths', VALUE_OPTIONAL),
                'improvements' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Improvement'), 'Improvements', VALUE_OPTIONAL)
            ], 'Feedback data', VALUE_OPTIONAL)
        ]);
    }
}