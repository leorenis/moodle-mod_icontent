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
 * Chapter edit form.
 *
 * @package    mod_icontent
 * @copyright  2015-2016 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Class mod_icontent_pages_edit_form
 */
class icontent_pages_edit_form extends moodleform {
    /**
     * Define form elements
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {
        global $CFG, $COURSE, $USER, $DB;

        $page = $this->_customdata['page'];
        $pageicontentoptions = $this->_customdata['pageicontentoptions'];
        $bgimagemaxbytes = $this->_customdata['bgimagemaxbytes'] ?? 0;

        $mform = $this->_form;
        $icontentconfig = get_config('mod_icontent');

        $page->bgcolor = self::format_colour_for_picker($page->bgcolor ?? $icontentconfig->bgcolor ?? '#FCFCFC', '#FCFCFC');
        $page->bordercolor = self::format_colour_for_picker(
            $page->bordercolor ?? $icontentconfig->bordercolor ?? '#E4E4E4',
            '#E4E4E4'
        );
        $page->titlecolor = self::format_colour_for_picker($page->titlecolor ?? '#000000', '#000000');

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

        // Need to see about using the pagenum to implement the move capability.
        $mform->addElement('hidden', 'pagenum');
        $mform->setType('pagenum', PARAM_INT);
        $mform->setDefault('pagenum', $page->pagenum);

        $mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);
        $mform->setDefault('timemodified', $timemodified);

        $mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);
        $mform->setDefault('timecreated', $timecreated);

        $mform->addElement('text', 'title', get_string('pagetitle', 'icontent'), ['class' => 'input-xxlarge']);
        $mform->setType('title', PARAM_RAW);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'coverpage', get_string('coverpage', 'icontent'));
        $mform->addHelpButton('coverpage', 'coverpage', 'icontent');
        $mform->setType('coverpage', PARAM_INT);
        $mform->setDefault('coverpage', 0);

        $branchparentoptions = [0 => get_string('none')];
        $branchparentpages = $DB->get_records(
            'icontent_pages',
            [
                'icontentid' => (int)$page->icontentid,
                'cmid' => (int)$page->cmid,
                'hidden' => 0,
                'branchparentpageid' => 0,
            ],
            'pagenum ASC',
            'id, pagenum, title'
        );
        foreach ($branchparentpages as $branchparentpage) {
            if (!empty($page->id) && (int)$branchparentpage->id === (int)$page->id) {
                continue;
            }

            $branchparams = (object)[
                'pagenum' => (int)$branchparentpage->pagenum,
                'title' => format_string((string)$branchparentpage->title),
            ];
            $branchparentoptions[(int)$branchparentpage->id] = get_string('pagexwithtitle', 'icontent', $branchparams);
        }

        $custompageoptions = [0 => get_string('none')];
        $customtargetpages = $DB->get_records(
            'icontent_pages',
            [
                'icontentid' => (int)$page->icontentid,
                'cmid' => (int)$page->cmid,
                'hidden' => 0,
            ],
            'pagenum ASC',
            'id, pagenum, title'
        );
        foreach ($customtargetpages as $customtargetpage) {
            if (!empty($page->id) && (int)$customtargetpage->id === (int)$page->id) {
                continue;
            }

            $targetparams = (object)[
                'pagenum' => (int)$customtargetpage->pagenum,
                'title' => format_string((string)$customtargetpage->title),
            ];
            $custompageoptions[(int)$customtargetpage->id] = get_string('pagexwithtitle', 'icontent', $targetparams);
        }

        $mform->addElement('header', 'clustering', get_string('clustering', 'icontent'));
        $mform->addElement('select', 'branchparentpageid', get_string('branchparentpageid', 'icontent'), $branchparentoptions);
        $mform->addHelpButton('branchparentpageid', 'branchparentpageid', 'icontent');
        $mform->setType('branchparentpageid', PARAM_INT);
        $mform->setDefault('branchparentpageid', (int)($page->branchparentpageid ?? 0));

        $mform->addElement('text', 'branchref', get_string('branchref', 'icontent'), ['size' => 32]);
        $mform->addHelpButton('branchref', 'branchref', 'icontent');
        $mform->setType('branchref', PARAM_TEXT);
        $mform->setDefault('branchref', trim((string)($page->branchref ?? '')));
        $mform->hideIf('branchref', 'branchparentpageid', 'eq', 0);

        $mform->addElement('text', 'branchname', get_string('branchname', 'icontent'), ['size' => 40]);
        $mform->addHelpButton('branchname', 'branchname', 'icontent');
        $mform->setType('branchname', PARAM_TEXT);
        $mform->setDefault('branchname', trim((string)($page->branchname ?? '')));
        $mform->hideIf('branchname', 'branchparentpageid', 'eq', 0);

        $navigationmodeoptions = [
            0 => get_string('navmodeauto', 'icontent'),
            1 => get_string('navmodehide', 'icontent'),
            2 => get_string('navmodecustom', 'icontent'),
        ];

        $mform->addElement('header', 'pagebranchingnavigation', get_string('pagebranchingnavigation', 'icontent'));
        $mform->addElement('select', 'prevmode', get_string('prevmode', 'icontent'), $navigationmodeoptions);
        $mform->addHelpButton('prevmode', 'prevmode', 'icontent');
        $mform->setType('prevmode', PARAM_INT);
        $mform->setDefault('prevmode', (int)($page->prevmode ?? 0));

        $mform->addElement('select', 'prevpageid', get_string('prevpageid', 'icontent'), $custompageoptions);
        $mform->addHelpButton('prevpageid', 'prevpageid', 'icontent');
        $mform->setType('prevpageid', PARAM_INT);
        $mform->setDefault('prevpageid', (int)($page->prevpageid ?? 0));
        $mform->hideIf('prevpageid', 'prevmode', 'neq', 2);

        $mform->addElement('select', 'nextmode', get_string('nextmode', 'icontent'), $navigationmodeoptions);
        $mform->addHelpButton('nextmode', 'nextmode', 'icontent');
        $mform->setType('nextmode', PARAM_INT);
        $mform->setDefault('nextmode', (int)($page->nextmode ?? 0));

        $mform->addElement('select', 'nextpageid', get_string('nextpageid', 'icontent'), $custompageoptions);
        $mform->addHelpButton('nextpageid', 'nextpageid', 'icontent');
        $mform->setType('nextpageid', PARAM_INT);
        $mform->setDefault('nextpageid', (int)($page->nextpageid ?? 0));
        $mform->hideIf('nextpageid', 'nextmode', 'neq', 2);

        $mform->addElement('editor', 'pageicontent_editor', get_string('icontent', 'mod_icontent'), null, $pageicontentoptions);
        $mform->setType('pageicontent_editor', PARAM_RAW);
        $mform->addRule('pageicontent_editor', get_string('required'), 'required', null, 'client');

        // 20240920 Added tags to edit_form page.
        if (core_tag_tag::is_enabled('mod_icontent', 'icontent_pages')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
        }
        $mform->addElement(
            'tags',
            'tags',
            get_string('tags'),
            [
                'itemtype' => 'icontent_pages',
                'component' => 'mod_icontent',
            ]
        );

        $mform->addElement('header', 'appearance', get_string('appearance'));

        $layouts = [
            '1' => get_string('fluid', 'icontent'),
            '2' => get_string('columns2', 'icontent'),
            '3' => get_string('columns3', 'icontent'),
            '4' => get_string('columns4', 'icontent'),
            '5' => get_string('columns5', 'icontent'),
        ];
        $mform->addElement('select', 'layout', get_string('layout', 'icontent'), $layouts);
        $mform->addHelpButton('layout', 'layouthelp', 'icontent');

        $mform->addElement('advcheckbox', 'showtitle', get_string('showtitle', 'icontent'));
        $mform->addHelpButton('showtitle', 'showtitle', 'icontent');
        $mform->setType('showtitle', PARAM_INT);
        $mform->setDefault('showtitle', 1);

        $titleattributes = ['id' => 'icontent_titlecolor_picker', 'size' => '10', 'maxlength' => '7'];
        $mform->addElement('text', 'titlecolor', get_string('titlecolor', 'icontent'), $titleattributes);
        $mform->setType('titlecolor', PARAM_TEXT);
        $mform->addHelpButton('titlecolor', 'titlecolorhelp', 'icontent');
        $mform->setDefault('titlecolor', $page->titlecolor);

        $mform->addElement('advcheckbox', 'showbgimage', get_string('showbgimage', 'icontent'));
        $mform->addHelpButton('showbgimage', 'showbgimage', 'icontent');
        $mform->setType('showbgimage', PARAM_INT);
        $mform->setDefault('showbgimage', 1);

        // Set up options for the filemanager setting.
        $filemanageroptions = [];
        $filemanageroptions['subdirs'] = 0;
        $filemanageroptions['maxbytes'] = $bgimagemaxbytes;
        $filemanageroptions['maxfiles'] = 1;
        $filemanageroptions['accepted_types'] = ['web_image'];
        $filemanageroptions['return_types'] = FILE_INTERNAL | FILE_EXTERNAL;
        $mform->addElement('filemanager', 'bgimage_filemanager', get_string('bgimage', 'icontent'), null, $filemanageroptions);
        $mform->setType('bgimage_filemanager', PARAM_INT);
        $mform->addHelpButton('bgimage_filemanager', 'bgimagepagehelp', 'icontent');

        // Show currently stored page background files even if the JS filemanager UI is not working.
        $storedfileshtml = '';
        if (!empty($page->id)) {
            $modulecontext = context_module::instance((int)$page->cmid);
            $storedfiles = get_file_storage()->get_area_files(
                $modulecontext->id,
                'mod_icontent',
                'bgpage',
                (int)$page->id,
                'id',
                false
            );
            if (!empty($storedfiles)) {
                $links = [];
                foreach ($storedfiles as $storedfile) {
                    $fileurl = moodle_url::make_pluginfile_url(
                        $modulecontext->id,
                        'mod_icontent',
                        'bgpage',
                        (int)$page->id,
                        (string)$storedfile->get_filepath(),
                        (string)$storedfile->get_filename(),
                        true
                    );
                    $links[] = html_writer::link($fileurl, $storedfile->get_filename());
                }
                $storedfileshtml = html_writer::alist($links);
            }
        }
        if ($storedfileshtml !== '') {
            $mform->addElement('static', 'bgimage_current_files', get_string('files'), $storedfileshtml);
        }

        // Legacy bgcolor field setup comments removed.

        $bgattributes = ['id' => 'icontent_bgcolor_picker', 'size' => '10', 'maxlength' => '7'];
        $mform->addElement('text', 'bgcolor', get_string('bgcolor', 'icontent'), $bgattributes);
        $mform->setType('bgcolor', PARAM_TEXT);
        $mform->addHelpButton('bgcolor', 'bgcolorpagehelp', 'icontent');
        $mform->setDefault('bgcolor', $page->bgcolor);

        $borderattributes = ['id' => 'icontent_bordercolor_picker', 'size' => '10', 'maxlength' => '7'];
        $mform->addElement('text', 'bordercolor', get_string('bordercolor', 'icontent'), $borderattributes);
        $mform->setType('bordercolor', PARAM_TEXT);
        $mform->addHelpButton('bordercolor', 'bgcolorpagehelp', 'icontent');
        $mform->setDefault('bordercolor', $page->bordercolor);

        $mform->addElement('html', "
            <script>
                (function() {
                    var normalizeHex = function(value, fallback) {
                        var raw = (value || '').toString().trim();
                        if (raw.charAt(0) !== '#') {
                            raw = '#' + raw;
                        }
                        if (!/^#[0-9a-fA-F]{6}$/.test(raw)) {
                            return fallback;
                        }
                        return raw.toUpperCase();
                    };

                    var initColorInput = function(id, fallback) {
                        var input = document.getElementById(id);
                        if (!input) {
                            return;
                        }
                        input.value = normalizeHex(input.value, fallback);
                        input.type = 'color';
                        input.style.width = '60px';
                        input.style.height = '35px';
                        input.style.cursor = 'pointer';
                        input.addEventListener('change', function() {
                            input.value = normalizeHex(input.value, fallback);
                        });
                    };

                    initColorInput('icontent_bgcolor_picker', '#FCFCFC');
                    initColorInput('icontent_bordercolor_picker', '#E4E4E4');
                    initColorInput('icontent_titlecolor_picker', '#000000');
                })();
            </script>
        ");

        $opts = icontent_add_borderwidth_options();
        $mform->addElement('select', 'borderwidth', get_string('borderwidth', 'icontent'), $opts);
        $mform->setType('borderwidth', PARAM_INT);
        $mform->addHelpButton('borderwidth', 'borderwidthpagehelp', 'icontent');
        // 20240216 Use default in config setting for iContent.
        $mform->setDefault('borderwidth', $icontentconfig->borderwidth);

        $attributes = ['class' => "x-large",
                       'value' => $icontentconfig->maxnotesperpages,
                       'size' => "10",
                      ];
        $mform->setType('maxnotesperpages', PARAM_INT);
        $mform->addElement('text', 'maxnotesperpages', get_string('maxnotesperpages', 'icontent'), $attributes);
        $mform->addHelpButton('maxnotesperpages', 'maxnotesperpageshelp', 'icontent');
        $mform->setDefault('maxnotesperpages', $icontentconfig->maxnotesperpages);
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

        $mform->addElement('header', 'grade', get_string('gradenoun'));
        $mform->addElement(
            'select',
            'attemptsallowed',
            get_string('attemptsallowed', 'icontent'),
            [
                '0' => get_string('unlimited'),
                '1' => '1 ' . get_string('attempt', 'mod_icontent'),
            ]
        );
        $mform->addHelpButton('attemptsallowed', 'attemptsallowedhelp', 'icontent');

        $mform->addElement('header', 'effects', get_string('effects', 'icontent'));
        $effects = [
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
        ];

        $mform->addElement('select', 'transitioneffect', get_string('transitioneffect', 'icontent'), $effects);
        $mform->addHelpButton('transitioneffect', 'transitioneffecthelp', 'icontent');
        $mform->setDefault('transitioneffect', 0);

        $this->add_action_buttons(true);

        // Set the defaults.
        $this->set_data($page);
    }

    /**
     * Format a color for color picker fields as #RRGGBB.
     *
     * @param string|null $value
     * @param string $fallback
     * @return string
     */
    protected static function format_colour_for_picker($value, $fallback) {
        $default = strtoupper((string)$fallback);
        if ($default === '' || $default[0] !== '#') {
            $default = '#' . ltrim($default, '#');
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $default)) {
            $default = '#FCFCFC';
        }

