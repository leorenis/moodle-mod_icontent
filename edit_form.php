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
 * @copyright  2015-2016 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
$PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/icontent/js/jscolor/jscolor.js'));

class icontent_pages_edit_form extends moodleform {

    function definition() {
        global $CFG, $COURSE;

        $page = $this->_customdata['page'];
        $pageicontentoptions = $this->_customdata['pageicontentoptions'];
		
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
		
        $mform->addElement('advcheckbox', 'coverpage', get_string('coverpage', 'icontent'));
		$mform->addHelpButton('coverpage', 'coverpage', 'icontent');
		$mform->setType('coverpage', PARAM_INT);
        $mform->setDefault('coverpage', 0);
		
		$mform->addElement('editor', 'pageicontent_editor', get_string('icontent', 'mod_icontent'), null, $pageicontentoptions);
        $mform->setType('pageicontent_editor', PARAM_RAW);
        $mform->addRule('pageicontent_editor', get_string('required'), 'required', null, 'client');
		
		$mform->addElement('header', 'appearance', get_string('appearance'));

		$layouts = array(
			'1' => get_string('fluid','icontent'),
			'2' => get_string('collumns2','icontent'),
			'3' => get_string('collumns3','icontent'),
			'4' => get_string('collumns4','icontent'),
			'5' => get_string('collumns5','icontent'),
		);
		$mform->addElement('select', 'layout', get_string('layout','icontent'), $layouts);
		$mform->addHelpButton('layout', 'layouthelp', 'icontent');
		
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
		
		$mform->addElement('text', 'maxnotesperpages', get_string('maxnotesperpages', 'icontent'), array('class'=>'x-large'));
		$mform->setType('maxnotesperpages', PARAM_INT);
		$mform->addHelpButton('maxnotesperpages', 'maxnotesperpageshelp', 'icontent');
		$mform->addRule('maxnotesperpages', null, 'numeric', null, 'client');
		$mform->addRule('maxnotesperpages', get_string('maximumdigits', 'icontent', 3), 'maxlength', 3, 'client');
		
		$mform->addElement('advcheckbox', 'expandquestionsarea', get_string('expandquestionsarea', 'icontent'));
		$mform->addHelpButton('expandquestionsarea', 'expandquestionsarea', 'icontent');
		$mform->setType('expandquestionsarea', PARAM_INT);
		$mform->setDefault('expandquestionsarea', 0);
		
		$mform->addElement('advcheckbox', 'expandnotesarea', get_string('expandnotesarea', 'icontent'));
		$mform->addHelpButton('expandnotesarea', 'expandnotesarea', 'icontent');
		$mform->setType('expandnotesarea', PARAM_INT);
		$mform->setDefault('expandnotesarea', 0);
		
		$mform->addElement('header', 'grade', get_string('grade'));
		$mform->addElement('select', 'attemptsallowed', get_string('attemptsallowed', 'icontent'), array('0'=>get_string('unlimited'), '1'=> '1 '.get_string('attempt', 'mod_icontent')));
		$mform->addHelpButton('attemptsallowed', 'attemptsallowedhelp', 'icontent');
		
		$mform->addElement('header', 'effects', get_string('effects', 'icontent'));
		$effects = array(
			'0' => get_string('noeffect', 'mod_icontent'),
			'blind' => 'Blind',
			'bounce' => 'Bounce',
			'clip' => 'Clip',
			'drop' => 'Drop',
			'explode' => 'Explode',
			'fade' => 'Fade',
			'fold' => 'Fold',
			'puff' => 'Puff',
			'pulsate' => 'Pulsate',
			'scale' => 'Scale',
			'shake' => 'Shake',
			'size' => 'Size',
			'slide' => 'Slide',
		);
		
		$mform->addElement('select', 'transitioneffect', get_string('transitioneffect','icontent'), $effects);
		$mform->addHelpButton('transitioneffect', 'transitioneffecthelp', 'icontent');
		$mform->setDefault('transitioneffect', 0);
		
        $this->add_action_buttons(true);
		
		// set the defaults
        $this->set_data($page);

    }
}
