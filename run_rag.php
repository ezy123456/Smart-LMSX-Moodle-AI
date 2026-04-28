<?php
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true); 

require_once('../../config.php');
require_once($CFG->dirroot . '/local/ai_assistant/config.php');
require_once($CFG->dirroot . '/local/ai_assistant/rag_processor.php');

global $DB, $PAGE, $OUTPUT, $CFG;

if (function_exists('set_time_limit')) {
    @set_time_limit(0); 
}

@ini_set('memory_limit', '512M'); 
@ini_set('max_execution_time', 0);

if (function_exists('core_php_time_limit::raise')) {
    core_php_time_limit::raise(); 
}

$module_id = optional_param('module_id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$scope = optional_param('scope', 'topic', PARAM_ALPHA);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

if (!in_array($scope, [RAGProcessor::SCOPE_TOPIC, RAGProcessor::SCOPE_MIDTERM, RAGProcessor::SCOPE_FINAL])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'error' => 'Invalid scope']);
        exit;
    }
    throw new moodle_exception('Invalid scope.');
}

if ($module_id && !$courseid && !$sectionid) {
    $context = RAGProcessor::get_context_from_module($module_id);
    if ($context) {
        $courseid = $context->course_id;
        $sectionid = $context->section_id;
    }
}

$isajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function return_json($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function shutdown_handler() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR)) {
        global $isajax;
        if ($isajax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Server Crash: ' . $error['message']]);
            exit;
        }
    }
}
register_shutdown_function('shutdown_handler');

ob_start();

if (!$courseid || !$sectionid) {
    if ($isajax) {
        return_json(['success' => false, 'error' => 'Missing parameters.']);
    }
    throw new moodle_exception('invalidparameter');
}

require_login();
if (!confirm_sesskey($sesskey)) {
    if ($isajax) {
        return_json(['success' => false, 'error' => 'Invalid Session Key.']);
    }
    die('Invalid sesskey');
}

$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

if (!$isajax) {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/ai_assistant/run_rag.php', ['courseid' => $courseid, 'sectionid' => $sectionid]));
    $PAGE->set_title('Proses RAG AI');
    $PAGE->set_heading('Pemrosesan Materi AI');
    echo $OUTPUT->header();
    
    $scope_str = match($scope) {
        'topic' => 'Topik Ini',
        'midterm' => 'Materi UTS (1-7)',
        'final' => 'Materi UAS (Semua)',
        default => 'Materi'
    };

    echo html_writer::tag('h3', "📚 Memproses RAG: {$scope_str}");
    echo html_writer::tag('div', 'Sedang menghubungi AI untuk membaca materi...', ['class' => 'alert alert-info']);
    flush(); 
}

try {
    $result = RAGProcessor::process_rag($courseid, $sectionid, $module_id, $scope);

    if ($isajax) {
        if (!empty($result['success'])) {
            return_json(['success' => true, 'chunks' => $result['chunks'] ?? 0]);
        } else {
            return_json(['success' => false, 'error' => $result['error'] ?? 'Unknown error.']);
        }
    } else {
        if (!empty($result['success'])) {
            echo $OUTPUT->notification("SUKSES! Materi berhasil diproses ({$result['chunks']} chunks).", 'notifysuccess');
        } else {
            echo $OUTPUT->notification("GAGAL: " . ($result['error'] ?? 'Gagal.'), 'notifyerror');
        }
        echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    }

} catch (Throwable $e) {
    if ($isajax) {
        return_json(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo $OUTPUT->notification("💥 ERROR FATAL: " . $e->getMessage(), 'notifyproblem');
        echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
}

if (!$isajax) {
    echo $OUTPUT->footer();
}