        $raw = '#' . strtoupper(ltrim(trim((string)$value), '#'));
        if (!preg_match('/^#[0-9A-F]{6}$/', $raw)) {
            return $default;
        }
        return $raw;
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $branchparentpageid = (int)($data['branchparentpageid'] ?? 0);
        $currentpageid = (int)($data['id'] ?? 0);

        if ($branchparentpageid > 0 && $branchparentpageid === $currentpageid) {
            $errors['branchparentpageid'] = get_string('errorclusterparentself', 'icontent');
            return $errors;
        }

        if ($branchparentpageid > 0) {
            $validparent = $DB->record_exists(
                'icontent_pages',
                [
                    'id' => $branchparentpageid,
                    'icontentid' => (int)($data['icontentid'] ?? 0),
                    'cmid' => (int)($data['cmid'] ?? 0),
                    'hidden' => 0,
                    'branchparentpageid' => 0,
                ]
            );
            if (!$validparent) {
                $errors['branchparentpageid'] = get_string('errorclusterparentinvalid', 'icontent');
            }
        }

        $prevmode = (int)($data['prevmode'] ?? 0);
        $nextmode = (int)($data['nextmode'] ?? 0);
        $prevpageid = (int)($data['prevpageid'] ?? 0);
        $nextpageid = (int)($data['nextpageid'] ?? 0);
        $allowedmodes = [0, 1, 2];

