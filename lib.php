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
 * @package   mod_sumtrain
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @global int $sumtrain_COLUMN_HEIGHT */
global $sumtrain_COLUMN_HEIGHT;
$sumtrain_COLUMN_HEIGHT = 300;

/** @global int $sumtrain_COLUMN_WIDTH */
global $sumtrain_COLUMN_WIDTH;
$sumtrain_COLUMN_WIDTH = 300;



define('sumtrain_PUBLISH_ANONYMOUS', '0');
define('sumtrain_PUBLISH_NAMES',     '1');

define('sumtrain_SHOWRESULTS_NOT',          '0');
define('sumtrain_SHOWRESULTS_AFTER_ANSWER', '1');
define('sumtrain_SHOWRESULTS_AFTER_CLOSE',  '2');
define('sumtrain_SHOWRESULTS_ALWAYS',       '3');

define('sumtrain_DISPLAY_HORIZONTAL',  '0');
define('sumtrain_DISPLAY_VERTICAL',    '1');

define('sumtrain_EVENT_TYPE_OPEN', 'open');
define('sumtrain_EVENT_TYPE_CLOSE', 'close');

require_once($CFG->dirroot . '/group/lib.php');

define('YEAR', '2023');
define("BATCH1", "Cohort 2022-2027 cohort");
define("BATCH2", "Cohort 2021-2026 cohort");

function getUserGroups(){
    global $COURSE, $DB, $USER;
    $batch= 0;    

    $groups= [];
    $ret = groups_get_user_groups($COURSE->id, $USER->id);
    $str= '';
    foreach ($ret[0] as $id) {    
        $g= groups_get_group_name($id);        
        switch ($g) {
            case BATCH1:
                $batch= 1;
                break;
            case BATCH2:
                $batch= 2;
                break;           
        }
        if ($str> ""){
            $str= $str . " OR FIND_IN_SET('{$g}', s.student_groups)";
        }
        else{
            $str= " AND FIND_IN_SET('{$g}', s.student_groups)";
        }
        $groups[]= $g;
    }    
    return array($groups, $str, $batch);
}






/** @global array $sumtrain_PUBLISH */
global $sumtrain_PUBLISH;
$sumtrain_PUBLISH = array (sumtrain_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'sumtrain'),
                         sumtrain_PUBLISH_NAMES      => get_string('publishnames', 'sumtrain'));

/** @global array $sumtrain_SHOWRESULTS */
global $sumtrain_SHOWRESULTS;
$sumtrain_SHOWRESULTS = array (sumtrain_SHOWRESULTS_NOT          => get_string('publishnot', 'sumtrain'),
                         sumtrain_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'sumtrain'),
                         sumtrain_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'sumtrain'),
                         sumtrain_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'sumtrain'));

/** @global array $sumtrain_DISPLAY */
global $sumtrain_DISPLAY;
$sumtrain_DISPLAY = array (sumtrain_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'sumtrain'),
                         sumtrain_DISPLAY_VERTICAL     => get_string('displayvertical','sumtrain'));

require_once(__DIR__ . '/deprecatedlib.php');

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $sumtrain
 * @return object|null
 */
