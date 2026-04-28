<?php
namespace local_ai_assistant\hook;

use core\hook\navigation\before_course_navigation_is_built;
use moodle_url;
use navigation_node;

class course_navigation {
    
    public static function before_course_navigation_is_built(before_course_navigation_is_built $hook): void {
        global $USER, $DB;
        
        $nav = $hook->navigation;
        $course = $hook->course;
        $context = $hook->context;
        
        if (!has_capability('moodle/course:view', $context)) {
            return;
        }
        
        $cap_chatbot = has_capability('local/ai_assistant:use_chatbot', $context);
        $cap_generate_quiz = has_capability('local/ai_assistant:generate_quiz', $context);
        $cap_process_rag = has_capability('local/ai_assistant:process_rag', $context);
        
        $has_any_cap = $cap_chatbot || $cap_generate_quiz || $cap_process_rag;
        if (!$has_any_cap) {
            return;
        }
        
        try {
            $main_url = new moodle_url('/local/ai_assistant/chat.php', ['id' => $course->id, 'section' => 0]);
            $mainnode = $nav->add('AI Assistant', $main_url, navigation_node::TYPE_CONTAINER, 'ai_assistant_main', 'ai_assistant_main', new \pix_icon('i/magic', 'AI Assistant'));
        } catch (\Exception $e) {
            return;
        }
        
        if ($cap_chatbot) {
            try {
                $all_topics_url = new moodle_url('/local/ai_assistant/chat.php', ['id' => $course->id, 'section' => 0]);
                $mainnode->add('Chatbot - Tanya Jawab', $all_topics_url, navigation_node::TYPE_SETTING);
                
                $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
                if ($sections) {
                    foreach ($sections as $section) {
                        if ($section->section == 0) continue;
                        $section_url = new moodle_url('/local/ai_assistant/chat.php', ['id' => $course->id, 'section' => $section->id]);
                        $mainnode->add(get_section_name($course, $section), $section_url, navigation_node::TYPE_SETTING);
                    }
                }
            } catch (\Exception $e) {
            }
        }
        
        if ($cap_generate_quiz) {
            try {
                $mainnode->add('───────────────', null, navigation_node::TYPE_SETTING);
                
                $coauthor_url = new moodle_url('/local/ai_assistant/content_generator_page.php', ['courseid' => $course->id]);
                $mainnode->add('Generator AI Materi', $coauthor_url, navigation_node::TYPE_SETTING, null, 'ai_content_gen', new \pix_icon('i/write', 'Write'));
                
                $generator_url = new moodle_url('/local/ai_assistant/generate_quiz_form.php', ['courseid' => $course->id]);
                $mainnode->add('Generator Soal Otomatis', $generator_url, navigation_node::TYPE_SETTING, null, 'ai_quiz_generator', new \pix_icon('i/quiz', 'Generate Quiz'));
                
                $qbank_url = new moodle_url('/question/edit.php', ['courseid' => $course->id]);
                $mainnode->add('Lihat Bank Soal', $qbank_url, navigation_node::TYPE_SETTING);
            } catch (\Exception $e) {
            }
        }
    }
}