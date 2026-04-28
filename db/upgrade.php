<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_ai_assistant_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025111003) {
        $table = new xmldb_table('ai_feedback_history');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('assignment_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('submission_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('feedback_text', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('scores', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('suggestions', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('strengths', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('weaknesses', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('assignment_fk', XMLDB_KEY_FOREIGN, ['assignment_id'], 'assign', ['id']);
            $table->add_key('submission_fk', XMLDB_KEY_FOREIGN, ['submission_id'], 'assign_submission', ['id']);
            $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id']);

            $table->add_index('assignment_id_idx', XMLDB_INDEX_NOTUNIQUE, ['assignment_id']);
            $table->add_index('submission_id_idx', XMLDB_INDEX_NOTUNIQUE, ['submission_id']);
            $table->add_index('user_id_idx', XMLDB_INDEX_NOTUNIQUE, ['user_id']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025111003, 'local', 'ai_assistant');
    }

    if ($oldversion < 2025111002) {
        upgrade_plugin_savepoint(true, 2025111002, 'local', 'ai_assistant');
    }

    if ($oldversion < 2025111001) {
        $table = new xmldb_table('ai_rag_content');

        $field = new xmldb_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('section_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('module_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('scope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'topic');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('module_id_idx', XMLDB_INDEX_NOTUNIQUE, ['module_id']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('course_section_idx', XMLDB_INDEX_NOTUNIQUE, ['course_id', 'section_id']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2025111001, 'local', 'ai_assistant');
    }

    return true;
}