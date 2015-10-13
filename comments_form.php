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
 * Chapter edit form
 *
 * @package    mod_icontent
 * @copyright  2004-2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

class icontent_comments_form extends moodleform {

    function definition() {
        global $CFG, $COURSE;

        $comment 		= $this->_customdata['comment'];
        $commentoptions	= $this->_customdata['commentoptions'];
		
		$mform = $this->_form;
		
		if (!empty($comment->id)) {
            $mform->addElement('header', 'general', get_string('editingpage', 'icontent'));
			$timemodified = time(); 
			$timecreated = 0;
        } else {
            $mform->addElement('header', 'general', get_string('addafter', 'icontent'));
			$timecreated = time();
			$timemodified = 0;
        }
		
		$mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
		$mform->setDefault('id', $comment->id);
		
		$mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);
		$mform->setDefault('pageid', $comment->pageid);
		
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
		$mform->setDefault('cmid', $comment->cmid);
		
		$mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);
		$mform->setDefault('timemodified', $timemodified);
		
		$mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);
		$mform->setDefault('timecreated', $timecreated);
		
		$mform->addElement('hidden', 'path');
        $mform->setType('path', PARAM_RAW);
		$mform->setDefault('path', $comment->path);
		
		$mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_RAW);
		$mform->setDefault('parent', $comment->parent);
		
		$mform->addElement('textarea', 'comment');
        $mform->setType('comment', PARAM_RAW);
		
		$this->add_action_buttons(true);
		
		// set the defaults
	    $this->set_data($page);
	}
}