<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT, $CFG;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('mod/quiz:manage', $context);

$PAGE->set_url('/local/ai_assistant/generate_quiz_form.php', ['courseid' => $courseid]);
$PAGE->set_title('Generate Soal Otomatis');
$PAGE->set_heading("Generate Soal - " . $course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

$PAGE->requires->js(new moodle_url('/local/ai_assistant/js/quiz_generator.js'));

echo $OUTPUT->header();

$sections = $DB->get_records('course_sections', 
    ['course' => $courseid], 
    'section ASC', 
    'id, section, name'
);
?>

<div class="ai-quiz-generator-container" style="background: white; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0;">
    <h3 style="color: #0f6cbf; margin-top: 0;">Generate Soal Otomatis</h3>
    <p class="text-muted mb-4">Pilih topik materi yang sudah diproses RAG untuk dibuatkan soal otomatis oleh AI.</p>

    <form id="generate-form" 
          data-wwwroot="<?php echo htmlspecialchars($CFG->wwwroot); ?>"
          data-courseid="<?php echo $courseid; ?>"
          data-sesskey="<?php echo sesskey(); ?>"
    >
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">

        <div class="form-group row">
            <label for="sectionid" class="col-sm-3 col-form-label font-weight-bold">1. Pilih Topik/Materi</label>
            <div class="col-sm-9">
                <select id="sectionid" name="sectionid" class="form-control" required>
                    <option value="">-- Pilih Topik --</option>
                    <?php
                    if ($sections) {
                        foreach ($sections as $section) {
                            if ($section->section == 0) continue; 
                            $name = !empty($section->name) ? $section->name : "Topik " . $section->section;
                            echo '<option value="' . $section->section . '">' . htmlspecialchars($name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="question-count" class="col-sm-3 col-form-label font-weight-bold">2. Jumlah Soal</label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="question-count" name="count" min="1" max="20" value="5" required>
            </div>
        </div>

        <div class="form-group row">
            <label for="difficulty" class="col-sm-3 col-form-label font-weight-bold">3. Tingkat Kesulitan</label>
            <div class="col-sm-9">
                <select id="difficulty" name="difficulty" class="form-control" required>
                    <option value="easy">Mudah (C1/C2)</option>
                    <option value="medium" selected>Sedang (C3/C4)</option>
                    <option value="hard">Sulit (C5/C6)</option>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="option_count" class="col-sm-3 col-form-label font-weight-bold">4. Jumlah Pilihan (A-E)</label>
            <div class="col-sm-9">
                <select id="option_count" name="option_count" class="form-control" required>
                    <option value="3">3 Pilihan</option>
                    <option value="4" selected>4 Pilihan</option>
                    <option value="5">5 Pilihan</option>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="qtype" class="col-sm-3 col-form-label font-weight-bold">5. Tipe Jawaban</label>
            <div class="col-sm-9">
                <select id="qtype" name="qtype" class="form-control" required>
                    <option value="single" selected>Satu Jawaban Benar</option>
                    <option value="multiple">Jawaban Majemuk (Banyak Benar)</option>
                </select>
            </div>
        </div>
        
        <div class="form-group row mt-4">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" id="generate-btn" class="btn btn-primary btn-lg shadow-sm">
                    <i class="fa fa-magic"></i> Generate Soal
                </button>
            </div>
        </div>
    </form>

    <div id="loading-area" style="display:none; text-align:center; margin-top:30px; padding: 20px;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <h5 class="mt-3 text-primary">AI Sedang Bekerja...</h5>
    </div>

    <div id="preview-area" style="display:none; margin-top: 30px; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f8f9fa;">
        <h3 class="text-primary mb-3">👁️ Preview Soal (Draft)</h3>
        <div id="preview-content" style="max-height: 400px; overflow-y: auto; background: white; padding: 15px; border: 1px solid #ced4da; margin-bottom: 20px; white-space: pre-wrap; font-family: monospace; font-size: 14px; color: #333;"></div>
        
        <div class="d-flex justify-content-between">
            <button type="button" id="btn-regenerate" class="btn btn-warning">
                <i class="fa fa-refresh"></i> Generate Ulang
            </button>
            
            <form id="form-import-final" action="<?php echo $CFG->wwwroot; ?>/local/ai_assistant/import_quiz_action.php" method="GET">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="quizid" id="input-quiz-id" value="">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-save"></i> Simpan ke Bank Soal
                </button>
            </form>
        </div>
    </div>

    <div id="result-area" style="margin-top: 20px;"></div>
</div>

<?php
echo $OUTPUT->footer();
?>