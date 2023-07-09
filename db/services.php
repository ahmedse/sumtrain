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
 * sumtrain external functions and service definitions.
 *
 * @package    mod_sumtrain
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_sumtrain_get_sumtrain_results' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'get_sumtrain_results',
        'description'   => 'Retrieve users results for a given sumtrain.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sumtrain_get_sumtrain_options' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'get_sumtrain_options',
        'description'   => 'Retrieve options for a specific sumtrain.',
        'type'          => 'read',
        'capabilities'  => 'mod/sumtrain:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sumtrain_submit_sumtrain_response' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'submit_sumtrain_response',
        'description'   => 'Submit responses to a specific sumtrain item.',
        'type'          => 'write',
        'capabilities'  => 'mod/sumtrain:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sumtrain_view_sumtrain' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'view_sumtrain',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sumtrain_get_sumtrains_by_courses' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'get_sumtrains_by_courses',
        'description'   => 'Returns a list of sumtrain instances in a provided set of courses,
                            if no courses are provided then all the sumtrain instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sumtrain_delete_sumtrain_responses' => array(
        'classname'     => 'mod_sumtrain_external',
        'methodname'    => 'delete_sumtrain_responses',
        'description'   => 'Delete the given submitted responses in a sumtrain',
        'type'          => 'write',
        'capabilities'  => 'mod/sumtrain:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
