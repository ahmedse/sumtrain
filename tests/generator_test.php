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

/**
 * Generator tests class.
 *
 * @package    mod_sumtrain
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends \advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('sumtrain', array('course' => $course->id)));
        $sumtrain = $this->getDataGenerator()->create_module('sumtrain', array('course' => $course->id));
        $this->assertEquals(1, $DB->count_records('sumtrain', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('sumtrain', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('sumtrain', array('id' => $sumtrain->id)));

        $params = array('course' => $course->id, 'name' => 'One more sumtrain');
        $sumtrain = $this->getDataGenerator()->create_module('sumtrain', $params);
        $this->assertEquals(2, $DB->count_records('sumtrain', array('course' => $course->id)));
        $this->assertEquals('One more sumtrain', $DB->get_field_select('sumtrain', 'name', 'id = :id', array('id' => $sumtrain->id)));

        $params = new \stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $sumtrain = $this->getDataGenerator()->create_module('sumtrain', $params);
        $this->assertEquals(5, $DB->count_records('sumtrain_options', array('sumtrainid' => $sumtrain->id)));
    }
}
