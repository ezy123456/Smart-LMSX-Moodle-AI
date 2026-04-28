<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once(__DIR__ . '/classes/gift_importer.php');

global $DB, $CFG, $USER, $PAGE;

$quizid = required_param('quizid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

require_login();
if (!confirm_sesskey($sesskey)) {
    throw new moodle_exception('invalidsesskey');
}

$quiz_record = $DB->get_record('ai_generated_quiz', ['id' => $quizid, 'created_by' => $USER->id], '*', MUST_EXIST);
$courseid = $quiz_record->course_id;
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = context_course::instance($courseid);

require_capability('mod/quiz:manage', $coursecontext);

$PAGE->set_url('/local/ai_assistant/import_quiz_action.php', ['quizid' => $quizid]);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($coursecontext);
$PAGE->set_title('Mengimpor Soal ke Bank Soal Kursus');
$PAGE->set_heading('Import Soal AI');

$success = false;
$error_message = '';
$category = null;
$import_count = 0;

function local_ai_count_questions($category_id) {
    global $DB;
    if ($DB->get_manager()->table_exists('question_bank_entries')) {
        return $DB->count_records('question_bank_entries', ['questioncategoryid' => $category_id]);
    } else {
        return $DB->count_records('question', ['category' => $category_id]);
    }
}

try {
    $section = $DB->get_record('course_sections', ['id' => $quiz_record->section_id]);
    $section_name = $section && $section->name ? $section->name : "Topik " . ($section->section ?? 'Umum');
    $cat_name = "AI Generated - " . $section_name;
    
    $category = $DB->get_record('question_categories', [
        'name' => $cat_name, 
        'contextid' => $coursecontext->id
    ]);
    
    if (!$category) {
        $new_cat = new stdClass();
        $new_cat->name = $cat_name;
        $new_cat->contextid = $coursecontext->id;
        $new_cat->info = 'Soal digenerate otomatis oleh AI Assistant';
        $new_cat->infoformat = FORMAT_HTML;
        $new_cat->stamp = make_unique_id_code();
        $new_cat->parent = 0;
        $new_cat->sortorder = 999;
        $new_cat->id = $DB->insert_record('question_categories', $new_cat);
        $category = $new_cat;
    }

    $temp_dir = make_temp_directory('ai_assistant_import');
    $temp_file = $temp_dir . '/import_' . time() . '.gift.txt';
    
    if (empty($quiz_record->quiz_data)) {
        throw new Exception('Data soal kosong.');
    }

    if (file_put_contents($temp_file, $quiz_record->quiz_data) === false) {
        throw new Exception('Gagal menulis file sementara.');
    }

    $questions_before = local_ai_count_questions($category->id);

    $qformat = new local_ai_assistant_gift_importer();
    $qformat->setcourse($course);
    $qformat->category = $category;
    $qformat->categoryname = $category->name;
    $qformat->filename = $temp_file;
    $qformat->realfilename = 'ai_generated.gift';
    
    ob_start(); 
    $process_result = $qformat->importprocess();
    ob_get_clean();
    
    if (file_exists($temp_file)) unlink($temp_file);

    if ($process_result) {
        $success = true;
        $questions_after = local_ai_count_questions($category->id);
        $import_count = $questions_after - $questions_before;
    } else {
        throw new Exception("Format GIFT ditolak Moodle.");
    }

} catch (Exception $e) {
    @ob_end_clean();
    $error_message = $e->getMessage();
}

echo $PAGE->get_renderer('core')->header();

if ($success) {
    echo "<div class='alert alert-success'>";
    echo "<h3>Berhasil Disimpan ke Bank Soal Kursus!</h3>";
    echo "<p>Soal masuk ke kategori: <strong>" . htmlspecialchars($category->name) . "</strong></p>";
    echo "<p>Jumlah soal baru: <strong>" . $import_count . "</strong></p>";
    
    $returnurl = new moodle_url('/question/edit.php', [
        'courseid' => $courseid,
        'cat' => $category->id . ',' . $category->contextid
    ]);
    
    echo "<a href='" . $returnurl . "' class='btn btn-primary'>Lihat Bank Soal</a>";
    echo "</div>";
    
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3>Gagal</h3>";
    echo "<p>" . htmlspecialchars($error_message) . "</p>";
    echo "</div>";
    
    $backurl = new moodle_url('/local/ai_assistant/generate_quiz_form.php', ['courseid' => $courseid]);
    echo "<a href='" . $backurl . "' class='btn btn-secondary'>Kembali</a>";
}

echo $PAGE->get_renderer('core')->footer();