<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ai_assistant/config.php');
require_once($CFG->dirroot . '/course/lib.php');

class RAGProcessor {
    
    const SCOPE_TOPIC = 'topic';
    const SCOPE_MIDTERM = 'midterm';
    const SCOPE_FINAL = 'final';
    const SCOPE_COURSE = 'course';

    public static function get_context_from_module($module_id) {
        global $DB;
        try {
            $sql = "SELECT cm.id as module_id,
                           cm.course as course_id,
                           cm.section as section_id,
                           c.fullname as course_name,
                           cs.name as section_name,
                           cs.section as week_number
                    FROM {course_modules} cm
                    JOIN {course} c ON c.id = cm.course
                    JOIN {course_sections} cs ON cs.id = cm.section
                    WHERE cm.id = ?";
            
            $result = $DB->get_record_sql($sql, [$module_id]);
            return $result ? $result : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function validate_input($courseid, $sectionid, $module_id, $scope) {
        global $DB;
        if (!$courseid || !$sectionid) throw new Exception('Invalid course ID or section ID');
        if (!in_array($scope, [self::SCOPE_TOPIC, self::SCOPE_MIDTERM, self::SCOPE_FINAL])) throw new Exception('Invalid scope.');
        if (!$DB->record_exists('course', ['id' => $courseid])) throw new Exception("Course not found");
        
        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid]);
        if (!$section) throw new Exception("Section not found in course");
        
        if ($module_id) {
            $cm = $DB->get_record('course_modules', ['id' => $module_id, 'course' => $courseid]);
            if (!$cm || $cm->section != $sectionid) throw new Exception("Invalid module");
        }
        return true;
    }

    public static function cleanup_invalid_content() {
        global $DB;
        return ['deleted' => 0];
    }

    public static function process_rag($courseid, $sectionid, $module_id = null, $scope = self::SCOPE_TOPIC) {
        global $DB;
        
        try {
            self::validate_input($courseid, $sectionid, $module_id, $scope);
            $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid]);
            if (!$section) throw new Exception("Section not found");
            
            if ($module_id) {
                $DB->delete_records('ai_rag_content', ['module_id' => $module_id, 'scope' => $scope]);
            }

            $all_content = [];
            if (!empty($section->summary)) {
                $all_content[] = [
                    'type' => 'section_summary', 
                    'id' => $sectionid, 
                    'content' => strip_tags($section->summary)
                ];
            }

            if (!empty($section->sequence)) {
                $sequence = explode(',', $section->sequence);
                foreach ($sequence as $cmid) {
                    $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                    if (!$cm || !$cm->visible) continue;
                    
                    $module = $DB->get_record('modules', ['id' => $cm->module]);
                    if (!$module) continue;
                    
                    $content = self::extract_module_content($module->name, $cm->instance);
                    if ($content) {
                        $all_content[] = [
                            'type' => $module->name, 
                            'id' => $cm->instance, 
                            'content' => $content
                        ];
                    }
                    unset($cm); unset($module); unset($content);
                }
            }

            $combined_content = self::combine_content($all_content);
            if (empty(trim($combined_content))) {
                return ['success' => false, 'error' => 'No content found to process'];
            }
            
            $chunks = self::split_into_chunks($combined_content);
            $processed_chunks = json_encode($chunks);

            $record = (object)[
                'course_id' => $courseid,
                'section_id' => $sectionid,
                'content_type' => 'mixed',
                'content_id' => $module_id,
                'original_content' => $combined_content,
                'processed_chunks' => $processed_chunks,
                'chunk_count' => count($chunks),
                'timecreated' => time(),
                'timemodified' => time(),
                'week_number' => $section->section,
                'content_scope' => $scope,
                'module_id' => $module_id,
                'content' => $combined_content,
                'scope' => $scope
            ];

            $params = ['course_id' => $courseid, 'section_id' => $sectionid, 'scope' => $scope];
            if ($module_id) $params['module_id'] = $module_id;

