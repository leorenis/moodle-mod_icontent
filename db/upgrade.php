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
 * This file keeps track of upgrades to the icontent module.
 *
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @copyright  2024 onwards AL Rachels drachels@drachels.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // phpcs:ignore

/**
 * Execute icontent upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_icontent_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2007040100) {
        // Define field course to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('course', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'id');

        // Add field course.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field intro to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'name');

        // Add field intro.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field introformat to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field(
            'introformat',
            XMLDB_TYPE_INTEGER,
            '4',
            XMLDB_UNSIGNED,
            XMLDB_NOTNULL,
            null,
            '0',
            'intro'
        );

        // Add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Once we reach this point, we can store the new version and consider the module
        // ... upgraded to the version 2007040100 so the next time this block is skipped.
        upgrade_mod_savepoint(true, 2007040100, 'icontent');
    }

    if ($oldversion < 2007040101) {
        // Define field timecreated to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field(
            'timecreated',
            XMLDB_TYPE_INTEGER,
            '10',
            XMLDB_UNSIGNED,
            XMLDB_NOTNULL,
            null,
            '0',
            'introformat'
        );

        // Add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timemodified to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field(
            'timemodified',
            XMLDB_TYPE_INTEGER,
            '10',
            XMLDB_UNSIGNED,
            XMLDB_NOTNULL,
            null,
            '0',
            'timecreated'
        );

        // Add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index course (not unique) to be added to icontent.
        $table = new xmldb_table('icontent');
        $index = new xmldb_index('courseindex', XMLDB_INDEX_NOTUNIQUE, ['course']);

        // Add index to course field.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Another save point reached.
        upgrade_mod_savepoint(true, 2007040101, 'icontent');
    }

    // Third example, the next day, 2007/04/02 (with the trailing 00),
    // some actions were performed to install.php related with the module.
    // 1.0.7.2 Adding five new fields.
    if ($oldversion < 2024082700) {
        // Define field usepassword to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('usepassword', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field usepassword.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field password to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('password', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'usepassword');

        // Conditionally launch add field password.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeopen to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field timeopen.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeclose to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');

        // Conditionally launch add field timeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field viewaftertimeclose to be added to icontent.
        $table = new xmldb_table('icontent');
        $field = new xmldb_field('viewaftertimeclose', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timeclose');

        // Conditionally launch add field viewaftertimeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2024082700, 'icontent');
    }

    if ($oldversion < 2026030200) {
        $table = new xmldb_table('icontent_question_attempts');

        $field = new xmldb_field('reviewercomment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'answertext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'reviewercommentformat',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'reviewercomment'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026030200, 'icontent');
    }

    if ($oldversion < 2026031808) {
        $table = new xmldb_table('icontent_pages');
        $field = new xmldb_field('titlecolor', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'showtitle');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026031808, 'icontent');
    }

    if ($oldversion < 2026032700) {
        // Remove the questionenginephase1 config setting since Question Engine
        // is now always enabled and is the only supported implementation.
        unset_config('questionenginephase1', 'mod_icontent');

        upgrade_mod_savepoint(true, 2026032700, 'icontent');
    }

    if ($oldversion < 2026032801) {
        $table = new xmldb_table('icontent_pages');

        $field = new xmldb_field('branchref', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'title');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('branchname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'title');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('branchparentpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'branchname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('icontent_pages_questions');

        $field = new xmldb_field('correctnextpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'qtype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('incorrectnextpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'correctnextpageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('manualreviewnextpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'incorrectnextpageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('defaultnextpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'manualreviewnextpageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('icontent_pages_nav');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('frompageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('topageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('cmiduseridtime', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid', 'timecreated']);
            $table->add_index('topageid', XMLDB_INDEX_NOTUNIQUE, ['topageid']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026032801, 'icontent');
    }

    if ($oldversion < 2026052403) {
        // No schema changes in this step; keep forward-only version progression.
        upgrade_mod_savepoint(true, 2026052403, 'icontent');
    }

    if ($oldversion < 2026052700) {
        $table = new xmldb_table('icontent_pages');

        // Rename legacy cluster fields to branch terminology.
        $oldfield = new xmldb_field('clusterref', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'title');
        $newfield = new xmldb_field('branchref', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'title');
        if ($dbman->field_exists($table, $oldfield) && !$dbman->field_exists($table, $newfield)) {
            $dbman->rename_field($table, $oldfield, 'branchref');
        } else if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }

        $oldfield = new xmldb_field('clustername', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'branchref');
        $newfield = new xmldb_field('branchname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'branchref');
        if ($dbman->field_exists($table, $oldfield) && !$dbman->field_exists($table, $newfield)) {
            $dbman->rename_field($table, $oldfield, 'branchname');
        } else if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }

        $oldfield = new xmldb_field('clusterparentpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'branchname');
        $newfield = new xmldb_field('branchparentpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'branchname');
        if ($dbman->field_exists($table, $oldfield) && !$dbman->field_exists($table, $newfield)) {
            $dbman->rename_field($table, $oldfield, 'branchparentpageid');
        } else if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }

        $field = new xmldb_field('prevmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'attemptsallowed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('prevpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'prevmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('nextmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'prevpageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('nextpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'nextmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026052700, 'icontent');
    }

    if ($oldversion < 2026052701) {
        $table = new xmldb_table('icontent');

        $field = new xmldb_field('showtocmenu', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'progressbar');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026052701, 'icontent');
    }

    if ($oldversion < 2026060201) {
        $table = new xmldb_table('icontent_question_attempts');

        // Store the Moodle text format for answertext so essay HTML can be rendered correctly.
        $field = new xmldb_field(
            'answertextformat',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'answertext'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026060201, 'icontent');
    }

    return true;
}
