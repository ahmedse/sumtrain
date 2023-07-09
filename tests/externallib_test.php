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

namespace mod_sumtrain;

use core_external\external_api;
use externallib_advanced_testcase;
use mod_sumtrain_external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/sumtrain/lib.php');

/**
 * External sumtrain functions unit tests
 *
 * @package    mod_sumtrain
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externallib_test extends externallib_advanced_testcase {

    /**
     * Test get_sumtrain_results
     */
    public function test_get_sumtrain_results() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $params = new \stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params->name = 'First sumtrain Activity';
        $params->showresults = sumtrain_SHOWRESULTS_AFTER_ANSWER;
        $params->publish = 1;
        $params->allowmultiple = 1;
        $params->showunanswered = 1;
        $sumtrain = self::getDataGenerator()->create_module('sumtrain', $params);

        $cm = get_coursemodule_from_id('sumtrain', $sumtrain->cmid);
        $sumtraininstance = sumtrain_get_sumtrain($cm->instance);
        $options = array_keys($sumtraininstance->option);
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Students in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course->id, $studentrole->id);
        self::getDataGenerator()->enrol_user($student2->id,  $course->id, $studentrole->id);

        $this->setUser($student1);
        $myanswer = $options[2];
        sumtrain_user_submit_response($myanswer, $sumtrain, $student1->id, $course, $cm);
        $results = mod_sumtrain_external::get_sumtrain_results($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_results_returns(), $results);

        // Create an array with optionID as Key.
        $resultsarr = array();
        foreach ($results['options'] as $option) {
            $resultsarr[$option['id']] = $option['userresponses'];
        }
        // The stundent1 is the userid who choosed the myanswer(option 3).
        $this->assertEquals($resultsarr[$myanswer][0]['userid'], $student1->id);
        // The stundent2 is the userid who didn't answered yet.
        $this->assertEquals($resultsarr[0][0]['userid'], $student2->id);

        // As Stundent2 we cannot see results (until we answered).
        $this->setUser($student2);
        $results = mod_sumtrain_external::get_sumtrain_results($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_results_returns(), $results);
        // We do not retrieve any response!
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
        }

        $timenow = time();
        // We can see results only after activity close (even if we didn't answered).
        $sumtrain->showresults = sumtrain_SHOWRESULTS_AFTER_CLOSE;
        // Set timeopen and timeclose in the past.
        $sumtrain->timeopen = $timenow - (60 * 60 * 24 * 3);
        $sumtrain->timeclose = $timenow + (60 * 60 * 24 * 2);
        $DB->update_record('sumtrain', $sumtrain);

        $results = mod_sumtrain_external::get_sumtrain_results($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_results_returns(), $results);
        // We do not retrieve any response (activity is still open).
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
        }

        // We close the activity (setting timeclose in the past).
        $sumtrain->timeclose = $timenow - (60 * 60 * 24 * 2);
        $DB->update_record('sumtrain', $sumtrain);
        // Now as Stundent2 we will see results!
        $results = mod_sumtrain_external::get_sumtrain_results($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_results_returns(), $results);
        // Create an array with optionID as Key.
        $resultsarr = array();
        foreach ($results['options'] as $option) {
            $resultsarr[$option['id']] = $option['userresponses'];
        }
        // The stundent1 is the userid who choosed the myanswer(option 3).
        $this->assertEquals($resultsarr[$myanswer][0]['userid'], $student1->id);
        // The stundent2 is the userid who didn't answered yet.
        $this->assertEquals($resultsarr[0][0]['userid'], $student2->id);

        // Do not publish user names!
        $sumtrain->publish = 0;
        $DB->update_record('sumtrain', $sumtrain);
        $results = mod_sumtrain_external::get_sumtrain_results($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_results_returns(), $results);
        // Create an array with optionID as Key.
        $resultsarr = array();
        // Does not show any user response!
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
            $resultsarr[$option['id']] = $option;
        }
        // But we can see totals and percentages.
        $this->assertEquals(1, $resultsarr[$myanswer]['numberofuser']);
    }

    /**
     * Test get_sumtrain_options
     */
    public function test_get_sumtrain_options() {
        global $DB;

        // Warningcodes.
        $notopenyet = 1;
        $previewonly = 2;
        $expired = 3;

        $this->resetAfterTest(true);
        $timenow = time();
        $timeopen = $timenow + (60 * 60 * 24 * 2);
        $timeclose = $timenow + (60 * 60 * 24 * 7);
        $course = self::getDataGenerator()->create_course();
        $possibleoptions = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params = array();
        $params['course'] = $course->id;
        $params['option'] = $possibleoptions;
        $params['name'] = 'First sumtrain Activity';
        $params['showpreview'] = 0;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_sumtrain');
        $sumtrain = $generator->create_instance($params);

        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Students in Course.
        self::getDataGenerator()->enrol_user($student1->id,  $course->id, $studentrole->id);
        $this->setUser($student1);

        $results = mod_sumtrain_external::get_sumtrain_options($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);

        // Here we force timeopen/close in the future.
        $sumtrain->timeopen = $timeopen;
        $sumtrain->timeclose = $timeclose;
        $DB->update_record('sumtrain', $sumtrain);

        $results = mod_sumtrain_external::get_sumtrain_options($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_options_returns(), $results);
        // We should retrieve no options.
        $this->assertCount(0, $results['options']);
        $this->assertEquals($notopenyet, $results['warnings'][0]['warningcode']);

        // Here we see the options because of preview!
        $sumtrain->showpreview = 1;
        $DB->update_record('sumtrain', $sumtrain);
        $results = mod_sumtrain_external::get_sumtrain_options($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);

        foreach ($results['options'] as $option) {
            // Each option is disabled as this is only the preview!
            $this->assertEquals(1, $option['disabled']);
        }
        $warnings = array();
        foreach ($results['warnings'] as $warning) {
            $warnings[$warning['warningcode']] = $warning['message'];
        }
        $this->assertTrue(isset($warnings[$previewonly]));
        $this->assertTrue(isset($warnings[$notopenyet]));

        // Simulate activity as opened!
        $sumtrain->timeopen = $timenow - (60 * 60 * 24 * 3);
        $sumtrain->timeclose = $timenow + (60 * 60 * 24 * 2);
        $DB->update_record('sumtrain', $sumtrain);
        $cm = get_coursemodule_from_id('sumtrain', $sumtrain->cmid);
        $sumtraininstance = sumtrain_get_sumtrain($cm->instance);
        $optionsids = array_keys($sumtraininstance->option);
        $myanswerid = $optionsids[2];
        sumtrain_user_submit_response($myanswerid, $sumtrain, $student1->id, $course, $cm);

        $results = mod_sumtrain_external::get_sumtrain_options($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);
        foreach ($results['options'] as $option) {
            // When we answered and we cannot update our sumtrain.
            if ($option['id'] == $myanswerid and !$sumtrain->allowupdate) {
                $this->assertEquals(1, $option['disabled']);
                $this->assertEquals(1, $option['checked']);
            } else {
                $this->assertEquals(0, $option['disabled']);
            }
        }

        // Set timeopen and timeclose as older than today!
        // We simulate what happens when the activity is closed.
        $sumtrain->timeopen = $timenow - (60 * 60 * 24 * 3);
        $sumtrain->timeclose = $timenow - (60 * 60 * 24 * 2);
        $DB->update_record('sumtrain', $sumtrain);
        $results = mod_sumtrain_external::get_sumtrain_options($sumtrain->id);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrain_options_returns(), $results);
        // We should retrieve no options.
        $this->assertCount(0, $results['options']);
        $this->assertEquals($expired, $results['warnings'][0]['warningcode']);

    }

    /**
     * Test submit_sumtrain_response
     */
    public function test_submit_sumtrain_response() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $params = new \stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params->name = 'First sumtrain Activity';
        $params->showresults = sumtrain_SHOWRESULTS_ALWAYS;
        $params->allowmultiple = 1;
        $params->showunanswered = 1;
        $sumtrain = self::getDataGenerator()->create_module('sumtrain', $params);
        $cm = get_coursemodule_from_id('sumtrain', $sumtrain->cmid);
        $sumtraininstance = sumtrain_get_sumtrain($cm->instance);
        $options = array_keys($sumtraininstance->option);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Students in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course->id, $studentrole->id);

        $this->setUser($student1);
        $myresponse = $options[2];
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($myresponse));
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);
        $myanswers = $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $student1->id));
        $myanswer = reset($myanswers);
        $this->assertEquals($results['answers'][0]['id'], $myanswer->id);
        $this->assertEquals($results['answers'][0]['sumtrainid'], $myanswer->sumtrainid);
        $this->assertEquals($results['answers'][0]['userid'], $myanswer->userid);
        $this->assertEquals($results['answers'][0]['timemodified'], $myanswer->timemodified);
    }

    /**
     * Test view_sumtrain
     */
    public function test_view_sumtrain() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $sumtrain = $this->getDataGenerator()->create_module('sumtrain', array('course' => $course->id));
        $context = \context_module::instance($sumtrain->cmid);
        $cm = get_coursemodule_from_instance('sumtrain', $sumtrain->id);

        // Test invalid instance id.
        try {
            mod_sumtrain_external::view_sumtrain(0);
            $this->fail('Exception expected due to invalid mod_sumtrain instance id.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidcoursemodule', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_sumtrain_external::view_sumtrain($sumtrain->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_sumtrain_external::view_sumtrain($sumtrain->id);
        $result = external_api::clean_returnvalue(mod_sumtrain_external::view_sumtrain_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_sumtrain\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlesumtrain = new \moodle_url('/mod/sumtrain/view.php', array('id' => $cm->id));
        $this->assertEquals($moodlesumtrain, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Test get_sumtrains_by_courses
     */
    public function test_get_sumtrains_by_courses() {
        global $DB;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $sumtrainoptions1 = array(
          'course' => $course1->id,
          'name' => 'First IMSCP'
        );
        $sumtrain1 = self::getDataGenerator()->create_module('sumtrain', $sumtrainoptions1);
        $course2 = self::getDataGenerator()->create_course();

        $sumtrainoptions2 = array(
          'course' => $course2->id,
          'name' => 'Second IMSCP'
        );
        $sumtrain2 = self::getDataGenerator()->create_module('sumtrain', $sumtrainoptions2);
        $student1 = $this->getDataGenerator()->create_user();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);

        $this->setUser($student1);
        $sumtrains = mod_sumtrain_external::get_sumtrains_by_courses(array());
        $sumtrains = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrains_by_courses_returns(), $sumtrains);
        $this->assertCount(1, $sumtrains['sumtrains']);
        $this->assertEquals('First IMSCP', $sumtrains['sumtrains'][0]['name']);
        // As Student you cannot see some IMSCP properties like 'section'.
        $this->assertFalse(isset($sumtrains['sumtrains'][0]['section']));

        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $sumtrains = mod_sumtrain_external::get_sumtrains_by_courses(array($course2->id));
        $sumtrains = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrains_by_courses_returns(), $sumtrains);
        $this->assertCount(0, $sumtrains['sumtrains']);
        $this->assertEquals(1, $sumtrains['warnings'][0]['warningcode']);

        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this IMSCP.
        $sumtrains = mod_sumtrain_external::get_sumtrains_by_courses(array($course2->id));
        $sumtrains = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrains_by_courses_returns(), $sumtrains);
        $this->assertCount(1, $sumtrains['sumtrains']);
        $this->assertEquals('Second IMSCP', $sumtrains['sumtrains'][0]['name']);
        // As an Admin you can see some IMSCP properties like 'section'.
        $this->assertEquals(0, $sumtrains['sumtrains'][0]['section']);

        // Now, prohibit capabilities.
        $this->setUser($student1);
        $contextcourse1 = \context_course::instance($course1->id);
        // Prohibit capability = mod:sumtrain:choose on Course1 for students.
        assign_capability('mod/sumtrain:choose', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();

        $sumtrains = mod_sumtrain_external::get_sumtrains_by_courses(array($course1->id));
        $sumtrains = external_api::clean_returnvalue(mod_sumtrain_external::get_sumtrains_by_courses_returns(), $sumtrains);
        $this->assertFalse(isset($sumtrains['sumtrains'][0]['timeopen']));
    }

    /**
     * Test delete_sumtrain_responses
     */
    public function test_delete_sumtrain_responses() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $params = new \stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params->name = 'First sumtrain Activity';
        $params->showresults = sumtrain_SHOWRESULTS_ALWAYS;
        $params->allowmultiple = 1;
        $params->showunanswered = 1;
        $sumtrain = self::getDataGenerator()->create_module('sumtrain', $params);
        $cm = get_coursemodule_from_id('sumtrain', $sumtrain->cmid);

        $sumtraininstance = sumtrain_get_sumtrain($cm->instance);
        $options = array_keys($sumtraininstance->option);

        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll student in Course1.
        self::getDataGenerator()->enrol_user($student->id,  $course->id, $studentrole->id);

        $this->setUser($student);
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);

        $myresponses = array_keys(sumtrain_get_my_response($sumtrain));

        // Try to delete responses when allow update is false.
        try {
            mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($myresponses[0], $myresponses[0]));
            $this->fail('Exception expected due to missing permissions.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Set allow update to true, and a passed time close.
        $DB->set_field('sumtrain', 'allowupdate', 1, array('id' => $sumtrain->id));
        $DB->set_field('sumtrain', 'timeclose', time() - DAYSECS, array('id' => $sumtrain->id));
        try {
            mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($myresponses[0], $myresponses[1]));
            $this->fail('Exception expected due to expired sumtrain.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('expired', $e->errorcode);
        }

        // Reset time close. We should be able now to delete all the responses.
        $DB->set_field('sumtrain', 'timeclose', 0, array('id' => $sumtrain->id));
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($myresponses[0], $myresponses[1]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);

        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);
        // Now, in the DB 0 responses.
        $this->assertCount(0, sumtrain_get_my_response($sumtrain));

        // Submit again the responses.
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);

        $myresponses = array_keys(sumtrain_get_my_response($sumtrain));
        // Delete only one response.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($myresponses[0]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);
        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);
        // Now, in the DB 1 response still.
        $this->assertCount(1, sumtrain_get_my_response($sumtrain));

        // Delete the remaining response, passing 2 invalid responses ids.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($myresponses[1], $myresponses[0] + 2,
                                                                $myresponses[0] + 3));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);
        $this->assertTrue($results['status']);
        // 2 warnings, 2 invalid responses.
        $this->assertCount(2, $results['warnings']);
        // Now, in the DB 0 responses.
        $this->assertCount(0, sumtrain_get_my_response($sumtrain));

        // Now, as an admin we must be able to delete all the responses under any condition.
        $this->setUser($student);
        // Submit again the responses.
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);
        $studentresponses = array_keys(sumtrain_get_my_response($sumtrain));

        $this->setAdminUser();
        $DB->set_field('sumtrain', 'allowupdate', 0, array('id' => $sumtrain->id));
        $DB->set_field('sumtrain', 'timeclose', time() - DAYSECS, array('id' => $sumtrain->id));

        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array($studentresponses[0], $studentresponses[1]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);

        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);

        // Submit again the responses.
        $this->setUser($student);
        $DB->set_field('sumtrain', 'timeclose', 0, array('id' => $sumtrain->id));
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);

        // Test admin try to delete his own responses (he didn't respond so nothing should be deleted).
        $this->setAdminUser();
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id);
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);
        $this->assertFalse($results['status']);
        $this->assertCount(0, $results['warnings']);
        $allresponses = sumtrain_get_all_responses($sumtrain);
        $this->assertCount(2, $allresponses);   // No responses deleted (admin didn't submit any).

        // Now admin submit a couple of responses more.
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);
        $allresponses = sumtrain_get_all_responses($sumtrain);
        $this->assertCount(4, $allresponses);
        // Admin responses are deleted when passing an empty array.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id);
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);
        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);
        $allresponses = sumtrain_get_all_responses($sumtrain);
        $this->assertCount(2, $allresponses);

        // Now admin will delete all the other users responses.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array_keys($allresponses));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);

        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);
        $allresponses = sumtrain_get_all_responses($sumtrain);
        $this->assertCount(0, $allresponses);   // Now all the responses were deleted.

        // Admin try do delete an invalid response.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id, array(-1));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);

        $this->assertFalse($results['status']);
        $this->assertCount(1, $results['warnings']);

        // Now, in the DB 0 responses.
        $this->setUser($student);

        // Submit again respones.
        $DB->set_field('sumtrain', 'allowupdate', 1, array('id' => $sumtrain->id));
        $DB->set_field('sumtrain', 'timeclose', 0, array('id' => $sumtrain->id));
        $results = mod_sumtrain_external::submit_sumtrain_response($sumtrain->id, array($options[1], $options[2]));
        $results = external_api::clean_returnvalue(mod_sumtrain_external::submit_sumtrain_response_returns(), $results);

        // Delete all responses.
        $results = mod_sumtrain_external::delete_sumtrain_responses($sumtrain->id);
        $results = external_api::clean_returnvalue(mod_sumtrain_external::delete_sumtrain_responses_returns(), $results);

        $this->assertTrue($results['status']);
        $this->assertCount(0, $results['warnings']);
        $this->assertCount(0, sumtrain_get_my_response($sumtrain));

    }
}
