<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/config.php');

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');
require_login();

global $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$section_number = required_param('sectionid', PARAM_INT);
$question_count = optional_param('count', 5, PARAM_INT);
$difficulty = optional_param('difficulty', 'medium', PARAM_TEXT);
$option_count = optional_param('option_count', 4, PARAM_INT); 
$qtype = optional_param('qtype', 'single', PARAM_TEXT);

$context = context_course::instance($courseid);
if (!has_capability('mod/quiz:manage', $context)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

try {
    $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section_number], '*', MUST_EXIST);
    $sectionid = $section->id; 

    $params = ['course_id' => $courseid, 'section_id' => $sectionid, 'scope' => 'topic'];
    $ragcontents = $DB->get_records(TABLE_RAG_CONTENT, $params, 'id DESC');

    if (empty($ragcontents)) {
        throw new Exception('Materi belum diproses (RAG). Silakan klik "Update Materi" di Chatbot terlebih dahulu.');
    }
    
    $ragcontent = reset($ragcontents);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    
    $prompt = build_quiz_prompt($course, $section, $ragcontent->original_content, $question_count, $difficulty, $option_count, $qtype);
    $response = call_gemini_for_quiz($prompt);
    
    if (!$response['success']) {
        throw new Exception($response['error']);
    }
    
    $gift_text = $response['response'];
    $gift_text = preg_replace('/```(json|gift|text)?/i', '', $gift_text);
    $gift_text = str_replace('```', '', $gift_text);
    $gift_text = str_replace(["\r\n", "\r"], "\n", $gift_text);
    $gift_text = preg_replace('/([^\n])(::)/', "$1\n\n$2", $gift_text);
    $gift_text = trim($gift_text);

    $real_count = preg_match_all('/::.*?::/', $gift_text);
    if ($real_count == 0) {
        $real_count = substr_count($gift_text, '{');
    }
    
    $question_count = $real_count;

    if ($real_count == 0) {
        throw new Exception('AI gagal membuat format GIFT yang valid.');
    }
    
    $record = new stdClass();
    $record->course_id = $courseid;
    $record->section_id = $sectionid; 
    $record->created_by = $USER->id;
    $record->quiz_data = $gift_text; 
    $record->question_count = $question_count; 
    $record->difficulty = $difficulty;
    $record->timecreated = time();
    
    $quizid = $DB->insert_record(TABLE_GENERATED_QUIZ, $record);
    
    echo json_encode([
        'success' => true,
        'quiz_id' => $quizid,
        'message' => "{$question_count} soal berhasil dibuat.",
        'gift_text_preview' => $gift_text
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function build_quiz_prompt($course, $section, $content, $count, $difficulty, $option_count, $qtype) {
    $sectionname = !empty($section->name) ? $section->name : "Topik " . $section->section;
    $bloom = match($difficulty) {
        'easy' => "TINGKAT KESULITAN: MUDAH (C1-C2).",
        'medium' => "TINGKAT KESULITAN: SEDANG (C3-C4).",
        'hard' => "TINGKAT KESULITAN: SULIT (C5-C6).",
        default => "TINGKAT KESULITAN: SEDANG."
    };

    $type_instruction = "";
    $gift_example = "";

    if ($qtype === 'multiple') {
        $type_instruction = "TIPE SOAL: MULTIPLE RESPONSE.\n- TEPAT 2 JAWABAN BENAR.\n- Gunakan persentase (~%50%Jawaban) dan penalti (~%-100%Jawaban).\n";
        $gift_example = "::Q1:: Manakah (2) perangkat Output? {\n~%50%Monitor\n~%50%Printer\n~%-100%Mouse\n~%-100%Keyboard\n}";
    } else {
        $type_instruction = "TIPE SOAL: SINGLE CHOICE.\n- Hanya 1 jawaban benar (=Jawaban).\n";
        $gift_example = "::Q1:: Apa itu CPU? {\n=Central Processing Unit\n~Central Power Unit\n~Computer Power Unit\n}";
    }

    $prompt = "Anda adalah pembuat soal Moodle Profesional.\n";
    $prompt .= "Mata Kuliah: {$course->fullname}\nTopik: {$sectionname}\n";
    $prompt .= "Materi: \n" . substr($content, 0, 15000) . "\n\n";
    $prompt .= "TUGAS: Buatkan {$count} soal GIFT.\n{$bloom}\n{$type_instruction}\n";
    $prompt .= "JUMLAH OPSI: Setiap soal HARUS {$option_count} pilihan.\n\n";
    $prompt .= "ATURAN: JANGAN gunakan Markdown. Pisahkan soal dengan DUA BARIS KOSONG.\nContoh:\n{$gift_example}\n";

    return $prompt;
}

function call_gemini_for_quiz($prompt) {
    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : get_config('local_ai_assistant', 'gemini_api_key');

    if (!$api_key) {
        $api_key = ''; 
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 8192]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) return ['success' => false, 'error' => 'cURL: ' . $curl_error];
    if ($http_code !== 200) return ['success' => false, 'error' => 'API Error: ' . $http_code];
    
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    return empty($text) ? ['success' => false, 'error' => 'AI respons kosong.'] : ['success' => true, 'response' => $text];
}