            $existing_records = $DB->get_records('ai_rag_content', $params, 'id DESC');
            $existing = !empty($existing_records) ? reset($existing_records) : null;

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('ai_rag_content', $record);
                $ragid = $existing->id;
            } else {
                $ragid = $DB->insert_record('ai_rag_content', $record);
            }

            $chunk_count = count($chunks);
            self::store_embeddings($ragid, $chunks);
            
            unset($all_content); unset($combined_content); unset($chunks); unset($processed_chunks);
            return ['success' => true, 'chunks' => $chunk_count];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function extract_module_content($modulename, $instanceid) {
        global $DB; 
        $content = '';
        try {
            switch ($modulename) {
                case 'page': 
                    $data = $DB->get_record('page', ['id' => $instanceid]); 
                    if ($data) $content = "Title: " . $data->name . "\n" . strip_tags($data->content);
                    break;
                case 'label': 
                    $data = $DB->get_record('label', ['id' => $instanceid]); 
                    if ($data) $content = strip_tags($data->intro);
                    break;
                case 'book':
                    $book = $DB->get_record('book', ['id' => $instanceid]);
                    if ($book) {
                        $content .= "SUMBER BUKU: " . $book->name . "\n" . strip_tags($book->intro) . "\n\n";
                        $chapters = $DB->get_records('book_chapters', ['bookid' => $instanceid, 'hidden' => 0], 'pagenum ASC');
                        if ($chapters) {
                            $chapter_count = 0;
                            foreach ($chapters as $ch) {
                                $chapter_count++;
                                $raw_text = strip_tags($ch->content);
                                if (strlen($raw_text) > 3000) $raw_text = substr($raw_text, 0, 3000) . "\n[... truncated ...]";
                                $content .= "--- BAB " . $chapter_count . ": " . $ch->title . " ---\n" . $raw_text . "\n\n";
                                unset($raw_text); unset($ch);
                                if ($chapter_count >= 50) break;
                            }
                        }
                    }
                    break;
                case 'url':
                     $data = $DB->get_record('url', ['id' => $instanceid]);
                     if ($data) $content = "Link: " . $data->name . " (" . $data->externalurl . ")\n" . strip_tags($data->intro);
                     break;
                case 'forum':
                     $forum = $DB->get_record('forum', ['id' => $instanceid]);
                     if ($forum) $content = "Forum: " . $forum->name . "\n" . strip_tags($forum->intro);
                     break;
                case 'resource':
                     $resource = $DB->get_record('resource', ['id' => $instanceid]);
                     if ($resource) $content = "Resource: " . $resource->name . "\n" . strip_tags($resource->intro);
                     break;
            }
        } catch (Exception $e) {}
        return trim($content);
    }

    private static function combine_content($content_array) {
        $combined = ""; 
        foreach ($content_array as $item) { 
            $combined .= "=== " . strtoupper($item['type']) . " ===\n" . $item['content'] . "\n\n"; 
        }
        return $combined;
    }

    private static function split_into_chunks($content) {
        $chunks = []; 
        $chunk_size = 800; 
        $paragraphs = preg_split('/\n\s*\n/', $content); 
        $current = '';
        foreach ($paragraphs as $p) {
            if (strlen($current . $p) < $chunk_size) { 
                $current .= $p . "\n\n"; 
            } else { 
                if (!empty($current)) $chunks[] = trim($current); 
                $current = $p . "\n\n"; 
            }
        }
        if (!empty($current)) $chunks[] = trim($current);
        return $chunks;
    }

    private static function store_embeddings($ragid, $chunks) {
        global $DB;
        $DB->delete_records('ai_rag_embeddings', ['rag_content_id' => $ragid]);
        foreach ($chunks as $i => $chunk_text) {
            try {
                $hash = substr(sha1($chunk_text), 0, 64);
                if ($DB->record_exists('ai_rag_embeddings', ['rag_content_id' => $ragid, 'embedding_hash' => $hash])) continue;
                $record = (object)[
                    'rag_content_id' => $ragid,
                    'chunk_index' => $i,
                    'chunk_text' => $chunk_text,
                    'embedding_hash' => $hash,
                    'embedding' => null
                ];
                $DB->insert_record('ai_rag_embeddings', $record);
            } catch (Exception $e) {}
        }
    }

    public static function retrieve_context($courseid, $sectionid, $query, $scope = self::SCOPE_TOPIC, $module_id = null) {
        global $DB;
        $params = ['courseid' => $courseid];
        $where_conditions = ["c.course_id = :courseid"];

        if ($scope === self::SCOPE_TOPIC) {
            $where_conditions[] = "c.section_id = :sectionid";
            $params['sectionid'] = $sectionid;
        } elseif ($scope === self::SCOPE_MIDTERM) {
            $where_conditions[] = "cs.section BETWEEN 1 AND 7";
        } elseif ($scope === self::SCOPE_FINAL) {
            $where_conditions[] = "cs.section > 0";
        }

        if ($module_id) {
            $where_conditions = ["c.module_id = :moduleid"];
            $params = ['moduleid' => $module_id];
        }

        $keywords = explode(' ', $query);
        $search_conditions = [];
        foreach ($keywords as $index => $keyword) {
            if (strlen($keyword) >= 3) {
                $param_name = 'keyword' . $index;
                $search_conditions[] = "e.chunk_text LIKE :{$param_name}";
                $params[$param_name] = '%' . $keyword . '%';
            }
        }
        if (empty($search_conditions)) $search_conditions[] = "1=1"; 

        $sql = "SELECT e.chunk_text
                FROM {ai_rag_embeddings} e
                JOIN {ai_rag_content} c ON e.rag_content_id = c.id
                JOIN {course_sections} cs ON c.section_id = cs.id
                WHERE " . implode(' AND ', $where_conditions) . "
                      AND (" . implode(' OR ', $search_conditions) . ")
                LIMIT " . (defined('RAG_TOP_K') ? RAG_TOP_K : 20);

        $records = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($records as $record) $result[] = ['chunk' => $record->chunk_text];
        return $result;
    }
}