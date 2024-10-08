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
 * Defines the version and other meta-info about the plugin
 *
 * Setting the $plugin->version to 0 prevents the plugin from being installed.
 * See https://docs.moodle.org/dev/version.php for more info.
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos <leorenis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_icontent';
$plugin->version = 2024082703; // The current module version (Date: YYYYMMDDXX).
$plugin->release = '1.0.7.2 (Build: 2024082700)'; // User-friendly version number.
$plugin->requires = 2022041903; // Moodle 4.0.
$plugin->maturity = MATURITY_STABLE;
$plugin->cron = 0; // Period for cron to check this module (secs).
$plugin->supported = [400, 404];
$plugin->dependencies = [];
