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
 * Internal library of functions for sumtrain module.
 *
 * All the sumtrain specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_sumtrain
 * @copyright 2016 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This creates new calendar events given as timeopen and timeclose by $sumtrain.
 *
 * @param stdClass $sumtrain
 * @return void
 */
function sumtrain_set_events($sumtrain) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Get CMID if not sent as part of $sumtrain.
    if (!isset($sumtrain->coursemodule)) {
        $cm = get_coursemodule_from_instance('sumtrain', $sumtrain->id, $sumtrain->course);
        $sumtrain->coursemodule = $cm->id;
    }

    // sumtrain start calendar events.
    $event = new stdClass();
    $event->eventtype = sumtrain_EVENT_TYPE_OPEN;
    // The sumtrain_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
    $event->type = empty($sumtrain->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'sumtrain', 'instance' => $sumtrain->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($sumtrain->timeopen)) && ($sumtrain->timeopen > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarstart', 'sumtrain', $sumtrain->name);
            $event->description  = format_module_intro('sumtrain', $sumtrain, $sumtrain->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->timestart    = $sumtrain->timeopen;
            $event->timesort     = $sumtrain->timeopen;
            $event->visible      = instance_is_visible('sumtrain', $sumtrain);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($sumtrain->timeopen)) && ($sumtrain->timeopen > 0)) {
            $event->name         = get_string('calendarstart', 'sumtrain', $sumtrain->name);
            $event->description  = format_module_intro('sumtrain', $sumtrain, $sumtrain->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->courseid     = $sumtrain->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'sumtrain';
            $event->instance     = $sumtrain->id;
            $event->timestart    = $sumtrain->timeopen;
            $event->timesort     = $sumtrain->timeopen;
            $event->visible      = instance_is_visible('sumtrain', $sumtrain);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }

    // sumtrain end calendar events.
    $event = new stdClass();
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->eventtype = sumtrain_EVENT_TYPE_CLOSE;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'sumtrain', 'instance' => $sumtrain->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($sumtrain->timeclose)) && ($sumtrain->timeclose > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarend', 'sumtrain', $sumtrain->name);
            $event->description  = format_module_intro('sumtrain', $sumtrain, $sumtrain->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->timestart    = $sumtrain->timeclose;
            $event->timesort     = $sumtrain->timeclose;
            $event->visible      = instance_is_visible('sumtrain', $sumtrain);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($sumtrain->timeclose)) && ($sumtrain->timeclose > 0)) {
            $event->name         = get_string('calendarend', 'sumtrain', $sumtrain->name);
            $event->description  = format_module_intro('sumtrain', $sumtrain, $sumtrain->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->courseid     = $sumtrain->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'sumtrain';
            $event->instance     = $sumtrain->id;
            $event->timestart    = $sumtrain->timeclose;
            $event->timesort     = $sumtrain->timeclose;
            $event->visible      = instance_is_visible('sumtrain', $sumtrain);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }
}
