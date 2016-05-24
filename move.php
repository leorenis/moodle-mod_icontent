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
 * Move icontent page
 *
 * @package    mod_icontent
 * @copyright  2015-2016 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id        = required_param('id', PARAM_INT);        // Course Module ID
$pageid = required_param('pageid', PARAM_INT); // page ID
$up        = optional_param('up', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

$page = $DB->get_record('icontent_pages', array('id'=>$pageid, 'icontentid'=>$icontent->id), '*', MUST_EXIST);


$oldpages = $DB->get_records('icontent_pages', array('icontentid'=>$icontent->id), 'pagenum', 'id, pagenum');

$nothing = 0;

$pages = array();
$pgs = 0;
$pge = 0;
$ts = 0;
$te = 0;
// create new ordered array and find pages to be moved
$i = 1;
$found = 0;
foreach ($oldpages as $pg) {
    $pages[$i] = $pg;
    if ($page->id == $pg->id) {
        $pgs = $i;
        $pge = $pgs;
        
    } else if ($pgs) {
        if ($found) {
            // Nothing.
        } else {
            $found = 1;
        }
    }
    $i++;
}

// Find target page(s).
// Moving page and looking for next/previous page.
if ($up) { // Up.
    if ($pgs == 1) {
        $nothing = 1; // Already first.
    } else {
        $te = $pgs - 1;
        for ($i = $pgs-1; $i >= 1; $i--) {
            $ts = $i;
            break;
        }
    }
} else { // Down.
    if ($pge == count($pages)) {
        $nothing = 1; // Already last.
    } else {
        $ts = $pge + 1;
        $found = 0;
        for ($i = $pge+1; $i <= count($pages); $i++) {
            if ($found) {
                break;
            } else {
                $te = $i;
                $found = 1;
            }
        }
    }
}

// Recreated newly sorted list of pages.
if (!$nothing) {
    $newpages = array();

    if ($up) {
        if ($ts > 1) {
            for ($i=1; $i<$ts; $i++) {
                $newpages[] = $pages[$i];
            }
        }
        for ($i=$pgs; $i<=$pge; $i++) {
            $newpages[$i] = $pages[$i];
        }
        for ($i=$ts; $i<=$te; $i++) {
            $newpages[$i] = $pages[$i];
        }
        if ($pge<count($pages)) {
            for ($i=$pge; $i<=count($pages); $i++) {
                $newpages[$i] = $pages[$i];
            }
        }
    } else {
        if ($pgs > 1) {
            for ($i=1; $i<$pgs; $i++) {
                $newpages[] = $pages[$i];
            }
        }
        for ($i=$ts; $i<=$te; $i++) {
            $newpages[$i] = $pages[$i];
        }
        for ($i=$pgs; $i<=$pge; $i++) {
            $newpages[$i] = $pages[$i];
        }
        if ($te<count($pages)) {
            for ($i=$te; $i<=count($pages); $i++) {
                $newpages[$i] = $pages[$i];
            }
        }
    }

    // Store pages in the new order.
    $i = 1;
    foreach ($newpages as $pg) {
        $pg->pagenum = $i;
        $DB->update_record('icontent_pages', $pg);
        $pg = $DB->get_record('icontent_pages', array('id' => $pg->id));

       // \mod_icontent\event\page_updated::create_from_page($icontent, $context, $pg)->trigger();

        $i++;
    }
}

redirect('view.php?id='.$cm->id.'&pageid='.$page->id);

