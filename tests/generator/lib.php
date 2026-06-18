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
 * mod_icontent data generator.
 *
 * @package   mod_icontent
 * @category  test
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * iContent module data generator class.
 *
 * @package   mod_icontent
 * @category  test
 */
class mod_icontent_generator extends testing_module_generator {
    /**
     * @var int Keep track of created iContent instances.
     */
    protected $icontentcount = 0;

    /**
     * Reset internal counters.
     *
     * @return void
     */
    public function reset() {
        $this->icontentcount = 0;
        parent::reset();
    }

    /**
     * Create new iContent module instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test iContent ' . $this->icontentcount;
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test iContent intro ' . $this->icontentcount;
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_HTML;
        }
        if (!isset($record->usepassword)) {
            $record->usepassword = 0;
        }
        if (!isset($record->password)) {
            $record->password = '';
        }
        if (!isset($record->grade)) {
            $record->grade = 100;
        }

        $this->icontentcount++;

        return parent::create_instance($record, (array)$options);
    }
}
