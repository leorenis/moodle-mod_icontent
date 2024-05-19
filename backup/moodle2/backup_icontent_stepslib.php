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
 * Define all the backup steps that will be used by the backup_icontent_activity_task
 *
 * @package   mod_icontent
 * @category  backup
 * @copyright 2016 Leo Renis Santos <leorenis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die; // @codingStandardsIgnoreLine

/**
 * Define the complete icontent structure for backup, with file and id annotations
 *
 * @package   mod_icontent
 * @category  backup
 * @copyright 2015 Leo Renis Santos <leorenis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_icontent_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the icontent instance.
        $icontent = new backup_nested_element('icontent', ['id'],
            ['name',
            'intro',
            'introformat',
            'grade',
            'scale',
            'bgimage',
            'bgcolor',
            'bordercolor',
            'borderwidth',
            'evaluative',
            'maxpages',
            'progressbar',
            'shownotesarea',
            'copyright',
            'maxnotesperpages',
            ]
        );

        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', ['id'],
            ['coverpage',
            'title',
            'showtitle',
            'pageicontent',
            'pageicontentformat',
            'showbgimage',
            'bgimage',
            'bgcolor',
            'layout',
            'transitioneffect',
            'bordercolor',
            'borderwidth',
            'pagenum',
            'hidden',
            'maxnotesperpages',
            'attemptsallowed',
            'expandnotesarea',
            'expandquestionsarea',
            'timecreated',
            'timemodified',
            ]
        );

        $pagequestions = new backup_nested_element('page_questions');
        $pagequestion = new backup_nested_element('page_question', ['id'],
            ['questionid',
            'timecreated',
            'timemodified',
            'remake',
            ]
        );

        $pagesnotes = new backup_nested_element('pages_notes');
        $pagesnote = new backup_nested_element('pages_note', ['id'],
            ['userid',
            'comment',
            'timecreated',
            'timemodified',
            'tab',
            'path',
            'parent',
            'private',
            'featured',
            'doubttutor',
            ]
        );

        $noteslikes = new backup_nested_element('notes_likes');
        $noteslike = new backup_nested_element('notes_like', ['id'],
            ['userid',
            'timemodified',
            'visible',
            ]
        );

        $pagesdisplayeds = new backup_nested_element('pages_displayeds');
        $pagesdisplayed = new backup_nested_element('pages_displayed', ['id'],
            ['userid',
            'timecreated',
            ]
        );

        $questionattempts = new backup_nested_element('question_attempts');
        $questionattempt = new backup_nested_element('question_attempt', ['id'],
            ['questionid',
            'userid',
            'fraction',
            'rightanswer',
            'answertext',
            'timecreated',
            ]
        );

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'],
            ['userid',
            'cmid',
            'grade',
            'timemodified',
            ]
        );

        // Build the tree.
        $icontent->add_child($pages);
        $pages->add_child($page);

        $page->add_child($pagequestions);
        $pagequestions->add_child($pagequestion);

        $page->add_child($pagesnotes);
        $pagesnotes->add_child($pagesnote);

        $pagesnote->add_child($noteslikes);
        $noteslikes->add_child($noteslike);

        $page->add_child($pagesdisplayeds);
        $pagesdisplayeds->add_child($pagesdisplayed);

        $pagequestion->add_child($questionattempts);
        $questionattempts->add_child($questionattempt);

        $icontent->add_child($grades);
        $grades->add_child($grade);

        // Define data sources.
        $icontent->set_source_table('icontent', ['id' => backup::VAR_ACTIVITYID]);
        $page->set_source_table('icontent_pages', ['icontentid' => backup::VAR_PARENTID]);
        $pagequestion->set_source_table('icontent_pages_questions', ['pageid' => backup::VAR_PARENTID]);

        // All these source definitions only happen if we are including user info.
        if ($userinfo) {
            $pagesnote->set_source_table('icontent_pages_notes', ['pageid' => backup::VAR_PARENTID]);
            $noteslike->set_source_table('icontent_pages_notes_like', ['pagenoteid' => backup::VAR_PARENTID]);
            $pagesdisplayed->set_source_table('icontent_pages_displayed', ['pageid' => backup::VAR_PARENTID]);
            $questionattempt->set_source_table('icontent_question_attempts', ['pagesquestionsid' => backup::VAR_PARENTID]);
            $grade->set_source_table('icontent_grades', ['icontentid' => backup::VAR_PARENTID]);
        }

        // If we were referring to other tables, we would annotate the relation with the element's annotate_ids() method.
        // Define id annotations.
        $pagesnote->annotate_ids('user', 'userid');
        $pagequestion->annotate_ids('question', 'questionid');
        $noteslike->annotate_ids('user', 'userid');
        $pagesdisplayed->annotate_ids('user', 'userid');
        $questionattempt->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'userid');

        // Define file annotations.
        $icontent->annotate_files('mod_icontent', 'intro', null);
        $page->annotate_files('mod_icontent', 'page', 'id');
        $page->annotate_files('mod_icontent', 'bgpage', 'id');

        // Return the root element (icontent), wrapped into standard activity structure.
        return $this->prepare_activity_structure($icontent);
    }
}
