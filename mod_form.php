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
 * The main icontent configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/filelib.php');
$PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/icontent/js/jscolor/jscolor.js'));

/**
 * Module instance settings form
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_icontent_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
		global $COURSE;
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('icontentname', 'icontent'), array('class'=>'input-xxlarge'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'icontentname', 'icontent');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();
		
		$mform->addElement('text', 'copyright', get_string('copyright', 'icontent'), array('class'=>'input-xxlarge'));
        $mform->setType('copyright', PARAM_RAW);
		$mform->addHelpButton('copyright', 'copyright', 'icontent');
		
		// Appearance.
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));
		
		$filemanager_options = array();
        $filemanager_options['accepted_types'] = array('.jpg', '.png');
        $filemanager_options['maxbytes'] = $COURSE->maxbytes;
        $filemanager_options['maxfiles'] = 1;
        $filemanager_options['subdirs'] = 0;

		$mform->addElement('filemanager', 'bgimage', get_string('bgimage', 'icontent'), null, $filemanager_options);
		$mform->setType('bgimage', PARAM_INT);
		$mform->addHelpButton('bgimage', 'bgimagehelp', 'icontent');
		
		$mform->addElement('text', 'bgcolor', get_string('bgcolor', 'icontent'), array('class' => 'color', 'value'=>'FCFCFC'));
		$mform->setType('bgcolor', PARAM_TEXT);
		$mform->addHelpButton('bgcolor', 'bgcolorhelp', 'icontent');
		
		$mform->addElement('text', 'bordercolor', get_string('bordercolor', 'icontent'), array('class' => 'color', 'value'=>'E4E4E4'));
		$mform->setType('bordercolor', PARAM_TEXT);
		$mform->addHelpButton('bordercolor', 'bordercolorhelp', 'icontent');
		
		$options = icontent_add_borderwidth_options();
		$mform->addElement('select', 'borderwidth', get_string('borderwidth','icontent'), $options);
		$mform->setType('borderwidth', PARAM_INT);
		$mform->addHelpButton('borderwidth', 'borderwidthhelp', 'icontent');
		$mform->setDefault('borderwidth', 1);
		
		$mform->addElement('text', 'maxpages', get_string('maxpages', 'icontent'), array('class'=>'x-large'));
		$mform->setType('maxpages', PARAM_INT);
		$mform->addHelpButton('maxpages', 'maxpageshelp', 'icontent');
		$mform->addRule('maxpages', null, 'numeric', null, 'client');
		$mform->addRule('maxpages', get_string('maximumdigits', 'icontent', 3), 'maxlength', 3, 'client');
		$mform->setDefault('maxpages', 55);
		
		$mform->addElement('selectyesno', 'shownotesarea', get_string('shownotesarea', 'icontent'));
		$mform->addHelpButton('shownotesarea', 'shownotesarea', 'icontent');
		$mform->setType('shownotesarea', PARAM_INT);
		$mform->setDefault('shownotesarea', 1);
		
		$mform->addElement('text', 'maxnotesperpages', get_string('maxnotesperpages', 'icontent'), array('class'=>'x-large'));
		$mform->setType('maxnotesperpages', PARAM_INT);
		$mform->addHelpButton('maxnotesperpages', 'maxnotesperpageshelp', 'icontent');
		$mform->addRule('maxnotesperpages', null, 'numeric', null, 'client');
		$mform->addRule('maxnotesperpages', get_string('maximumdigits', 'icontent', 3), 'maxlength', 3, 'client');
		$mform->setDefault('maxnotesperpages', 15);
		
		$mform->addElement('selectyesno', 'progressbar', get_string('progressbar', 'icontent'));
        $mform->addHelpButton('progressbar', 'progressbar', 'icontent');
        $mform->setType('progressbar', PARAM_INT);
        $mform->setDefault('progressbar', 1);
		
		// Grade.
    	$this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

	function data_preprocessing(&$default_values) {
			
			if ($this->current->instance) {
				$draftitemid = file_get_submitted_draft_itemid('bgimage');
				
				file_save_draft_area_files($default_values['bgimage'], $this->context->id, 'mod_icontent', 'icontent',
				0, array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1));
	        }
        }
}
