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
 * Events tests.
 *
 * @package    mod_sumtrain
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_sumtrain\event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/sumtrain/lib.php');

/**
 * Events tests class.
 *
 * @package    mod_sumtrain
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {
    /** @var sumtrain_object */
    protected $sumtrain;

    /** @var course_object */
    protected $course;

    /** @var cm_object Course module object. */
    protected $cm;

    /** @var context_object */
    protected $context;

    /**
     * Setup often used objects for the following tests.
     */
    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->sumtrain = $this->getDataGenerator()->create_module('sumtrain', array('course' => $this->course->id));
        $this->cm = $DB->get_record('course_modules', array('id' => $this->sumtrain->cmid));
        $this->context = \context_module::instance($this->sumtrain->cmid);
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_answer_created() {
        global $DB;
        // Generate user data.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $optionids = array_keys($DB->get_records('sumtrain_options', array('sumtrainid' => $this->sumtrain->id)));
        // Redirect event.
        $sink = $this->redirectEvents();
        sumtrain_user_submit_response($optionids[3], $this->sumtrain, $user->id, $this->course, $this->cm);
        $events = $sink->get_events();
        $answer = $DB->get_record('sumtrain_answers', ['userid' => $user->id, 'sumtrainid' => $this->sumtrain->id]);

        // Data checking.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_sumtrain\event\answer_created', $events[0]);
        $this->assertEquals($user->id, $events[0]->userid);
        $this->assertEquals($user->id, $events[0]->relateduserid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $events[0]->get_context());
        $this->assertEquals($answer->id, $events[0]->objectid);
        $this->assertEquals($this->sumtrain->id, $events[0]->other['sumtrainid']);
        $this->assertEquals($optionids[3], $events[0]->other['optionid']);
        $this->assertEventContextNotUsed($events[0]);
        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_answer_submitted_by_another_user() {
        global $DB, $USER;
        // Generate user data.
        $user = $this->getDataGenerator()->create_user();

        $optionids = array_keys($DB->get_records('sumtrain_options', array('sumtrainid' => $this->sumtrain->id)));
        // Redirect event.
        $sink = $this->redirectEvents();
        sumtrain_user_submit_response($optionids[3], $this->sumtrain, $user->id, $this->course, $this->cm);
        $events = $sink->get_events();
        $answer = $DB->get_record('sumtrain_answers', ['userid' => $user->id, 'sumtrainid' => $this->sumtrain->id]);

        // Data checking.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_sumtrain\event\answer_created', $events[0]);
        $this->assertEquals($USER->id, $events[0]->userid);
        $this->assertEquals($user->id, $events[0]->relateduserid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $events[0]->get_context());
        $this->assertEquals($answer->id, $events[0]->objectid);
        $this->assertEquals($this->sumtrain->id, $events[0]->other['sumtrainid']);
        $this->assertEquals($optionids[3], $events[0]->other['optionid']);
        $this->assertEventContextNotUsed($events[0]);
        $sink->close();
    }

    /**
     * Test to ensure that multiple sumtrain data is being stored correctly.
     */
    public function test_answer_created_multiple() {
        global $DB;

        // Generate user data.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create multiple sumtrain.
        $sumtrain = $this->getDataGenerator()->create_module('sumtrain', array('course' => $this->course->id,
            'allowmultiple' => 1));
        $cm = $DB->get_record('course_modules', array('id' => $sumtrain->cmid));
        $context = \context_module::instance($sumtrain->cmid);

        $optionids = array_keys($DB->get_records('sumtrain_options', array('sumtrainid' => $sumtrain->id)));
        $submittedoptionids = array($optionids[1], $optionids[3]);

        // Redirect event.
        $sink = $this->redirectEvents();
        sumtrain_user_submit_response($submittedoptionids, $sumtrain, $user->id, $this->course, $cm);
        $events = $sink->get_events();
        $answers = $DB->get_records('sumtrain_answers', ['userid' => $user->id, 'sumtrainid' => $sumtrain->id], 'id');
        $answers = array_values($answers);

        // Data checking.
        $this->assertCount(2, $events);
        $this->assertInstanceOf('\mod_sumtrain\event\answer_created', $events[0]);
        $this->assertEquals($user->id, $events[0]->userid);
        $this->assertEquals($user->id, $events[0]->relateduserid);
        $this->assertEquals(\context_module::instance($sumtrain->cmid), $events[0]->get_context());
        $this->assertEquals($answers[0]->id, $events[0]->objectid);
        $this->assertEquals($sumtrain->id, $events[0]->other['sumtrainid']);
        $this->assertEquals($optionids[1], $events[0]->other['optionid']);
        $this->assertEventContextNotUsed($events[0]);

        $this->assertInstanceOf('\mod_sumtrain\event\answer_created', $events[1]);
        $this->assertEquals($user->id, $events[1]->userid);
        $this->assertEquals($user->id, $events[1]->relateduserid);
        $this->assertEquals(\context_module::instance($sumtrain->cmid), $events[1]->get_context());
        $this->assertEquals($answers[1]->id, $events[1]->objectid);
        $this->assertEquals($sumtrain->id, $events[1]->other['sumtrainid']);
        $this->assertEquals($optionids[3], $events[1]->other['optionid']);
        $this->assertEventContextNotUsed($events[1]);
        $sink->close();
    }

    /**
     * Test custom validations.
     */
    public function test_answer_created_other_exception() {
        // Generate user data.
        $user = $this->getDataGenerator()->create_user();

        $eventdata = array();
        $eventdata['context'] = $this->context;
        $eventdata['objectid'] = 2;
        $eventdata['userid'] = $user->id;
        $eventdata['courseid'] = $this->course->id;
        $eventdata['other'] = array();

        // Make sure content identifier is always set.
        $this->expectException(\coding_exception::class);
        $event = \mod_sumtrain\event\answer_created::create($eventdata);
        $event->trigger();
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_answer_updated() {
        global $DB;
        // Generate user data.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $optionids = array_keys($DB->get_records('sumtrain_options', array('sumtrainid' => $this->sumtrain->id)));

        // Create the first answer.
        sumtrain_user_submit_response($optionids[2], $this->sumtrain, $user->id, $this->course, $this->cm);
        $oldanswer = $DB->get_record('sumtrain_answers', ['userid' => $user->id, 'sumtrainid' => $this->sumtrain->id]);

        // Redirect event.
        $sink = $this->redirectEvents();
        // Now choose a different answer.
        sumtrain_user_submit_response($optionids[3], $this->sumtrain, $user->id, $this->course, $this->cm);
        $newanswer = $DB->get_record('sumtrain_answers', ['userid' => $user->id, 'sumtrainid' => $this->sumtrain->id]);

        $events = $sink->get_events();

        // Data checking.
        $this->assertCount(2, $events);
        $this->assertInstanceOf('\mod_sumtrain\event\answer_deleted', $events[0]);
        $this->assertEquals($user->id, $events[0]->userid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $events[0]->get_context());
        $this->assertEquals($oldanswer->id, $events[0]->objectid);
        $this->assertEquals($this->sumtrain->id, $events[0]->other['sumtrainid']);
        $this->assertEquals($optionids[2], $events[0]->other['optionid']);
        $this->assertEventContextNotUsed($events[0]);

        $this->assertInstanceOf('\mod_sumtrain\event\answer_created', $events[1]);
        $this->assertEquals($user->id, $events[1]->userid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $events[1]->get_context());
        $this->assertEquals($newanswer->id, $events[1]->objectid);
        $this->assertEquals($this->sumtrain->id, $events[1]->other['sumtrainid']);
        $this->assertEquals($optionids[3], $events[1]->other['optionid']);
        $this->assertEventContextNotUsed($events[1]);

        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_answer_deleted() {
        global $DB, $USER;
        // Generate user data.
        $user = $this->getDataGenerator()->create_user();

        $optionids = array_keys($DB->get_records('sumtrain_options', array('sumtrainid' => $this->sumtrain->id)));

        // Create the first answer.
        sumtrain_user_submit_response($optionids[2], $this->sumtrain, $user->id, $this->course, $this->cm);
        // Get the users response.
        $answer = $DB->get_record('sumtrain_answers', array('userid' => $user->id, 'sumtrainid' => $this->sumtrain->id),
            '*', $strictness = IGNORE_MULTIPLE);

        // Redirect event.
        $sink = $this->redirectEvents();
        // Now delete the answer.
        sumtrain_delete_responses(array($answer->id), $this->sumtrain, $this->cm, $this->course);

        // Get our event event.
        $events = $sink->get_events();
        $event = reset($events);

        // Data checking.
        $this->assertInstanceOf('\mod_sumtrain\event\answer_deleted', $event);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $event->get_context());
        $this->assertEquals($this->sumtrain->id, $event->other['sumtrainid']);
        $this->assertEquals($answer->optionid, $event->other['optionid']);
        $this->assertEventContextNotUsed($event);
        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_report_viewed() {
        global $USER;

        $this->resetAfterTest();

        // Generate user data.
        $this->setAdminUser();

        $eventdata = array();
        $eventdata['objectid'] = $this->sumtrain->id;
        $eventdata['context'] = $this->context;
        $eventdata['courseid'] = $this->course->id;
        $eventdata['other']['content'] = 'sumtrainreportcontentviewed';

        // This is fired in a page view so we can't run this through a function.
        $event = \mod_sumtrain\event\report_viewed::create($eventdata);

        // Redirect event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $event = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $event);
        $this->assertInstanceOf('\mod_sumtrain\event\report_viewed', $event[0]);
        $this->assertEquals($USER->id, $event[0]->userid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $event[0]->get_context());
        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_report_downloaded() {
        global $USER;

        $this->resetAfterTest();

        // Generate user data.
        $this->setAdminUser();

        $eventdata = array();
        $eventdata['context'] = $this->context;
        $eventdata['courseid'] = $this->course->id;
        $eventdata['other']['content'] = 'sumtrainreportcontentviewed';
        $eventdata['other']['format'] = 'csv';
        $eventdata['other']['sumtrainid'] = $this->sumtrain->id;

        // This is fired in a page view so we can't run this through a function.
        $event = \mod_sumtrain\event\report_downloaded::create($eventdata);

        // Redirect event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $event = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $event);
        $this->assertInstanceOf('\mod_sumtrain\event\report_downloaded', $event[0]);
        $this->assertEquals($USER->id, $event[0]->userid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $event[0]->get_context());
        $this->assertEquals('csv', $event[0]->other['format']);
        $this->assertEquals($this->sumtrain->id, $event[0]->other['sumtrainid']);
        $this->assertEventContextNotUsed($event[0]);
        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_course_module_viewed() {
        global $USER;

        // Generate user data.
        $this->setAdminUser();

        $eventdata = array();
        $eventdata['objectid'] = $this->sumtrain->id;
        $eventdata['context'] = $this->context;
        $eventdata['courseid'] = $this->course->id;
        $eventdata['other']['content'] = 'pageresourceview';

        // This is fired in a page view so we can't run this through a function.
        $event = \mod_sumtrain\event\course_module_viewed::create($eventdata);

        // Redirect event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $event = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $event);
        $this->assertInstanceOf('\mod_sumtrain\event\course_module_viewed', $event[0]);
        $this->assertEquals($USER->id, $event[0]->userid);
        $this->assertEquals(\context_module::instance($this->sumtrain->cmid), $event[0]->get_context());
        $sink->close();
    }

    /**
     * Test to ensure that event data is being stored correctly.
     */
    public function test_course_module_instance_list_viewed_viewed() {
        global $USER;

        // Not much can be tested here as the event is only triggered on a page load,
        // let's just check that the event contains the expected basic information.
        $this->setAdminUser();

        $params = array('context' => \context_course::instance($this->course->id));
        $event = \mod_sumtrain\event\course_module_instance_list_viewed::create($params);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\mod_sumtrain\event\course_module_instance_list_viewed', $event);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals(\context_course::instance($this->course->id), $event->get_context());
    }
}
