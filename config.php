<?php
defined('MOODLE_INTERNAL') || die();

global $CFG, $DB;

$gemini_api_key = get_config('local_ai_assistant', 'gemini_api_key');

if (empty($gemini_api_key)) {
    throw new \moodle_exception('error_api_key_missing', 'local_ai_assistant', '', 'API Key belum dikonfigurasi di halaman admin Moodle.');
}

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', $gemini_api_key);
}
if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent');
}

if (!defined('RAG_CHUNK_SIZE')) define('RAG_CHUNK_SIZE', 1000);
if (!defined('RAG_OVERLAP_SIZE')) define('RAG_OVERLAP_SIZE', 200);
if (!defined('RAG_TOP_K')) define('RAG_TOP_K', 5);

if (!defined('CHATBOT_MAX_HISTORY')) define('CHATBOT_MAX_HISTORY', 10);
if (!defined('CHATBOT_TEMPERATURE')) define('CHATBOT_TEMPERATURE', 0.7);

if (!defined('TABLE_RAG_CONTENT')) define('TABLE_RAG_CONTENT', 'ai_rag_content');
if (!defined('TABLE_RAG_EMBEDDINGS')) define('TABLE_RAG_EMBEDDINGS', 'ai_rag_embeddings');
if (!defined('TABLE_CHAT_HISTORY')) define('TABLE_CHAT_HISTORY', 'ai_chat_history');
if (!defined('TABLE_GENERATED_QUIZ')) define('TABLE_GENERATED_QUIZ', 'ai_generated_quiz');
if (!defined('TABLE_FEEDBACK_HISTORY')) define('TABLE_FEEDBACK_HISTORY', 'ai_feedback_history');

function get_gemini_headers() {
    return [
        'Content-Type: application/json',
        'x-goog-api-key: ' . GEMINI_API_KEY
    ];
}

function log_ai_error($message, $context = []) {
    global $CFG;
    if (!empty($CFG->debug) && $CFG->debug >= DEBUG_NORMAL) {
        error_log('[AI Assistant] ' . $message . ' | Context: ' . json_encode($context));
    }
}

function can_access_section($userid, $courseid, $sectionid) {
    global $DB;
    $context = context_course::instance($courseid);
    return is_enrolled($context, $userid, '', true);
}