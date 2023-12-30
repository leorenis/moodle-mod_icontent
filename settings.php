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
 * Administration settings definitions for the iContent module.
 *
 * @package    mod_icontent
 * @copyright  2023 AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die;
use mod_icontent\local\keyboards;

if ($ADMIN->fulltree) {
    // Changed to this newer format 03/10/2019.
    require_once(__DIR__ . '/lib.php');

    // Availability settings.
    $settings->add(new admin_setting_heading('mod_icontent/availibility', get_string('availability'), ''));

    // Recent activity setting.
    $name = new lang_string('showrecentactivity', 'icontent');
    $description = new lang_string('showrecentactivityconfig', 'icontent');
    $settings->add(new admin_setting_configcheckbox('mod_icontent/showrecentactivity',
                                                    $name,
                                                    $description,
                                                    0));

    // Options settings.
    //$settings->add(new admin_setting_heading('mod_icontent/options', get_string('options', 'icontent'), ''));




    // Appearance settings.
    $settings->add(new admin_setting_heading('mod_icontent/appearance', get_string('appearance'), ''));

// Need and appearance setting for background color, border color, border width max number of pages, show notes area, max number of notes per page and the progress bar.

    // Date format setting.
    $settings->add(new admin_setting_configtext(
        'mod_icontent/dateformat',
        get_string('dateformat', 'icontent'),
        get_string('dateformatconfig', 'icontent'),
        'M d, Y G:i A', PARAM_TEXT, 15)
    );

// I have temporarily left these as examples to draw from.
/*
    // Statistics bar colour setting.
    $name = 'mod_icontent/statscolor';
    $title = get_string('statscolor_title', 'icontent');
    $description = get_string('statscolor_descr', 'icontent');
    $default = get_string('statscolor_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Key top text colour setting.
    $name = 'mod_icontent/normalkeytoptextc';
    $title = get_string('normalkeytoptextc_title', 'icontent');
    $description = get_string('normalkeytoptextc_descr', 'icontent');
    $default = get_string('normalkeytoptextc_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Key top colour setting.
    $name = 'mod_icontent/normalkeytops';
    $title = get_string('normalkeytops_title', 'icontent');
    $description = get_string('normalkeytops_descr', 'icontent');
    $default = get_string('normalkeytops_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Keyboard background colour setting.
    $name = 'mod_icontent/keyboardbgc';
    $title = get_string('keyboardbgc_title', 'icontent');
    $description = get_string('keyboardbgc_descr', 'icontent');
    $default = get_string('keyboardbgc_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Cursor colour setting.
    $name = 'mod_icontent/cursorcolor';
    $title = get_string('cursorcolor_title', 'icontent');
    $description = get_string('cursorcolor_descr', 'icontent');
    $default = get_string('cursorcolor_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Text background colour setting.
    $name = 'mod_icontent/textbgc';
    $title = get_string('textbgc_title', 'icontent');
    $description = get_string('textbgc_descr', 'icontent');
    $default = get_string('textbgc_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Text error background colour setting.
    $name = 'mod_icontent/texterrorcolor';
    $title = get_string('texterrorcolor_title', 'icontent');
    $description = get_string('texterrorcolor_descr', 'icontent');
    $default = get_string('texterrorcolor_colour', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);
*/
}
