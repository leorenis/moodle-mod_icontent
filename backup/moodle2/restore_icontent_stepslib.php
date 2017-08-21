<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Define all the restore steps that will be used by the restore_icontent_activity_task
 *
 * @package   mod_icontent
 * @category  backup
 * @copyright 2016 Leo Renis Santos <leorenis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one icontent activity
 */
class restore_icontent_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
        $paths[] = new restore_path_element('icontent', '/activity/icontent');
        $paths[] = new restore_path_element('icontent_page', '/activity/icontent/pages/page');
        $paths[] = new restore_path_element('icontent_page_question', '/activity/icontent/page_questions/page_question');
        
        if ($userinfo) {
        	$paths[] = new restore_path_element('icontent_page_note', '/activity/icontent/pages_notes/pages_note');
        	$paths[] = new restore_path_element('icontent_page_note_like', '/activity/icontent/notes_likes/notes_like');
        	$paths[] = new restore_path_element('icontent_page_displayed', '/activity/icontent/pages_displayeds/pages_displayed');
        	$paths[] = new restore_path_element('icontent_question_attempt', '/activity/icontent/question_attempts/question_attempt');
        	$paths[] = new restore_path_element('icontent_grade', '/activity/icontent/grades/grade');
        }
        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_icontent($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }

        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the icontent instance.
        $newitemid = $DB->insert_record('icontent', $data);
        $this->apply_activity_instance($newitemid);
    }
    
    protected function process_icontent_page($data) {
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	// Get new course module id
    	$icontentid = $this->get_new_parentid('icontent');
    	$cm = get_coursemodule_from_instance('icontent', $icontentid);
    	
    	$data->cmid = $cm->id;
    	$data->icontentid = $this->get_new_parentid('icontent');
    	$data->timecreated = $this->apply_date_offset($data->timecreated);
    	$data->timemodified = $this->apply_date_offset($data->timemodified);
    	
    	$newitemid = $DB->insert_record('icontent_pages', $data);
    	$this->set_mapping('icontent_page', $oldid, $newitemid);
    }
    
    protected function process_icontent_page_question($data){
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	
    	$data->pageid = $this->get_new_parentid('icontent_page'); 
    	// $this->get_mappingid('icontent_page', $data->pageid);
    	$data->questionid = $this->get_mappingid('question', $data->questionid);
    	$data->cmid = $this->get_mappingid('icontent_page', $data->cmid);
    	$data->timecreated = $this->apply_date_offset($data->timecreated);
    	$data->timemodified = $this->apply_date_offset($data->timemodified);
    	
    	$newitemid = $DB->insert_record('icontent_pages_questions', $data);
    	$this->set_mapping('icontent_page_question', $oldid, $newitemid);
    }
    
    protected function process_icontent_page_note($data) {
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	
    	$data->pageid = $this->get_new_parentid('icontent_page'); 
    	// $this->get_mappingid('icontent_page', $data->pageid);
    	$data->userid = $this->get_mappingid('user', $data->userid);
    	$data->cmid = $this->get_mappingid('icontent_page', $data->cmid);
    	$data->timecreated = $this->apply_date_offset($data->timecreated);
    	$data->timemodified = $this->apply_date_offset($data->timemodified);
    	
    	$newitemid = $DB->insert_record('icontent_pages_notes', $data);
    	$this->set_mapping('icontent_page_note', $oldid, $newitemid);
    }
    
    protected function process_icontent_page_note_like($data) {
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	
    	$data->pagenoteid = $this->get_new_parentid('icontent_page_note'); 
    	// $data->pagenoteid = $this->get_mappingid('icontent_page_note', $data->pagenoteid);
    	$data->userid = $this->get_mappingid('user', $data->userid);
    	$data->cmid = $this->get_mappingid('icontent_page', $data->cmid);
    	$data->timemodified = $this->apply_date_offset($data->timemodified);
    	 
    	$newitemid = $DB->insert_record('icontent_pages_notes_like', $data);
    	$this->set_mapping('icontent_page_note_like', $oldid, $newitemid);
    }
    
    protected function process_icontent_page_displayed($data) {
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	
    	$data->pageid = $this->get_new_parentid('icontent_page'); 
    	// $data->pageid = $this->get_mappingid('icontent_page', $data->pageid);
    	$data->cmid = $this->get_mappingid('icontent_page', $data->cmid);
    	$data->userid = $this->get_mappingid('user', $data->userid);
    	$data->timecreated = $this->apply_date_offset($data->timecreated);
    	
    	$newitemid = $DB->insert_record('icontent_pages_displayed', $data);
    	$this->set_mapping('icontent_page_displayed', $oldid, $newitemid);
    }
    
    protected function process_icontent_question_attempt($data) {
    	global $DB;
    	
    	$data = (object)$data;
    	$oldid = $data->id;
    	
    	$data->pagesquestionsid = $this->get_new_parentid('icontent_page_question'); 
    	// $data->pagesquestionsid = $this->get_mappingid('icontent_page_question', $data->pagesquestionsid);
    	$data->questionid = $this->get_mappingid('question', $data->questionid);
    	$data->userid = $this->get_mappingid('user', $data->userid);
    	$data->cmid = $this->get_mappingid('icontent_page', $data->cmid);
    	$data->timecreated = $this->apply_date_offset($data->timecreated);
    	 
    	$newitemid = $DB->insert_record('icontent_pages_displayed', $data);
    	$this->set_mapping('icontent_question_attempt', $oldid, $newitemid);
    }
    
    protected function process_icontent_grade($data) {
    	global $DB;
    	 
    	$data = (object)$data;
    	$oldid = $data->id;
    	// Get new course module id
    	$icontentid = $this->get_new_parentid('icontent');
    	$cm = get_coursemodule_from_instance('icontent', $icontentid);
    	
    	$data->icontentid = $this->get_new_parentid('icontent');
    	$data->userid = $this->get_mappingid('user', $data->userid);
    	$data->cmid = $cm->id;
    	$data->timemodified = $this->apply_date_offset($data->timemodified);
    	 
    	$newitemid = $DB->insert_record('icontent_grades', $data);
    	$this->set_mapping('icontent_grade', $oldid, $newitemid);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add icontent related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_icontent', 'intro', null);
        
        // Add page related files, matching by itemname = 'icontent_page'
        $this->add_related_files('mod_icontent', 'page', 'icontent_page');
        $this->add_related_files('mod_icontent', 'bgpage', 'icontent_page');
    }
}
