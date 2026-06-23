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
 * Duplicate icontent page.
 *
 * @package    mod_icontent
 * @copyright  2026 AL Rachels
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_icontent\local\icontent_info;

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$pageid = required_param('pageid', PARAM_INT); // Source page ID.

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);
$createdpage = icontent_duplicate_page((int)$icontent->id, (int)$cm->id, (int)$pageid, $context);

icontent_info::icontent_preload_pages($icontent);
\mod_icontent\event\page_created::create_from_page($icontent, $context, $createdpage)->trigger();

$url = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => (int)$createdpage->id]);
redirect($url, get_string('msgsucessduplicate', 'mod_icontent'));
