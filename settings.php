<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN) {
    $settings = new admin_settingpage('local_ai_assistant_settings', 'AI Assistant Settings');
    $ADMIN->add('localplugins', $settings);
    
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ai_assistant_test',
        'AI Assistant Tests',
        new moodle_url('/local/ai_assistant/test_rag_new.php')
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_assistant/gemini_api_key',
        'Gemini API Key',
        'Masukkan API Key Anda dari Google AI Studio.',
        '',
        PARAM_TEXT
    ));
}