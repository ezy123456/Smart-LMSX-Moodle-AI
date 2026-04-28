<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/ai_assistant/config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

if (!has_capability('moodle/course:manageactivities', $context)) {
    print_error('nopermissions', 'error', '', 'Akses Generator Materi');
}

$PAGE->set_url('/local/ai_assistant/content_generator_page.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Generator AI Materi');
$PAGE->set_heading('Generator AI Materi');
$PAGE->set_pagelayout('standard');

$PAGE->requires->js(new moodle_url('/local/ai_assistant/js/content_generator.js', ['v' => time()]));

$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$topics_data = [];

foreach ($sections as $sec) {
    $sec_name = get_section_name($course, $sec);
    $raw_summary = $sec->summary;
    $raw_summary = str_ireplace(['<br />', '<br>', '<br/>', '</p>'], "\n", $raw_summary);
    $text = strip_tags($raw_summary); 
    $text = html_entity_decode($text); 
    $text = trim($text);

    $parts = preg_split('/(Sub\s*CPMK|Daftar\s*Materi)/i', $text, 2);
    $description = isset($parts[0]) ? trim(trim($parts[0]), '"\'') : $text;
    
    if (empty($description)) {
        $description = $text; 
    }

    $lines = explode("\n", $text);
    $subtopics = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (substr($line, 0, 1) === '-') {
            $clean_sub = trim(substr($line, 1));
            if (!empty($clean_sub)) {
                $subtopics[] = $clean_sub;
            }
        }
    }

    $topics_data[$sec->id] = [
        'id' => $sec->id,
        'name' => $sec_name,
        'description' => $description,
        'subtopics' => $subtopics
    ];
}

$topics_json = json_encode($topics_data);

echo $OUTPUT->header();
?>

<script>
    window.AI_COURSE_TOPICS = <?php echo $topics_json; ?>;
</script>

<div id="ai-assistant-wrapper">
    <div class="ai-content-generator-wrapper bg-white p-4 rounded shadow-sm border">
        <div class="d-flex align-items-center mb-3">
            <div style="background:#e3f2fd; padding:10px; border-radius:50%; margin-right:15px;">
                <span style="font-size: 24px;">📝</span>
            </div>
            <div>
                <h3 style="color:#0f6cbf; margin:0;">Generator AI Materi</h3>
                <p class="text-muted mb-0">Buat draf materi ajar terstruktur dari Topik Moodle Anda.</p>
            </div>
        </div>
        <hr>

        <div class="form-group">
            <label for="ai-select-moodle-topic" class="font-weight-bold text-uppercase text-secondary small">
                1. Pilih Topik / Pertemuan Moodle
            </label>
            <select id="ai-select-moodle-topic" class="form-control custom-select" style="border: 2px solid #0f6cbf;">
                <option value="">-- Pilih Topik --</option>
                <?php foreach ($topics_data as $topic): ?>
                    <option value="<?php echo $topic['id']; ?>">
                        <?php echo htmlspecialchars($topic['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="font-weight-bold text-uppercase text-secondary small">2. Deskripsi Topik (Otomatis)</label>
            <textarea id="ai-input-description" class="form-control" rows="5" readonly style="background-color: #f8f9fa; min-height: 100px;"></textarea>
        </div>

        <div class="form-group">
            <label for="ai-input-subtopics-select" class="font-weight-bold text-uppercase text-secondary small">
                3. Pilih Sub-Topik
            </label>
            <select id="ai-input-subtopics-select" class="form-control custom-select" style="height: auto; padding: 10px; border: 2px solid #ccc;">
                <option value="" disabled selected>-- Pilih Topik Moodle di atas terlebih dahulu --</option>
            </select>
        </div>

        <div id="ai-subtopic-preview" class="alert alert-success mt-2 mb-4" style="display: none; border-left: 5px solid #155724;">
            <i class="fa fa-check-circle mr-2"></i> 
            <strong>Anda memilih:</strong> 
            <span id="ai-subtopic-text" style="font-weight: normal; display: block; margin-top: 5px; font-style: italic;">...</span>
        </div>

        <div class="form-row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="font-weight-bold text-uppercase text-secondary small">4. Instruksi Tambahan (Opsional)</label>
                    <input type="text" id="ai-input-instructions" class="form-control" placeholder="Cth: Fokus pada studi kasus lokal atau bahasa yang lebih santai...">
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button id="ai-btn-generate-content" class="btn btn-primary btn-lg px-4">
                <i class="fa fa-magic"></i> Buat Materi
            </button>
            <span id="ai-loading-spinner" style="display:none; margin-left:15px; font-weight:bold; color:#0f6cbf;">
                <i class="fa fa-spinner fa-spin"></i> Menulis materi...
            </span>
        </div>

        <div id="ai-content-result" class="mt-4"></div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>