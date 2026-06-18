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

namespace mod_icontent\courseformat;

use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\url;
use core_courseformat\local\overview\overviewitem;

/**
 * iContent overview integration (for Moodle 5.1+).
 *
 * @package   mod_icontent
 * @copyright 2026 AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        $url = new url('/mod/icontent/view.php', ['id' => $this->cm->id]);
        $text = get_string('view');

        if (defined('button::BODY_OUTLINE')) {
            $buttonclass = button::BODY_OUTLINE->classes();
        } else {
            $buttonclass = 'btn btn-outline-secondary';
        }

        $content = new action_link($url, $text, null, ['class' => $buttonclass]);
        return new overviewitem(get_string('actions'), $text, $content, text_align::CENTER);
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'pages' => $this->get_extra_pages_overview(),
        ];
    }

    /**
     * Retrieves a pages count overview item.
     *
     * @return overviewitem
     */
    private function get_extra_pages_overview(): overviewitem {
        global $DB;

        $totalpages = (int) $DB->count_records('icontent_pages', ['icontentid' => $this->cm->instance]);

        return new overviewitem(
            name: get_string('pages', 'mod_icontent'),
            value: $totalpages,
            content: $totalpages,
            textalign: text_align::CENTER,
        );
    }
}
