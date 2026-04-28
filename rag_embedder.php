<?php
require_once('../../config.php');

class RAGEmbedder {
    
public static function generate_embedding($text, $is_query = false) {
    
        $apikey = get_config('local_ai_assistant', 'gemini_api_key');
        
        if (empty($apikey) && defined('GEMINI_API_KEY')) {
            $apikey = GEMINI_API_KEY;
        }
        
        if (empty($apikey)) {
            throw new \moodle_exception('error_missing_apikey', 'local_ai_assistant');
        }
    
    
        if (empty($apikey)) {
            throw new Exception("Gemini API Key belum diset.");
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=' . $apikey;
        $taskType = $is_query ? 'RETRIEVAL_QUERY' : 'RETRIEVAL_DOCUMENT';

        $payload = json_encode([
            'model' => 'models/text-embedding-004',
            'content' => [
                'parts' => [['text' => $text]]
            ],
            'taskType' => $taskType
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (strpos($response, '<html') !== false) {
                throw new Exception("Google API Error (HTML Response).");
            }
            throw new Exception("Gagal decode JSON: " . json_last_error_msg());
        }

        if (isset($result['error'])) {
            $msg = $result['error']['message'] ?? 'Unknown error';
            throw new Exception("Gemini API Error ($status): $msg");
        }

        if (isset($result['embedding']['values'])) {
            return $result['embedding']['values'];
        }

        throw new Exception("Response tidak memiliki embedding values.");
    }
}