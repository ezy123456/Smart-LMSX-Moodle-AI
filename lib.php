<?php
defined('MOODLE_INTERNAL') || die();

function local_ai_assistant_before_footer() {
    global $PAGE, $CFG;

    try { $url = $PAGE->url->__toString(); } catch (Exception $e) { return ''; }
    if (strpos($url, '/mod/assign/') === false) { return ''; }

    $cmid   = optional_param('id', 0, PARAM_INT);
    $userid = optional_param('userid', 0, PARAM_INT); 
    $sid    = optional_param('sid', 0, PARAM_INT);

    $assignmentid = 0;
    if ($cmid) {
        $cm = get_coursemodule_from_id('assign', $cmid);
        if ($cm) $assignmentid = $cm->instance;
    }
    if (!$assignmentid && !empty($PAGE->cm->instance)) {
        $assignmentid = $PAGE->cm->instance;
    }

    $jsinit = "
        window.AIS_CONFIG = {
            assignmentId: " . (int)$assignmentid . ",
            userId: " . (int)$userid . ",
            submissionId: " . (int)$sid . ",
            wwwroot: '" . $CFG->wwwroot . "'
        };
    ";

    $PAGE->requires->js_init_code($jsinit);
    $js_url = new moodle_url('/local/ai_assistant/js/assignment_feedback.js', ['v' => time()]);
    $PAGE->requires->js($js_url);
}

function local_ai_assistant_extend_settings_navigation($settings, $context) {
    global $USER, $PAGE, $DB;

    if ($context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    $can_chat = has_capability('local/ai_assistant:use_chatbot', $context);
    $can_gen  = has_capability('local/ai_assistant:generate_quiz', $context);

    if (!$can_chat && !$can_gen) {
        return;
    }

    $courseadmin = $settings->get('courseadmin');
    $target_node = $courseadmin ? $courseadmin : $settings;

    $ai_node = $target_node->add(
        'AI Assistant', 
        null, 
        navigation_node::TYPE_CUSTOM, 
        null, 
        'ai_assistant_root'
    );
    $ai_node->force_open();

    if ($can_chat) {
        $main_url = new moodle_url('/local/ai_assistant/chat.php', ['id' => $PAGE->course->id, 'section' => 0]);
        $ai_node->add(
            'Chatbot - Tanya Jawab', 
            $main_url, 
            navigation_node::TYPE_SETTING,
            null,
            'ai_chat_main',
            new pix_icon('i/magic', '')
        );

        $sections = $DB->get_records('course_sections', ['course' => $PAGE->course->id], 'section ASC');
        if ($sections) {
            foreach ($sections as $section) {
                if ($section->section == 0) continue;
                $secName = get_section_name($PAGE->course, $section);
                $sec_url = new moodle_url('/local/ai_assistant/chat.php', ['id' => $PAGE->course->id, 'section' => $section->id]);
                $ai_node->add($secName, $sec_url, navigation_node::TYPE_SETTING);
            }
        }
    }

    if ($can_gen) {
        $ai_node->add('────────────────', null, navigation_node::TYPE_CUSTOM);

        $url_gen = new moodle_url('/local/ai_assistant/content_generator_page.php', ['courseid' => $PAGE->course->id]);
        $ai_node->add(
            'Generator AI Materi', 
            $url_gen, 
            navigation_node::TYPE_SETTING,
            null,
            'ai_gen_materi',
            new pix_icon('i/write', '')
        );

        $url_quiz = new moodle_url('/local/ai_assistant/generate_quiz_form.php', ['courseid' => $PAGE->course->id]);
        $ai_node->add(
            'Generator Soal Otomatis', 
            $url_quiz, 
            navigation_node::TYPE_SETTING,
            null,
            'ai_gen_quiz',
            new pix_icon('i/quiz', '')
        );

        $url_bank = new moodle_url('/question/edit.php', ['courseid' => $PAGE->course->id]);
        $ai_node->add('Lihat Bank Soal', $url_bank, navigation_node::TYPE_SETTING);
    }
}

function local_ai_assistant_extend_course_section_navigation($navigation, $course, $section, $sectionreturn = null) {
    global $PAGE;
    $context = context_course::instance($course->id);
    if (!has_capability('local/ai_assistant:process_rag', $context)) return;
    if ($section->section == 0) return;

    $url = new moodle_url('/local/ai_assistant/run_rag.php', ['courseid' => $course->id, 'sectionid' => $section->id, 'sesskey' => sesskey()]);
    $navigation->add('Proses AI (RAG)', $url, navigation_node::TYPE_CUSTOM, null, 'ai_rag_process_' . $section->id, new pix_icon('i/ai', 'AI'));
}