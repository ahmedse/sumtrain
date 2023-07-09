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
 * sumtrain module external API
 *
 * @package    mod_sumtrain
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

use core_course\external\helper_for_get_mods_by_courses;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/mod/sumtrain/lib.php');

/**
 * sumtrain module external functions
 *
 * @package    mod_sumtrain
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_sumtrain_external extends external_api {

    /**
     * Describes the parameters for get_sumtrains_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_sumtrain_results_parameters() {
        return new external_function_parameters (array('sumtrainid' => new external_value(PARAM_INT, 'sumtrain instance id')));
    }
    /**
     * Returns user's results for a specific sumtrain
     * and a list of those users that did not answered yet.
     *
     * @param int $sumtrainid the sumtrain instance id
     * @return array of responses details
     * @since Moodle 3.0
     */
    public static function get_sumtrain_results($sumtrainid) {
        global $USER, $PAGE;

        $params = self::validate_parameters(self::get_sumtrain_results_parameters(), array('sumtrainid' => $sumtrainid));

        if (!$sumtrain = sumtrain_get_sumtrain($params['sumtrainid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($sumtrain, 'sumtrain');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $groupmode = groups_get_activity_groupmode($cm);
        // Check if we have to include responses from inactive users.
        $onlyactive = $sumtrain->includeinactive ? false : true;
        $users = sumtrain_get_response_data($sumtrain, $cm, $groupmode, $onlyactive);
        // Show those who haven't answered the question.
        if (!empty($sumtrain->showunanswered)) {
            $sumtrain->option[0] = get_string('notanswered', 'sumtrain');
            $sumtrain->maxanswers[0] = 0;
        }
        $results = prepare_sumtrain_show_results($sumtrain, $course, $cm, $users);

        $options = array();
        $fullnamecap = has_capability('moodle/site:viewfullnames', $context);
        foreach ($results->options as $optionid => $option) {

            $userresponses = array();
            $numberofuser = 0;
            $percentageamount = 0;
            if (property_exists($option, 'user') and
                (has_capability('mod/sumtrain:readresponses', $context) or sumtrain_can_view_results($sumtrain))) {
                $numberofuser = count($option->user);
                $percentageamount = ((float)$numberofuser / (float)$results->numberofuser) * 100.0;
                if ($sumtrain->publish) {
                    foreach ($option->user as $userresponse) {
                        $response = array();
                        $response['userid'] = $userresponse->id;
                        $response['fullname'] = fullname($userresponse, $fullnamecap);

                        $userpicture = new user_picture($userresponse);
                        $userpicture->size = 1; // Size f1.
                        $response['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);

                        // Add optional properties.
                        foreach (array('answerid', 'timemodified') as $field) {
                            if (property_exists($userresponse, 'answerid')) {
                                $response[$field] = $userresponse->$field;
                            }
                        }
                        $userresponses[] = $response;
                    }
                }
            }

            $options[] = array('id'               => $optionid,
                               'text'             => \core_external\util::format_string($option->text, $context->id),
                               'maxanswer'        => $option->maxanswer,
                               'userresponses'    => $userresponses,
                               'numberofuser'     => $numberofuser,
                               'percentageamount' => $percentageamount
                              );
        }

        $warnings = array();
        return array(
            'options' => $options,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_sumtrain_results return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_sumtrain_results_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'sumtrain instance id'),
                            'text' => new external_value(PARAM_RAW, 'text of the sumtrain'),
                            'maxanswer' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'userresponses' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'userid' => new external_value(PARAM_INT, 'user id'),
                                        'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                                        'profileimageurl' => new external_value(PARAM_URL, 'profile user image url'),
                                        'answerid' => new external_value(PARAM_INT, 'answer id', VALUE_OPTIONAL),
                                        'timemodified' => new external_value(PARAM_INT, 'time of modification', VALUE_OPTIONAL),
                                     ), 'User responses'
                                 )
                            ),
                            'numberofuser' => new external_value(PARAM_INT, 'number of users answers'),
                            'percentageamount' => new external_value(PARAM_FLOAT, 'percentage of users answers')
                        ), 'Options'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for mod_sumtrain_get_sumtrain_options.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_sumtrain_options_parameters() {
        return new external_function_parameters (array('sumtrainid' => new external_value(PARAM_INT, 'sumtrain instance id')));
    }

    /**
     * Returns options for a specific sumtrain
     *
     * @param int $sumtrainid the sumtrain instance id
     * @return array of options details
     * @since Moodle 3.0
     */
    public static function get_sumtrain_options($sumtrainid) {
        global $USER;
        $warnings = array();
        $params = self::validate_parameters(self::get_sumtrain_options_parameters(), array('sumtrainid' => $sumtrainid));

        if (!$sumtrain = sumtrain_get_sumtrain($params['sumtrainid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($sumtrain, 'sumtrain');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/sumtrain:choose', $context);

        $groupmode = groups_get_activity_groupmode($cm);
        $onlyactive = $sumtrain->includeinactive ? false : true;
        $allresponses = sumtrain_get_response_data($sumtrain, $cm, $groupmode, $onlyactive);

        $timenow = time();
        $sumtrainopen = true;
        $showpreview = false;

        if (!empty($sumtrain->timeopen) && ($sumtrain->timeopen > $timenow)) {
            $sumtrainopen = false;
            $warnings[1] = get_string("notopenyet", "sumtrain", userdate($sumtrain->timeopen));
            if ($sumtrain->showpreview) {
                $warnings[2] = get_string('previewonly', 'sumtrain', userdate($sumtrain->timeopen));
                $showpreview = true;
            }
        }
        if (!empty($sumtrain->timeclose) && ($timenow > $sumtrain->timeclose)) {
            $sumtrainopen = false;
            $warnings[3] = get_string("expired", "sumtrain", userdate($sumtrain->timeclose));
        }

        $optionsarray = array();

        if ($sumtrainopen or $showpreview) {

            $options = sumtrain_prepare_options($sumtrain, $USER, $cm, $allresponses);

            foreach ($options['options'] as $option) {
                $optionarr = array();
                $optionarr['id']            = $option->attributes->value;
                $optionarr['text']          = \core_external\util::format_string($option->text, $context->id);
                $optionarr['maxanswers']    = $option->maxanswers;
                $optionarr['displaylayout'] = $option->displaylayout;
                $optionarr['countanswers']  = $option->countanswers;
                foreach (array('checked', 'disabled') as $field) {
                    if (property_exists($option->attributes, $field) and $option->attributes->$field == 1) {
                        $optionarr[$field] = 1;
                    } else {
                        $optionarr[$field] = 0;
                    }
                }
                // When showpreview is active, we show options as disabled.
                if ($showpreview or ($optionarr['checked'] == 1 and !$sumtrain->allowupdate)) {
                    $optionarr['disabled'] = 1;
                }
                $optionsarray[] = $optionarr;
            }
        }
        foreach ($warnings as $key => $message) {
            $warnings[$key] = array(
                'item' => 'sumtrain',
                'itemid' => $cm->id,
                'warningcode' => $key,
                'message' => $message
            );
        }
        return array(
            'options' => $optionsarray,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_sumtrain_results return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function get_sumtrain_options_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'text' => new external_value(PARAM_RAW, 'text of the sumtrain'),
                            'maxanswers' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'displaylayout' => new external_value(PARAM_BOOL, 'true for orizontal, otherwise vertical'),
                            'countanswers' => new external_value(PARAM_INT, 'number of answers'),
                            'checked' => new external_value(PARAM_BOOL, 'we already answered'),
                            'disabled' => new external_value(PARAM_BOOL, 'option disabled'),
                            )
                    ), 'Options'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for submit_sumtrain_response.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function submit_sumtrain_response_parameters() {
        return new external_function_parameters (
            array(
                'sumtrainid' => new external_value(PARAM_INT, 'sumtrain instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'answer id'),
                    'Array of response ids'
                ),
            )
        );
    }

    /**
     * Submit sumtrain responses
     *
     * @param int $sumtrainid the sumtrain instance id
     * @param array $responses the response ids
     * @return array answers information and warnings
     * @since Moodle 3.0
     */
    public static function submit_sumtrain_response($sumtrainid, $responses) {
        global $USER;

        $warnings = array();
        $params = self::validate_parameters(self::submit_sumtrain_response_parameters(),
                                            array(
                                                'sumtrainid' => $sumtrainid,
                                                'responses' => $responses
                                            ));

        if (!$sumtrain = sumtrain_get_sumtrain($params['sumtrainid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($sumtrain, 'sumtrain');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/sumtrain:choose', $context);

        $timenow = time();
        if (!empty($sumtrain->timeopen) && ($sumtrain->timeopen > $timenow)) {
            throw new moodle_exception("notopenyet", "sumtrain", '', userdate($sumtrain->timeopen));
        } else if (!empty($sumtrain->timeclose) && ($timenow > $sumtrain->timeclose)) {
            throw new moodle_exception("expired", "sumtrain", '', userdate($sumtrain->timeclose));
        }

        if (!sumtrain_get_my_response($sumtrain) or $sumtrain->allowupdate) {
            // When a single response is given, we convert the array to a simple variable
            // in order to avoid sumtrain_user_submit_response to check with allowmultiple even
            // for a single response.
            if (count($params['responses']) == 1) {
                $params['responses'] = reset($params['responses']);
            }
            sumtrain_user_submit_response($params['responses'], $sumtrain, $USER->id, $course, $cm);
        } else {
            throw new moodle_exception('missingrequiredcapability', 'webservice', '', 'allowupdate');
        }
        $answers = sumtrain_get_my_response($sumtrain);

        return array(
            'answers' => $answers,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the submit_sumtrain_response return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function submit_sumtrain_response_returns() {
        return new external_single_structure(
            array(
                'answers' => new external_multiple_structure(
                     new external_single_structure(
                         array(
                             'id'           => new external_value(PARAM_INT, 'answer id'),
                             'sumtrainid'     => new external_value(PARAM_INT, 'sumtrainid'),
                             'userid'       => new external_value(PARAM_INT, 'user id'),
                             'optionid'     => new external_value(PARAM_INT, 'optionid'),
                             'timemodified' => new external_value(PARAM_INT, 'time of last modification')
                         ), 'Answers'
                     )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_sumtrain_parameters() {
        return new external_function_parameters(
            array(
                'sumtrainid' => new external_value(PARAM_INT, 'sumtrain instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $sumtrainid the sumtrain instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_sumtrain($sumtrainid) {
        global $CFG;

        $params = self::validate_parameters(self::view_sumtrain_parameters(),
                                            array(
                                                'sumtrainid' => $sumtrainid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$sumtrain = sumtrain_get_sumtrain($params['sumtrainid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($sumtrain, 'sumtrain');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Trigger course_module_viewed event and completion.
        sumtrain_view($sumtrain, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function view_sumtrain_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_sumtrains_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_sumtrains_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of sumtrains in a provided list of courses,
     * if no list is provided all sumtrains that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of sumtrains details
     * @since Moodle 3.0
     */
    public static function get_sumtrains_by_courses($courseids = array()) {
        $returnedsumtrains = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_sumtrains_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = util::validate_courses($params['courseids'], $courses);

            // Get the sumtrains in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $sumtrains = get_all_instances_in_courses("sumtrain", $courses);
            foreach ($sumtrains as $sumtrain) {
                $context = context_module::instance($sumtrain->coursemodule);

                $sumtraindetails = helper_for_get_mods_by_courses::standard_coursemodule_element_values($sumtrain, 'mod_sumtrain');

                if (has_capability('mod/sumtrain:choose', $context)) {
                    $sumtraindetails['publish']  = $sumtrain->publish;
                    $sumtraindetails['showresults']  = $sumtrain->showresults;
                    $sumtraindetails['showpreview']  = $sumtrain->showpreview;
                    $sumtraindetails['timeopen']  = $sumtrain->timeopen;
                    $sumtraindetails['timeclose']  = $sumtrain->timeclose;
                    $sumtraindetails['display']  = $sumtrain->display;
                    $sumtraindetails['allowupdate']  = $sumtrain->allowupdate;
                    $sumtraindetails['allowmultiple']  = $sumtrain->allowmultiple;
                    $sumtraindetails['limitanswers']  = $sumtrain->limitanswers;
                    $sumtraindetails['showunanswered']  = $sumtrain->showunanswered;
                    $sumtraindetails['includeinactive']  = $sumtrain->includeinactive;
                    $sumtraindetails['showavailable']  = $sumtrain->showavailable;
                }

                if (has_capability('moodle/course:manageactivities', $context)) {
                    $sumtraindetails['timemodified']  = $sumtrain->timemodified;
                    $sumtraindetails['completionsubmit']  = $sumtrain->completionsubmit;
                }
                $returnedsumtrains[] = $sumtraindetails;
            }
        }
        $result = array();
        $result['sumtrains'] = $returnedsumtrains;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_sumtrain_get_sumtrains_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_sumtrains_by_courses_returns() {
        return new external_single_structure(
            array(
                'sumtrains' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                        [
                            'publish' => new external_value(PARAM_BOOL, 'If sumtrain is published', VALUE_OPTIONAL),
                            'showresults' => new external_value(PARAM_INT, '0 never, 1 after answer, 2 after close, 3 always',
                                                                VALUE_OPTIONAL),
                            'display' => new external_value(PARAM_INT, 'Display mode (vertical, horizontal)', VALUE_OPTIONAL),
                            'allowupdate' => new external_value(PARAM_BOOL, 'Allow update', VALUE_OPTIONAL),
                            'allowmultiple' => new external_value(PARAM_BOOL, 'Allow multiple sumtrains', VALUE_OPTIONAL),
                            'showunanswered' => new external_value(PARAM_BOOL, 'Show users who not answered yet', VALUE_OPTIONAL),
                            'includeinactive' => new external_value(PARAM_BOOL, 'Include inactive users', VALUE_OPTIONAL),
                            'limitanswers' => new external_value(PARAM_BOOL, 'Limit unswers', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'Date of opening validity', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'Date of closing validity', VALUE_OPTIONAL),
                            'showpreview' => new external_value(PARAM_BOOL, 'Show preview before timeopen', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                            'completionsubmit' => new external_value(PARAM_BOOL, 'Completion on user submission', VALUE_OPTIONAL),
                            'showavailable' => new external_value(PARAM_BOOL, 'Show available spaces', VALUE_OPTIONAL),
                        ]
                    ), 'sumtrains')
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for delete_sumtrain_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function delete_sumtrain_responses_parameters() {
        return new external_function_parameters (
            array(
                'sumtrainid' => new external_value(PARAM_INT, 'sumtrain instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'response id'),
                    'Array of response ids, empty for deleting all the current user responses.',
                    VALUE_DEFAULT,
                    array()
                ),
            )
        );
    }

    /**
     * Delete the given submitted responses in a sumtrain
     *
     * @param int $sumtrainid the sumtrain instance id
     * @param array $responses the response ids,  empty for deleting all the current user responses
     * @return array status information and warnings
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function delete_sumtrain_responses($sumtrainid, $responses = array()) {

        $status = false;
        $warnings = array();
        $params = self::validate_parameters(self::delete_sumtrain_responses_parameters(),
                                            array(
                                                'sumtrainid' => $sumtrainid,
                                                'responses' => $responses
                                            ));

        if (!$sumtrain = sumtrain_get_sumtrain($params['sumtrainid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($sumtrain, 'sumtrain');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/sumtrain:choose', $context);

        $candeleteall = has_capability('mod/sumtrain:deleteresponses', $context);
        if ($candeleteall || $sumtrain->allowupdate) {

            // Check if we can delete our own responses.
            if (!$candeleteall) {
                $timenow = time();
                if (!empty($sumtrain->timeclose) && ($timenow > $sumtrain->timeclose)) {
                    throw new moodle_exception("expired", "sumtrain", '', userdate($sumtrain->timeclose));
                }
            }

            if (empty($params['responses'])) {
                // No responses indicated so delete only my responses.
                $todelete = array_keys(sumtrain_get_my_response($sumtrain));
            } else {
                // Fill an array with the responses that can be deleted for this sumtrain.
                if ($candeleteall) {
                    // Teacher/managers can delete any.
                    $allowedresponses = array_keys(sumtrain_get_all_responses($sumtrain));
                } else {
                    // Students can delete only their own responses.
                    $allowedresponses = array_keys(sumtrain_get_my_response($sumtrain));
                }

                $todelete = array();
                foreach ($params['responses'] as $response) {
                    if (!in_array($response, $allowedresponses)) {
                        $warnings[] = array(
                            'item' => 'response',
                            'itemid' => $response,
                            'warningcode' => 'nopermissions',
                            'message' => 'Invalid response id, the response does not exist or you are not allowed to delete it.'
                        );
                    } else {
                        $todelete[] = $response;
                    }
                }
            }

            $status = sumtrain_delete_responses($todelete, $sumtrain, $cm, $course);
        } else {
            // The user requires the capability to delete responses.
            throw new required_capability_exception($context, 'mod/sumtrain:deleteresponses', 'nopermissions', '');
        }

        return array(
            'status' => $status,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the delete_sumtrain_responses return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function delete_sumtrain_responses_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if everything went right'),
                'warnings' => new external_warnings(),
            )
        );
    }

}
