<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/gift/format.php');

class local_ai_assistant_gift_importer extends qformat_gift {
    
    protected $importcontext = null;
    
    public function setcourse($course) {
        parent::setcourse($course);
        
        if (!empty($this->category) && !empty($this->category->contextid)) {
            try {
                $this->importcontext = context::instance_by_id($this->category->contextid);
            } catch (Exception $e) {
            }
        }
    }
    
    public function importprocess() {
        global $CFG;
        
        if (empty($this->importcontext)) {
            if (!empty($this->category) && !empty($this->category->contextid)) {
                try {
                    $this->importcontext = context::instance_by_id($this->category->contextid);
                } catch (Exception $e) {
                    throw new Exception('Tidak dapat membuat context dari category ID: ' . $this->category->contextid . ' | Error: ' . $e->getMessage());
                }
            } else {
                throw new Exception('Import context tidak dapat ditentukan. Pastikan kategori memiliki contextid yang valid.');
            }
        }
        
        try {
            $result = parent::importprocess();
            return $result;
        } catch (Exception $e) {
            $error_msg = 'Error saat import GIFT format: ' . $e->getMessage();
            
            if (defined('PHPUNIT_TEST') && !PHPUNIT_TEST) {
                error_log('[AI Assistant Import Error] ' . $error_msg);
            }
            
            throw new Exception($error_msg);
        }
    }
    
    public function get_import_context() {
        return $this->importcontext;
    }
}