function sumtrain_user_outline($course, $user, $mod, $sumtrain) {
    global $DB;
    if ($answer = $DB->get_record('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(sumtrain_get_option_text($sumtrain, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Callback for the "Complete" report - prints the activity summary for the given user
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $sumtrain
 */
function sumtrain_user_complete($course, $user, $mod, $sumtrain) {
    global $DB;
    if ($answers = $DB->get_records('sumtrain_answers', array("sumtrainid" => $sumtrain->id, "userid" => $user->id))) {
        $info = [];
        foreach ($answers as $answer) {
            $info[] = "'" . format_string(sumtrain_get_option_text($sumtrain, $answer->optionid)) . "'";
        }
        core_collator::asort($info);
        echo get_string("answered", "sumtrain") . ": ". join(', ', $info) . ". " .
                get_string("updated", '', userdate($answer->timemodified));
    } else {
        print_string("notanswered", "sumtrain");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $sumtrain
 * @return int
 */
function sumtrain_add_instance($sumtrain) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/sumtrain/locallib.php');

    $sumtrain->timemodified = time();

    //insert answers
    $sumtrain->id = $DB->insert_record("sumtrain", $sumtrain);
    foreach ($sumtrain->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->sumtrainid = $sumtrain->id;
            if (isset($sumtrain->limit[$key])) {
                $option->maxanswers = $sumtrain->limit[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("sumtrain_options", $option);
        }
    }

    // Add calendar events if necessary.
    sumtrain_set_events($sumtrain);
    if (!empty($sumtrain->completionexpected)) {
        \core_completion\api::update_completion_date_event($sumtrain->coursemodule, 'sumtrain', $sumtrain->id,
                $sumtrain->completionexpected);
    }

    return $sumtrain->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $sumtrain
 * @return bool
 */
function sumtrain_update_instance($sumtrain) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/sumtrain/locallib.php');

    $sumtrain->id = $sumtrain->instance;
    $sumtrain->timemodified = time();

    //update, delete or insert answers
    foreach ($sumtrain->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->sumtrainid = $sumtrain->id;
        if (isset($sumtrain->limit[$key])) {
            $option->maxanswers = $sumtrain->limit[$key];
        }
        $option->timemodified = time();
        if (isset($sumtrain->optionid[$key]) && !empty($sumtrain->optionid[$key])){//existing sumtrain record
            $option->id=$sumtrain->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("sumtrain_options", $option);
            } else {
                // Remove the empty (unused) option.
                $DB->delete_records("sumtrain_options", array("id" => $option->id));
                // Delete any answers associated with this option.
                $DB->delete_records("sumtrain_answers", array("sumtrainid" => $sumtrain->id, "optionid" => $option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("sumtrain_options", $option);
            }
        }
    }

    // Add calendar events if necessary.
    sumtrain_set_events($sumtrain);
    $completionexpected = (!empty($sumtrain->completionexpected)) ? $sumtrain->completionexpected : null;
    \core_completion\api::update_completion_date_event($sumtrain->coursemodule, 'sumtrain', $sumtrain->id, $completionexpected);

    return $DB->update_record('sumtrain', $sumtrain);

}

/**
 * @global object
 * @param object $sumtrain
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function sumtrain_prepare_options($sumtrain, $user, $coursemodule, $allresponses) {
    global $DB;

    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = $sumtrain->limitanswers;
    $cdisplay['showavailable'] = $sumtrain->showavailable;

    $context = context_module::instance($coursemodule->id);

    foreach ($sumtrain->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $sumtrain->maxanswers[$optionid];
            $option->displaylayout = $sumtrain->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $sumtrain->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/sumtrain:choose'); //only enrolled users are allowed to make a sumtrain

    if ($sumtrain->allowupdate && $DB->record_exists('sumtrain_answers', array('sumtrainid'=> $sumtrain->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    if ($sumtrain->showpreview && $sumtrain->timeopen > time()) {
        $cdisplay['previewonly'] = true;
    }

    return $cdisplay;
}

/**
 * Modifies responses of other users adding the option $newoptionid to them
 *
 * @param array $userids list of users to add option to (must be users without any answers yet)
 * @param array $answerids list of existing attempt ids of users (will be either appended or
 *      substituted with the newoptionid, depending on $sumtrain->allowmultiple)
 * @param int $newoptionid
 * @param stdClass $sumtrain sumtrain object, result of {@link sumtrain_get_sumtrain()}
 * @param stdClass $cm
 * @param stdClass $course
 */
function sumtrain_modify_responses($userids, $answerids, $newoptionid, $sumtrain, $cm, $course) {
    // Get all existing responses and the list of non-respondents.
    $groupmode = groups_get_activity_groupmode($cm);
    $onlyactive = $sumtrain->includeinactive ? false : true;
    $allresponses = sumtrain_get_response_data($sumtrain, $cm, $groupmode, $onlyactive);

    // Check that the option value is valid.
    if (!$newoptionid || !isset($sumtrain->option[$newoptionid])) {
        return;
    }

    // First add responses for users who did not make any sumtrain yet.
    foreach ($userids as $userid) {
        if (isset($allresponses[0][$userid])) {
            sumtrain_user_submit_response($newoptionid, $sumtrain, $userid, $course, $cm);
        }
    }

    // Create the list of all options already selected by each user.
    $optionsbyuser = []; // Mapping userid=>array of chosen sumtrain options.
    $usersbyanswer = []; // Mapping answerid=>userid (which answer belongs to each user).
    foreach ($allresponses as $optionid => $responses) {
        if ($optionid > 0) {
            foreach ($responses as $userid => $userresponse) {
                $optionsbyuser += [$userid => []];
                $optionsbyuser[$userid][] = $optionid;
                $usersbyanswer[$userresponse->answerid] = $userid;
            }
        }
    }

    // Go through the list of submitted attemptids and find which users answers need to be updated.
    foreach ($answerids as $answerid) {
        if (isset($usersbyanswer[$answerid])) {
            $userid = $usersbyanswer[$answerid];
            if (!in_array($newoptionid, $optionsbyuser[$userid])) {
                $options = $sumtrain->allowmultiple ?
                        array_merge($optionsbyuser[$userid], [$newoptionid]) : $newoptionid;
                sumtrain_user_submit_response($options, $sumtrain, $userid, $course, $cm);
            }
        }
    }
}

/**
 * Process user submitted answers for a sumtrain,
 * and either updating them or saving new answers.
 *
 * @param int|array $formanswer the id(s) of the user submitted sumtrain options.
 * @param object $sumtrain the selected sumtrain.
 * @param int $userid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 * @return void
 */
function sumtrain_user_submit_response($formanswer, $sumtrain, $userid, $course, $cm) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    $continueurl = new moodle_url('/mod/sumtrain/view.php', array('id' => $cm->id));

    if (empty($formanswer)) {
        throw new \moodle_exception('atleastoneoption', 'sumtrain', $continueurl);
    }

    if (is_array($formanswer)) {
        if (!$sumtrain->allowmultiple) {
            throw new \moodle_exception('multiplenotallowederror', 'sumtrain', $continueurl);
        }
        $formanswers = $formanswer;
    } else {
        $formanswers = array($formanswer);
    }

    $options = $DB->get_records('sumtrain_options', array('sumtrainid' => $sumtrain->id), '', 'id');
    foreach ($formanswers as $key => $val) {
        if (!isset($options[$val])) {
            throw new \moodle_exception('cannotsubmit', 'sumtrain', $continueurl);
        }
    }
    // Start lock to prevent synchronous access to the same data
    // before it's updated, if using limits.
    if ($sumtrain->limitanswers) {
        $timeout = 10;
        $locktype = 'mod_sumtrain_sumtrain_user_submit_response';
        // Limiting access to this sumtrain.
        $resouce = 'sumtrainid:' . $sumtrain->id;
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // Opening the lock.
        $sumtrainlock = $lockfactory->get_lock($resouce, $timeout, MINSECS);
        if (!$sumtrainlock) {
            throw new \moodle_exception('cannotsubmit', 'sumtrain', $continueurl);
        }
    }

    $current = $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $userid));

    // Array containing [answerid => optionid] mapping.
    $existinganswers = array_map(function($answer) {
        return $answer->optionid;
    }, $current);

    $context = context_module::instance($cm->id);

    $sumtrainsexceeded = false;
    $countanswers = array();
    foreach ($formanswers as $val) {
        $countanswers[$val] = 0;
    }
    if($sumtrain->limitanswers) {
        // Find out whether groups are being used and enabled
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }

        list ($insql, $params) = $DB->get_in_or_equal($formanswers, SQL_PARAMS_NAMED);

        if($currentgroup) {
            // If groups are being used, retrieve responses only for users in
            // current group
            global $CFG;

            $params['groupid'] = $currentgroup;
            $sql = "SELECT ca.*
                      FROM {sumtrain_answers} ca
                INNER JOIN {groups_members} gm ON ca.userid=gm.userid
                     WHERE optionid $insql
                       AND gm.groupid= :groupid";
        } else {
            // Groups are not used, retrieve all answers for this option ID
            $sql = "SELECT ca.*
                      FROM {sumtrain_answers} ca
                     WHERE optionid $insql";
        }

        $answers = $DB->get_records_sql($sql, $params);
        if ($answers) {
            foreach ($answers as $a) { //only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/sumtrain:choose')) {
                    $countanswers[$a->optionid]++;
                }
            }
        }

        foreach ($countanswers as $opt => $count) {
            // Ignore the user's existing answers when checking whether an answer count has been exceeded.
            // A user may wish to update their response with an additional sumtrain option and shouldn't be competing with themself!
            if (in_array($opt, $existinganswers)) {
                continue;
            }
            if ($count >= $sumtrain->maxanswers[$opt]) {
                $sumtrainsexceeded = true;
                break;
            }
        }
    }

    // Check the user hasn't exceeded the maximum selections for the sumtrain(s) they have selected.
    $answersnapshots = array();
    $deletedanswersnapshots = array();
    if (!($sumtrain->limitanswers && $sumtrainsexceeded)) {
        if ($current) {
            // Update an existing answer.
            foreach ($current as $c) {
                if (in_array($c->optionid, $formanswers)) {
                    $DB->set_field('sumtrain_answers', 'timemodified', time(), array('id' => $c->id));
                } else {
                    $deletedanswersnapshots[] = $c;
                    $DB->delete_records('sumtrain_answers', array('id' => $c->id));
                }
            }

            // Add new ones.
            foreach ($formanswers as $f) {
                if (!in_array($f, $existinganswers)) {
                    $newanswer = new stdClass();
                    $newanswer->optionid = $f;
                    $newanswer->sumtrainid = $sumtrain->id;
                    $newanswer->userid = $userid;
                    $newanswer->timemodified = time();
                    $newanswer->id = $DB->insert_record("sumtrain_answers", $newanswer);
                    $answersnapshots[] = $newanswer;
                }
            }
        } else {
            // Add new answer.
            foreach ($formanswers as $answer) {
                $newanswer = new stdClass();
                $newanswer->sumtrainid = $sumtrain->id;
                $newanswer->userid = $userid;
                $newanswer->optionid = $answer;
                $newanswer->timemodified = time();
                $newanswer->id = $DB->insert_record("sumtrain_answers", $newanswer);
                $answersnapshots[] = $newanswer;
            }

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $sumtrain->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    } else {
        // This is a sumtrain with limited options, and one of the options selected has just run over its limit.
        $sumtrainlock->release();
        throw new \moodle_exception('sumtrainfull', 'sumtrain', $continueurl);
    }

    // Release lock.
    if (isset($sumtrainlock)) {
        $sumtrainlock->release();
    }

    // Trigger events.
    foreach ($deletedanswersnapshots as $answer) {
        \mod_sumtrain\event\answer_deleted::create_from_object($answer, $sumtrain, $cm, $course)->trigger();
    }
    foreach ($answersnapshots as $answer) {
        \mod_sumtrain\event\answer_created::create_from_object($answer, $sumtrain, $cm, $course)->trigger();
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function sumtrain_show_reportlink($user, $cm) {
    $userschosen = array();
    foreach($user as $optionid => $userlist) {
        if ($optionid) {
            $userschosen = array_merge($userschosen, array_keys($userlist));
        }
    }
    $responsecount = count(array_unique($userschosen));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "sumtrain", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $sumtrain
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_sumtrain_show_results($sumtrain, $course, $cm, $allresponses) {
    global $OUTPUT;

    $display = clone($sumtrain);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    if (!empty($sumtrain->showunanswered)) {
        $sumtrain->option[0] = get_string('notanswered', 'sumtrain');
        $sumtrain->maxanswers[0] = 0;
    }

    // Remove from the list of non-respondents the users who do not have access to this activity.
    if (!empty($display->showunanswered) && $allresponses[0]) {
        $info = new \core_availability\info_module(cm_info::create($cm));
        $allresponses[0] = $info->filter_user_list($allresponses[0]);
    }

    //overwrite options value;
    $display->options = array();
    $allusers = [];
    foreach ($sumtrain->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = format_string($optiontext, true,
            ['context' => context_module::instance($cm->id)]);
        $display->options[$optionid]->maxanswer = $sumtrain->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $allusers = array_merge($allusers, array_keys($allresponses[$optionid]));
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = count(array_unique($allusers));
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/sumtrain:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/sumtrain:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"), 3, null);
        return false;
    }

    return $display;
}

/**
 * @global object
 * @param array $attemptids
 * @param object $sumtrain sumtrain main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function sumtrain_delete_responses($attemptids, $sumtrain, $cm, $course) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach($attemptids as $num => $attemptid) {
        if(empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'id' => $attemptid))) {
            // Trigger the event answer deleted.
            \mod_sumtrain\event\answer_deleted::create_from_object($todelete, $sumtrain, $cm, $course)->trigger();
            $DB->delete_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'id' => $attemptid));
        }
    }

    // Update completion state.
    if ($completion->is_enabled($cm) && $sumtrain->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE);
    }

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function sumtrain_delete_instance($id) {
    global $DB;

    if (! $sumtrain = $DB->get_record("sumtrain", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("sumtrain_answers", array("sumtrainid"=>"$sumtrain->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("sumtrain_options", array("sumtrainid"=>"$sumtrain->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("sumtrain", array("id"=>"$sumtrain->id"))) {
        $result = false;
    }
    // Remove old calendar events.
    if (! $DB->delete_records('event', array('modulename' => 'sumtrain', 'instance' => $sumtrain->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $sumtrain
 * @param int $id
 * @return string
 */
function sumtrain_get_option_text($sumtrain, $id) {
    global $DB;

    if ($result = $DB->get_record("sumtrain_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "sumtrain");
    }
}

/**
 * Gets a full sumtrain record
 *
 * @global object
 * @param int $sumtrainid
 * @return object|bool The sumtrain or false
 */
function sumtrain_get_sumtrain($sumtrainid) {
    global $DB;

    if ($sumtrain = $DB->get_record("sumtrain", array("id" => $sumtrainid))) {
        if ($options = $DB->get_records("sumtrain_options", array("sumtrainid" => $sumtrainid), "id")) {
            foreach ($options as $option) {
                $sumtrain->option[$option->id] = $option->text;
                $sumtrain->maxanswers[$option->id] = $option->maxanswers;
            }
            return $sumtrain;
        }
    }
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function sumtrain_get_view_actions() {
    return array('view','view all','report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function sumtrain_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the sumtrain.
 *
 * @param MoodleQuickForm $mform form passed by reference
 */
function sumtrain_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'sumtrainheader', get_string('modulenameplural', 'sumtrain'));
    $mform->addElement('advcheckbox', 'reset_sumtrain', get_string('removeresponses','sumtrain'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function sumtrain_reset_course_form_defaults($course) {
    return array('reset_sumtrain'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * sumtrain responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function sumtrain_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'sumtrain');
    $status = array();

    if (!empty($data->reset_sumtrain)) {
        $sumtrainssql = "SELECT ch.id
                       FROM {sumtrain} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('sumtrain_answers', "sumtrainid IN ($sumtrainssql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'sumtrain'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('sumtrain', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $sumtrain
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @return array
 */
function sumtrain_get_response_data($sumtrain, $cm, $groupmode, $onlyactive) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    // TODO Does not support custom user profile fields (MDL-70456).
    $userfieldsapi = \core_user\fields::for_identity($context, false)->with_userpic();
    $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $allresponses[0] = get_enrolled_users($context, 'mod/sumtrain:choose', $currentgroup,
            $userfields, null, 0, 0, $onlyactive);

/// Get all the recorded responses for this sumtrain
    $rawresponses = $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        $answeredusers = array();
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    }
    return $allresponses;
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function sumtrain_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_MOD_PURPOSE:             return MOD_PURPOSE_COMMUNICATION;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $sumtrainnode The node to add module settings to
 */
function sumtrain_extend_settings_navigation(settings_navigation $settings, navigation_node $sumtrainnode) {
    if (has_capability('mod/sumtrain:readresponses', $settings->get_page()->cm->context)) {
        $sumtrainnode->add(get_string('responses', 'sumtrain'),
            new moodle_url('/mod/sumtrain/report.php', array('id' => $settings->get_page()->cm->id)));
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function sumtrain_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-sumtrain-*'=>get_string('page-mod-sumtrain-x', 'sumtrain'));
    return $module_pagetype;
}

/**
 * @deprecated since Moodle 3.3, when the block_course_overview block was removed.
 */
function sumtrain_print_overview() {
    throw new coding_exception('sumtrain_print_overview() can not be used any more and is obsolete.');
}


/**
 * Get responses of a given user on a given sumtrain.
 *
 * @param stdClass $sumtrain sumtrain record
 * @param int $userid User id
 * @return array of sumtrain answers records
 * @since  Moodle 3.6
 */
function sumtrain_get_user_response($sumtrain, $userid) {
    global $DB;
    return $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $userid), 'optionid');
}

/**
 * Get my responses on a given sumtrain.
 *
 * @param stdClass $sumtrain sumtrain record
 * @return array of sumtrain answers records
 * @since  Moodle 3.0
 */
function sumtrain_get_my_response($sumtrain) {
    global $USER;
    return sumtrain_get_user_response($sumtrain, $USER->id);
}


/**
 * Get all the responses on a given sumtrain.
 *
 * @param stdClass $sumtrain sumtrain record
 * @return array of sumtrain answers records
 * @since  Moodle 3.0
 */
function sumtrain_get_all_responses($sumtrain) {
    global $DB;
    return $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id));
}


/**
 * Return true if we are allowd to view the sumtrain results.
 *
 * @param stdClass $sumtrain sumtrain record
 * @param rows|null $current my sumtrain responses
 * @param bool|null $sumtrainopen if the sumtrain is open
 * @return bool true if we can view the results, false otherwise.
 * @since  Moodle 3.0
 */
function sumtrain_can_view_results($sumtrain, $current = null, $sumtrainopen = null) {

    if (is_null($sumtrainopen)) {
        $timenow = time();

        if ($sumtrain->timeopen != 0 && $timenow < $sumtrain->timeopen) {
            // If the sumtrain is not available, we can't see the results.
            return false;
        }

        if ($sumtrain->timeclose != 0 && $timenow > $sumtrain->timeclose) {
            $sumtrainopen = false;
        } else {
            $sumtrainopen = true;
        }
    }
    if (empty($current)) {
        $current = sumtrain_get_my_response($sumtrain);
    }

    if ($sumtrain->showresults == sumtrain_SHOWRESULTS_ALWAYS or
       ($sumtrain->showresults == sumtrain_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
       ($sumtrain->showresults == sumtrain_SHOWRESULTS_AFTER_CLOSE and !$sumtrainopen)) {
        return true;
    }
    return false;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $sumtrain     sumtrain object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function sumtrain_view($sumtrain, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $sumtrain->id
    );

    $event = \mod_sumtrain\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('sumtrain', $sumtrain);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if a sumtrain is available for the current user.
 *
 * @param  stdClass  $sumtrain            sumtrain record
 * @return array                       status (available or not and possible warnings)
 */
function sumtrain_get_availability_status($sumtrain) {
    $available = true;
    $warnings = array();

    $timenow = time();

    if (!empty($sumtrain->timeopen) && ($sumtrain->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($sumtrain->timeopen);
    } else if (!empty($sumtrain->timeclose) && ($timenow > $sumtrain->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($sumtrain->timeclose);
    }
    if (!$sumtrain->allowupdate && sumtrain_get_my_response($sumtrain)) {
        $available = false;
        $warnings['sumtrainsaved'] = '';
    }

    // sumtrain is available.
    return array($available, $warnings);
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every sumtrain event in the site is checked, else
 * only sumtrain events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance sumtrain module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function sumtrain_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/sumtrain/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('sumtrain', array('id' => $instance), '*', MUST_EXIST);
        }
        sumtrain_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $sumtrains = $DB->get_records("sumtrain", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $sumtrains = $DB->get_records("sumtrain")) {
            return true;
        }
    }

    foreach ($sumtrains as $sumtrain) {
        sumtrain_set_events($sumtrain);
    }
    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function sumtrain_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB;

    $updates = new stdClass();
    $sumtrain = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    list($available, $warnings) = sumtrain_get_availability_status($sumtrain);
    if (!$available) {
        return $updates;
    }

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    if (!sumtrain_can_view_results($sumtrain)) {
        return $updates;
    }
    // Check if there are new responses in the sumtrain.
    $updates->answers = (object) array('updated' => false);
    $select = 'sumtrainid = :id AND timemodified > :since';
    $params = array('id' => $sumtrain->id, 'since' => $from);
    $answers = $DB->get_records_select('sumtrain_answers', $select, $params, '', 'id');
    if (!empty($answers)) {
        $updates->answers->updated = true;
        $updates->answers->itemids = array_keys($answers);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_sumtrain_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       int $userid = 0) {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['sumtrain'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $now = time();

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < $now) {
        // The sumtrain has closed so the user can no longer submit anything.
        return null;
    }

    // The sumtrain is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeopen']) || $cm->customdata['timeopen'] <= $now);

    if ($actionable && sumtrain_get_user_response((object)['id' => $event->instance], $userid)) {
        // There is no action if the user has already submitted their sumtrain.
        return null;
    }

    return $factory->create_instance(
        get_string('viewsumtrains', 'sumtrain'),
        new \moodle_url('/mod/sumtrain/view.php', array('id' => $cm->id)),
        1,
        $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $sumtrain The module instance to get the range from
 */
function mod_sumtrain_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $sumtrain) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == sumtrain_EVENT_TYPE_OPEN) {
        if (!empty($sumtrain->timeclose)) {
            $maxdate = [
                $sumtrain->timeclose,
                get_string('openafterclose', 'sumtrain')
            ];
        }
    } else if ($event->eventtype == sumtrain_EVENT_TYPE_CLOSE) {
        if (!empty($sumtrain->timeopen)) {
            $mindate = [
                $sumtrain->timeopen,
                get_string('closebeforeopen', 'sumtrain')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the sumtrain module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the sumtrain instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $sumtrain The module instance to get the range from
 */
function mod_sumtrain_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $sumtrain) {
    global $DB;

    if (!in_array($event->eventtype, [sumtrain_EVENT_TYPE_OPEN, sumtrain_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'sumtrain') {
        return;
    }

    if ($sumtrain->id != $instanceid) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == sumtrain_EVENT_TYPE_OPEN) {
        // If the event is for the sumtrain activity opening then we should
        // set the start time of the sumtrain activity to be the new start
        // time of the event.
        if ($sumtrain->timeopen != $event->timestart) {
            $sumtrain->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == sumtrain_EVENT_TYPE_CLOSE) {
        // If the event is for the sumtrain activity closing then we should
        // set the end time of the sumtrain activity to be the new start
        // time of the event.
        if ($sumtrain->timeclose != $event->timestart) {
            $sumtrain->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $sumtrain->timemodified = time();
        // Persist the instance changes.
        $DB->update_record('sumtrain', $sumtrain);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_sumtrain_get_fontawesome_icon_map() {
    return [
        'mod_sumtrain:row' => 'fa-info',
        'mod_sumtrain:column' => 'fa-columns',
    ];
}

/**
 * Add a get_coursemodule_info function in case any sumtrain type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function sumtrain_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$sumtrain = $DB->get_record('sumtrain', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $sumtrain->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('sumtrain', $sumtrain, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $sumtrain->completionsubmit;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($sumtrain->timeopen) {
        $result->customdata['timeopen'] = $sumtrain->timeopen;
    }
    if ($sumtrain->timeclose) {
        $result->customdata['timeclose'] = $sumtrain->timeclose;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_sumtrain_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'sumtrain');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Callback to fetch the activity event type lang string.
 *
 * @param string $eventtype The event type.
 * @return lang_string The event type lang string.
 */
function mod_sumtrain_core_calendar_get_event_action_string(string $eventtype): string {
    $modulename = get_string('modulename', 'sumtrain');

    switch ($eventtype) {
        case sumtrain_EVENT_TYPE_OPEN:
            $identifier = 'calendarstart';
            break;
        case sumtrain_EVENT_TYPE_CLOSE:
            $identifier = 'calendarend';
            break;
        default:
            return get_string('requiresaction', 'calendar', $modulename);
    }

    return get_string($identifier, 'sumtrain', $modulename);
}
