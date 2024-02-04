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
 * The mod_icontent question_toevaluate created event.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Santos <leorenis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_icontent\event;

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * The mod_icontent question_toevaluate created event class.
 *
 * @package    mod_icontent
 * @since      Moodle 3.0
 * @copyright  2016 Leo Santos <leorenis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_toevaluate_created extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventquestiontoevaluatecreated', 'mod_icontent');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $useridevaluated = $this->other['useridevaluated'];
        return "The user with id '$this->userid' evaluated manual question(s) answered by the participant with id ".
            "'$useridevaluated' in activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/icontent/view.php',
            [
                'id' => $this->contextinstanceid,
            ]
        );
    }

    /**
     * Create instance of event.
     *
     * @since Moodle 3.0
     *
     * @param stdClass $icontent
     * @param \context_module $context
     * @param stdClass $user
     * @return question_toevaluate_created
     */
    public static function create_from_question_toevaluate($icontent, \context_module $context, $user) {
        $data = [
            'context' => $context,
            'other' => ['useridevaluated' => $user->id],
        ];
        /** @var question_toevaluate_created $event */
        $event = self::create($data);
        $event->add_record_snapshot('icontent', $icontent);
        return $event;
    }
}