        if (!in_array($prevmode, $allowedmodes, true)) {
            $errors['prevmode'] = get_string('errorinvalidnavigationmode', 'icontent');
        }
        if (!in_array($nextmode, $allowedmodes, true)) {
            $errors['nextmode'] = get_string('errorinvalidnavigationmode', 'icontent');
        }

        if ($prevmode === 2) {
            if (empty($prevpageid)) {
                $errors['prevpageid'] = get_string('errorcustompreviousrequired', 'icontent');
            } else if ($prevpageid === $currentpageid) {
                $errors['prevpageid'] = get_string('errorcustomnavself', 'icontent');
            } else if (!$DB->record_exists('icontent_pages', [
                'id' => $prevpageid,
                'icontentid' => (int)($data['icontentid'] ?? 0),
                'cmid' => (int)($data['cmid'] ?? 0),
                'hidden' => 0,
            ])) {
                $errors['prevpageid'] = get_string('errorcustomnavinvalid', 'icontent');
            }
        }

        if ($nextmode === 2) {
            if (empty($nextpageid)) {
                $errors['nextpageid'] = get_string('errorcustomnextrequired', 'icontent');
            } else if ($nextpageid === $currentpageid) {
                $errors['nextpageid'] = get_string('errorcustomnavself', 'icontent');
            } else if (!$DB->record_exists('icontent_pages', [
                'id' => $nextpageid,
                'icontentid' => (int)($data['icontentid'] ?? 0),
                'cmid' => (int)($data['cmid'] ?? 0),
                'hidden' => 0,
            ])) {
                $errors['nextpageid'] = get_string('errorcustomnavinvalid', 'icontent');
            }
        }

        return $errors;
    }
}
