<?php
if (!defined('NO_OUTPUT_BUFFERING')) {
    define('NO_OUTPUT_BUFFERING', true);
}
if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/rag_processor.php');

global $CFG, $DB, $USER, $SESSION, $COURSE, $PAGE;

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_login();

try {
    $courseid = isset($_POST['courseid']) ? intval($_POST['courseid']) : 0;
    $sectionid = isset($_POST['sectionid']) ? intval($_POST['sectionid']) : 0; 
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $sesskey = $_POST['sesskey'] ?? '';
    $scope = isset($_POST['scope']) ? trim($_POST['scope']) : RAGProcessor::SCOPE_TOPIC;

    if (!$courseid || !$message) {
        throw new Exception('Parameter tidak lengkap.');
    }

    if (!confirm_sesskey($sesskey)) {
        throw new Exception('Invalid session key.');
    }
    
    $context = context_course::instance($courseid);
    if (!is_enrolled($context, $USER->id, '', true)) {
         throw new Exception('Access denied.');
    }

    if (!in_array($scope, [RAGProcessor::SCOPE_TOPIC, RAGProcessor::SCOPE_MIDTERM, RAGProcessor::SCOPE_FINAL])) {
        throw new Exception('Invalid scope.');
    }

    $conditions = ['course_id' => $courseid, 'scope' => $scope];
    if ($sectionid > 0) {
        $conditions['section_id'] = $sectionid;
    }
    $existing_content = $DB->get_records('ai_rag_content', $conditions);
    
    if (empty($existing_content)) {
        if ($scope === RAGProcessor::SCOPE_MIDTERM) {
            for ($section_num = 1; $section_num <= 7; $section_num++) {
                $section_record = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section_num]);
                if ($section_record) {
                    RAGProcessor::process_rag($courseid, $section_record->id, null, $scope);
                }
            }
        } elseif ($scope === RAGProcessor::SCOPE_FINAL) {
            for ($section_num = 9; $section_num <= 15; $section_num++) {
                $section_record = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section_num]);
                if ($section_record) {
                    RAGProcessor::process_rag($courseid, $section_record->id, null, $scope);
                }
            }
        } else {
            if ($sectionid > 0) {
                RAGProcessor::process_rag($courseid, $sectionid, $module_id, $scope);
            }
        }
    }

    if ($module_id) {
        $cm = $DB->get_record('course_modules', ['id' => $module_id, 'course' => $courseid]);
        if (!$cm) {
            throw new Exception("Module tidak ditemukan.");
        }
        $sectionid = $cm->section;
    }

    $context_chunks = RAGProcessor::retrieve_context($courseid, $sectionid, $message, $scope, $module_id);
    $context_text = '';
    foreach ($context_chunks as $chunk_data) {
        if (is_array($chunk_data) && isset($chunk_data['chunk'])) {
            $context_text .= $chunk_data['chunk'] . "\n\n";
        }
    }

    if (empty($context_text)) {
        $scope_str = match($scope) {
            RAGProcessor::SCOPE_TOPIC => $module_id ? "modul" : "topik",
            RAGProcessor::SCOPE_MIDTERM => "UTS",
            RAGProcessor::SCOPE_FINAL => "UAS",
            default => "bagian"
        };
        $context_text = "(Tidak ada materi yang ditemukan untuk {$scope_str} ini)";
    }

    $course = $DB->get_record('course', ['id' => $courseid]);
    
    $last_request = $DB->get_field_sql("SELECT MAX(timecreated) FROM {ai_chat_history} WHERE user_id = ? AND timecreated > ?", [$USER->id, time() - 3]);
    if ($last_request) {
        throw new Exception('Mohon tunggu beberapa detik sebelum mengirim pesan baru.');
    }

    $context_name = '';
    if ($module_id) {
        $cm = $DB->get_record('course_modules', ['id' => $module_id]);
        $mod = $DB->get_record('modules', ['id' => $cm->module]);
        $instance = $DB->get_record($mod->name, ['id' => $cm->instance]);
        $context_name = $instance->name ?? 'Modul';
    } else if ($sectionid > 0) {
        $section = $DB->get_record('course_sections', ['id' => $sectionid]);
        $context_name = !empty($section->name) ? $section->name : 'Topic ' . $section->section;
    } else {
        $context_name = match($scope) {
            RAGProcessor::SCOPE_MIDTERM => 'UTS (Pertemuan 1-7)',
            RAGProcessor::SCOPE_FINAL => 'UAS (Pertemuan 9-15)',
            default => 'Semua Topik'
        };
    }

    $system_prompt = "Anda adalah asisten AI untuk mata kuliah '{$course->fullname}', khusus untuk " . ($module_id ? "modul" : ($scope === RAGProcessor::SCOPE_TOPIC ? "pertemuan" : "ujian")) . " '{$context_name}'.\n\n";
    $system_prompt .= "PENTING: Jawablah pertanyaan mahasiswa HANYA berdasarkan materi yang diberikan di bawah ini. Jika materi tidak ada, jawablah Maaf. Saya tidak menemukan materi yang relevan dengan pertanyaan Anda di topik ini.'\n\n";
    $system_prompt .= "=== MATERI PERTEMUAN ===\n{$context_text}\n\n";
    $system_prompt .= "PERTANYAAN MAHASISWA:\n{$message}";

    $api_url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
    $payload = [
        'contents' => [['parts' => [['text' => $system_prompt]]]]
    ];

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        throw new Exception("Gemini API error ($status)");
    }

    $result = json_decode($response, true);
    $reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Tidak ada respons dari AI.';

    $thread_id = optional_param('thread_id', uniqid('thread_', true), PARAM_TEXT);

    $history = new stdClass();
    $history->user_id = $USER->id;
    $history->course_id = $courseid;
    $history->section_id = $sectionid;
    $history->thread_id = $thread_id;
    $history->role = 'user';
    $history->message = $message;
    $history->context_used = json_encode(['scope' => $scope, 'module_id' => $module_id, 'chunks_found' => count($context_chunks)]);
    $history->timecreated = time();
    $DB->insert_record('ai_chat_history', $history);
    
    $reply = trim($reply);
    if (!str_starts_with($reply, "```") && !str_starts_with($reply, ">")) {
        $reply = str_replace("\n", "\n\n", $reply);
    }
    
    $history->role = 'assistant';
    $history->message = $reply;
    $DB->insert_record('ai_chat_history', $history);

    echo json_encode([
        'success' => true,
        'response' => $reply,
        'context' => ['scope' => $scope, 'module_id' => $module_id, 'chunks_found' => count($context_chunks), 'context_name' => $context_name],
        'thread_id' => $thread_id,
        'markdown' => true
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}