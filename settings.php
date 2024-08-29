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
 * Administration default settings definitions for the iContent module.
 *
 * @package    mod_icontent
 * @copyright  2023 AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die;
use mod_icontent\local\keyboards;

if ($ADMIN->fulltree) {
    // Changed to this format 03/10/2019.
    require_once(__DIR__ . '/lib.php');
    require_once(dirname(__FILE__).'/locallib.php');

    // Availability settings.
    $settings->add(new admin_setting_heading('mod_icontent/availibility', get_string('availability'), ''));

    $name = new lang_string('alwaysshowdescription', 'mod_icontent');
    $description = new lang_string('alwaysshowdescription_help', 'mod_icontent');
    $setting = new admin_setting_configcheckbox('mod_icontent/alwaysshowdescription',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
    
    // 20231230 Recent activity setting.
    $name = new lang_string('showrecentactivity', 'icontent');
    $description = new lang_string('showrecentactivityconfig', 'icontent');
    $settings->add(new admin_setting_configcheckbox('mod_icontent/showrecentactivity',
                                                    $name,
                                                    $description,
                                                    0));

    // Password setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced('mod_icontent/password',
        get_string('password', 'icontent'),
        get_string('configpassword_desc', 'icontent'),
        ['value' => 0,
        'adv' => true, ]
    ));
    
    // Appearance settings.
    $settings->add(new admin_setting_heading('mod_icontent/appearance', get_string('appearance'), ''));

    // Need and appearance setting for background color, border color, border width max number of
    // pages, show notes area, max number of notes per page and the progress bar.

    // 20240205Slide background colour setting.
    $name = 'mod_icontent/bgcolor';
    $title = get_string('bgcolor', 'icontent');
    $description = get_string('bgcolorhelp_help', 'icontent');
    $default = get_string('bgcolor_color', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // 20240205 Slide border colour setting.
    $name = 'mod_icontent/bordercolor';
    $title = get_string('bordercolor', 'icontent');
    $description = get_string('bordercolorhelp_help', 'icontent');
    $default = get_string('bordercolor_color', 'icontent');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // 20240205 iContent border width setting.
    $name = 'mod_icontent/borderwidth';
    $title = get_string('borderwidth', 'icontent');
    $description = get_string('borderwidthhelp_help', 'icontent');
    $default = 10;
    $options = icontent_add_borderwidth_options(); // 20240216 Limit to <= 50px in locallib.php about line 253.
    $settings->add(new admin_setting_configselect($name, $title, $description, $default, $options, 1));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // 20240205 iContent Maximum number of pages.
    $maxpages = [];
    for ($i = 0; $i <= 55; $i++) {
        $maxpages[] = $i;
    }
    $name = 'mod_icontent/maxpages';
    $title = get_string('maxpages', 'icontent');
    $description = get_string('maxpageshelp_help', 'icontent');
    $default = 35;
    $settings->add(new admin_setting_configselect($name, $title, $description, $default, $maxpages));


    // 20210708 iContent Show notes area.
    $name = 'mod_icontent/shownotesarea';
    $title = get_string('shownotesarea', 'icontent');
    $description = get_string('shownotesarea_help', 'icontent');
    $default = 1;
    $settings->add(new admin_setting_configselect($name, $title, $description, $default,
        [
            '0' => get_string('no'),
            '1' => get_string('yes'),
        ]
    ));

    // 20240205 iContent Maximum number of notes per page.
    $maxnotesperpages = [];
    for ($i = 0; $i <= 50; $i++) {
        $maxnotesperpages[] = $i;
    }
    $name = 'mod_icontent/maxnotesperpages';
    $title = get_string('maxnotesperpages', 'icontent');
    $description = get_string('maxnotesperpageshelp_help', 'icontent');
    $default = 50;
    $settings->add(new admin_setting_configselect($name, $title, $description, $default, $maxnotesperpages));


    // 20240205 iContent Show progress bar.
    $name = 'mod_icontent/progressbar';
    $title = get_string('progressbar', 'icontent');
    $description = get_string('progressbar_help', 'icontent');
    $default = 1;
    $settings->add(new admin_setting_configselect($name, $title, $description, $default,
        [
            '0' => get_string('no'),
            '1' => get_string('yes'),
        ]
    ));

    // 20231230 Date format setting.
    $settings->add(new admin_setting_configtext(
        'mod_icontent/dateformat',
        get_string('dateformat', 'icontent'),
        get_string('dateformatconfig', 'icontent'),
        'M d, Y G:i A',
        PARAM_TEXT,
        25)
    );
}
