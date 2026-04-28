<?php
namespace local_ai_assistant\hook\output;

use core\hook\output\before_footer as before_footer_hook;

defined('MOODLE_INTERNAL') || die();

class before_footer {

    public static function callback(before_footer_hook $hook): void {
        global $PAGE, $DB;

        try {
            $url = $PAGE->url->__toString();
        } catch (\Exception $e) {
            return;
        }

        if (strpos($url, '/mod/assign/') === false) {
            return;
        }

        $assignmentid = optional_param('id', 0, PARAM_INT);
        $submissionid = optional_param('sid', 0, PARAM_INT);
        $userid       = optional_param('userid', 0, PARAM_INT);

        if (!$assignmentid && !empty($PAGE->cm->id)) {
            $assignmentid = $PAGE->cm->id;
            
            $cm = get_coursemodule_from_id('assign', $assignmentid);
            if ($cm) {
                $assignmentid = $cm->instance;
            }
        } else {
             $cm = get_coursemodule_from_id('assign', $assignmentid);
             if ($cm) {
                 $assignmentid = $cm->instance;
             }
        }

        if (empty($submissionid) && !empty($assignmentid) && !empty($userid)) {
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assignmentid,
                'userid' => $userid,
                'latest' => 1 
            ]);

            if ($submission) {
                $submissionid = $submission->id;
            }
        }

        $js_config = "
            <script>
            window.AIS_CONFIG = {
                assignmentId: " . (int)$assignmentid . ",
                submissionId: " . (int)$submissionid . ",
                userId: " . (int)$userid . ",
                wwwroot: '" . \moodle_url::make_pluginfile_url()->get_scheme() . '://' . \moodle_url::make_pluginfile_url()->get_host() . "'
            };
            console.log('[AI PHP] Footer Hook Siap. Submission ID: ' + window.AIS_CONFIG.submissionId);
            </script>
        ";
        $hook->add_html($js_config);

        $js_url = new \moodle_url('/local/ai_assistant/js/assignment_feedback.js');
        $script_tag = '<script src="' . $js_url . '?v=' . time() . '"></script>';
        $hook->add_html($script_tag);
    }
}