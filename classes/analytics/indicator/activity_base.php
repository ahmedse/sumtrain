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
 * Activity base class.
 *
 * @package   mod_sumtrain
 * @copyright 2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_sumtrain\analytics\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Activity base class.
 *
 * @package   mod_sumtrain
 * @copyright 2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class activity_base extends \core_analytics\local\indicator\community_of_inquiry_activity {

    /**
     * feedback_viewed_events
     *
     * @return string[]
     */
    protected function feedback_viewed_events() {
        return array('\mod_sumtrain\event\course_module_viewed', '\mod_sumtrain\event\answer_updated');
    }

    /**
     * feedback_viewed
     *
     * @param \cm_info $cm
     * @param int $contextid
     * @param int $userid
     * @param int $after
     * @return bool
     */
    protected function feedback_viewed(\cm_info $cm, $contextid, $userid, $after = null) {

        // If results are shown after they answer a write action counts as feedback viewed.
        if ($this->instancedata[$cm->instance]->showresults == 1) {
            // The user id will be enough for any_write_log.
            $user = (object)['id' => $userid];
            return $this->any_write_log($contextid, $user);
        }

        $after = null;
        if ($this->instancedata[$cm->instance]->timeclose) {
            $after = $this->instancedata[$cm->instance]->timeclose;
        }

        return $this->feedback_post_action($cm, $contextid, $userid, $this->feedback_viewed_events(), $after);
    }

    /**
     * Returns the name of the field that controls activity availability.
     *
     * @return null|string
     */
    protected function get_timeclose_field() {
        return 'timeclose';
    }
}
