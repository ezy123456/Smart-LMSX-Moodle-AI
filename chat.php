<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/ai_assistant/config.php');

$courseid = required_param('id', PARAM_INT);
$sectionid = optional_param('section', 0, PARAM_INT); 

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

$PAGE->set_url('/local/ai_assistant/chat.php', ['id' => $courseid, 'section' => $sectionid]);
$PAGE->set_context($context); 
$PAGE->set_title('AI Assistant');
$PAGE->set_heading('AI Assistant');
$PAGE->set_pagelayout('standard'); 

$PAGE->requires->css('/local/ai_assistant/css/chat.css');
$PAGE->requires->js('/local/ai_assistant/js/chat.js'); 

echo $OUTPUT->header();

$section_number = 0;
$section_name = '';

if ($sectionid > 0) {
    $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid]);
    if ($section) {
        $section_number = $section->section;
        $section_name = get_section_name($course, $section);
    }
}
?>

<div id="chatbot-container">
    <div class="chatbot-controls">
        
        <?php if ($section_name): ?>
            <div class="chatbot-topic-badge">
                📚 <?php echo htmlspecialchars($section_name); ?>
            </div>
        <?php endif; ?>
        
        <div class="chatbot-scope-selector">
            <select id="chat-scope" class="form-control custom-select">
                <?php 
                if ($section_number == 8) {
                    echo '<option value="midterm" selected>📖 Review Materi UTS (Topik 1-7)</option>';
                } elseif ($section_number == 16) {
                    echo '<option value="final" selected>🎓 Review Materi UAS (Semua)</option>';
                } else {
                    echo '<option value="topic" selected>📄 Fokus Materi Ini Saja</option>';
                }
                ?>
            </select>
        </div>
    </div>
    
    <div id="chat-window">
        <div class="chat-message bot">
            Halo! <?php echo $section_name ? "Saya siap membantu Anda dengan materi <strong>" . htmlspecialchars($section_name) . "</strong>." : "Ada yang bisa saya bantu terkait materi kuliah ini?"; ?>
        </div>
    </div>
    
    <form id="chat-form">
        <input type="text" id="chat-input" placeholder="Ketik pesan Anda di sini..." autocomplete="off">
        <input type="hidden" id="section-id" value="<?php echo $sectionid; ?>">
        <input type="hidden" id="course-id" value="<?php echo $courseid; ?>">
        <input type="hidden" id="sesskey" value="<?php echo sesskey(); ?>">
        
        <div class="chat-actions">
            <?php if (has_capability('moodle/course:manageactivities', $context)): ?>
                <button type="button" id="run-rag" title="Proses ulang materi dengan AI">🔄 Update Materi</button>
            <?php endif; ?>
            <button type="submit" id="chat-submit">Kirim</button>
        </div>
    </form>
</div>

<?php
echo $OUTPUT->footer();