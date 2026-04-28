<?php
ob_start();
define('AJAX_SCRIPT', true); 

try {
    require_once(__DIR__ . '/../../config.php');
    if (file_exists(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    error_reporting(E_ALL); 
    ini_set('display_errors', 0);

    require_login();
    global $USER, $DB;

    header('Content-Type: application/json; charset=utf-8');

    $description  = required_param('description', PARAM_TEXT);
    $subtopics    = required_param('subtopics', PARAM_TEXT);
    $level        = optional_param('level', 'Mahasiswa S1', PARAM_TEXT);
    $instructions = optional_param('instructions', '', PARAM_TEXT); 
    $courseid     = optional_param('courseid', 0, PARAM_INT);

    if ($courseid) {
        $context = context_course::instance($courseid);
        if (!has_capability('moodle/course:manageactivities', $context)) {
            throw new Exception('Akses ditolak. Anda tidak memiliki izin dosen.');
        }
    }

    $prompt = build_content_generation_prompt($description, $subtopics, $instructions, $level);
    $response = call_gemini_for_content($prompt);

    if (!$response['success']) {
        throw new Exception($response['error']);
    }

    ob_clean(); 
    echo json_encode([
        'success' => true,
        'topic' => $description,
        'content' => $response['text']
    ]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean(); 
    http_response_code(200); 
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;

function build_content_generation_prompt($description, $subtopics, $instructions, $level) {
    $prompt = "Bertindaklah sebagai Dosen Ahli (Subject Matter Expert).\n";
    $prompt .= "Konteks Mata Kuliah: '{$description}'.\n\n"; 
    $prompt .= "TUGAS UTAMA: Buatlah materi ajar yang mendalam dan fokus HANYA pada sub-topik berikut:\n";
    $prompt .= ">>> JUDUL MATERI: {$subtopics} <<<\n\n";
    $prompt .= "INSTRUKSI KHUSUS:\n";
    $prompt .= "1. JANGAN membahas sub-topik lain di luar judul di atas.\n";
    $prompt .= "2. Fokuskan seluruh isi materi untuk menjelaskan judul tersebut secara detail.\n";
    if (!empty($instructions)) {
        $prompt .= "3. INSTRUKSI TAMBAHAN DARI DOSEN: '{$instructions}'\n";
    }
    $prompt .= "\nTarget Audiens: {$level}. Sesuaikan gaya bahasa dan kedalaman materi.\n";
    $prompt .= "Format Output: Markdown (Gunakan Header H3 (###) untuk sub-bagian, Bold, dan Bullet points).\n";
    $prompt .= "4. WAJIB SERTAKAN 'Referensi Bacaan Lanjutan' di akhir materi.\n";
    $prompt .= "   - Berikan 3-5 referensi (Buku Teks, Jurnal Ilmiah, atau Website Kredibel).\n";
    return $prompt;
}

function call_gemini_for_content($prompt) {
    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : get_config('local_ai_assistant', 'gemini_api_key');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $api_key;
    
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 8192,
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $curl_error];
    }
    if ($http_code !== 200) {
        return ['success' => false, 'error' => 'Gemini API Error Code: ' . $http_code];
    }
    
    $result = json_decode($response, true);
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => false, 'error' => 'Format respons AI tidak valid.'];
    }
    return ['success' => true, 'text' => $result['candidates'][0]['content']['parts'][0]['text']];
}