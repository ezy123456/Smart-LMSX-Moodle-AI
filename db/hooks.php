<?php
defined('MOODLE_INTERNAL') || die();

$hooks = [
    [
        'hook' => \core\hook\output\before_footer::class,
        'callback' => 'local_ai_assistant\hook\output\before_footer::callback',
    ],
];