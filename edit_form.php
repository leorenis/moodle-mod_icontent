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
$PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/icontent/js/jscolor/jscolor.js'));

class icontent_pages_edit_form extends moodleform {

    function definition() {
        global $CFG, $COURSE;

        $page 		= $this->_customdata['page'];
        $pageicontentoptions	= $this->_customdata['pageicontentoptions'];
		
        // Disabled subchapter option when editing first node.
        $disabledmsg = null;
        $disabled	 = null;
        if ($page->pagenum == 1) {
            $disabledmsg	= get_string('subpagenotice', 'icontent');
			$disabled		= array('disabled'=>'disabled');
        }
		
        $mform = $this->_form;

        if (!empty($page->id)) {
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
		$mform->setDefault('id', $page->id);
		
		$mform->addElement('hidden', 'icontentid');
        $mform->setType('icontentid', PARAM_INT);
		$mform->setDefault('icontentid', $page->icontentid);
		
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
		$mform->setDefault('cmid', $page->cmid);
		
		$mform->addElement('hidden', 'pagenum');
        $mform->setType('pagenum', PARAM_INT);
		$mform->setDefault('pagenum', $page->pagenum);
		
		$mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);
		$mform->setDefault('timemodified', $timemodified);
		
		$mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);
		$mform->setDefault('timecreated', $timecreated);
		
        $mform->addElement('text', 'title', get_string('pagetitle', 'icontent'), array('class'=>'input-xxlarge'));
        $mform->setType('title', PARAM_RAW);
        $mform->addRule('title', null, 'required', null, 'client');
		
        $mform->addElement('checkbox', 'subpage', get_string('subpage', 'icontent'), $disabledmsg, $disabled);
		$mform->addHelpButton('subpage', 'subpage', 'icontent');
		$mform->setType('subpage', PARAM_INT);
        $mform->setDefault('subpage', 0);
		
		$mform->addElement('editor', 'pageicontent_editor', get_string('icontent', 'mod_icontent'), null, $pageicontentoptions);
        $mform->setType('pageicontent_editor', PARAM_RAW);
        $mform->addRule('pageicontent_editor', get_string('required'), 'required', null, 'client');
		
		$mform->addElement('header', 'appearance', get_string('appearance'));
		
		$mform->addElement('advcheckbox', 'showtitle', get_string('showtitle', 'icontent'));
        $mform->addHelpButton('showtitle', 'showtitle', 'icontent');
		$mform->setType('showtitle', PARAM_INT);
        $mform->setDefault('showtitle', 1);
		
		$mform->addElement('advcheckbox', 'showbgimage', get_string('showbgimage', 'icontent'));
        $mform->addHelpButton('showbgimage', 'showbgimage', 'icontent');
		$mform->setType('showbgimage', PARAM_INT);
        $mform->setDefault('showbgimage', 1);
		
		$filemanager_options = array();
        $filemanager_options['accepted_types'] = array('.jpg', '.png');
        $filemanager_options['maxbytes'] = $COURSE->maxbytes;
        $filemanager_options['maxfiles'] = 1;
        $filemanager_options['subdirs'] = 0;

		$mform->addElement('filemanager', 'bgimage', get_string('bgimage', 'icontent'), null, $filemanager_options);
		$mform->setType('bgimage', PARAM_INT);
		$mform->addHelpButton('bgimage', 'bgimagepagehelp', 'icontent');
		//$mform->disabledIf('bgimage', 'showbgimage');
		
		$mform->addElement('text', 'bgcolor', get_string('bgcolor', 'icontent'), array('class' => 'color', 'value'=>'FCFCFC'));
		$mform->setType('bgcolor', PARAM_TEXT);
		$mform->addHelpButton('bgcolor', 'bgcolorpagehelp', 'icontent');
		
		$mform->addElement('text', 'bordercolor', get_string('bordercolor', 'icontent'), array('class' => 'color', 'value'=>'E4E4E4'));
		$mform->setType('bordercolor', PARAM_TEXT);
		$mform->addHelpButton('bordercolor', 'bordercolorpagehelp', 'icontent');
		
		$opts = icontent_add_borderwidth_options();
		$mform->addElement('select', 'borderwidth', get_string('borderwidth','icontent'), $opts);
		$mform->setType('borderwidth', PARAM_INT);
		$mform->addHelpButton('borderwidth', 'borderwidthpagehelp', 'icontent');
		$mform->setDefault('borderwidth', 1);
		
		$mform->addElement('header', 'effects', get_string('effects', 'icontent'));
		$effects = array(
			'blind' => 'Blind',
			'bounce' => 'Bounce',
			'clip' => 'Clip',
			'drop' => 'Drop',
			'explode' => 'Explode',
			'fade' => 'Fade',
			'fold' => 'Fold',
			'highlight' => 'Highlight',
			'puff' => 'Puff',
			'pulsate' => 'Pulsate',
			'scale' => 'Scale',
			'shake' => 'Shake',
			'size' => 'Size',
			'slide' => 'Slide',
			'transfer' => 'Transfer',
		);
		
		$mform->addElement('select', 'nexttransitiontype', get_string('nexttransitiontype','icontent'), $effects);
		$mform->addHelpButton('nexttransitiontype', 'nexttransitiontypehelp', 'icontent');
		
		$mform->addElement('select', 'prevtransitiontype', get_string('prevtransitiontype','icontent'), $effects);
		$mform->addHelpButton('prevtransitiontype', 'prevtransitiontypehelp', 'icontent');
		
		$mform->addElement('select', 'texttransitiontype', get_string('texttransitiontype','icontent'), $effects);
		$mform->addHelpButton('texttransitiontype', 'texttransitiontypehelp', 'icontent');
		
		$mform->addElement('select', 'imagetransitiontype', get_string('imagetransitiontype','icontent'), $effects);
		$mform->addHelpButton('imagetransitiontype', 'imagetransitiontypehelp', 'icontent');
		
        $this->add_action_buttons(true);
		
		// set the defaults
        $this->set_data($page);

    }
}
