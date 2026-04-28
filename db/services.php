<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ai_assistant_generate_feedback' => [
        'classname'     => 'local_ai_assistant_external',
        'methodname'    => 'generate_feedback',
        'classpath'     => 'local/ai_assistant/external.php',
        'description'   => 'Generate AI feedback for an assignment submission',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'moodle/course:view'
    ]
];

$services = [
    'AI Assistant Service' => [
        'functions' => ['local_ai_assistant_generate_feedback'],
        'requiredcapability' => '',
        'restrictedusers' => 0,
        'enabled' => 1
    ]
];
