<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

$id         = required_param('id', PARAM_INT);                 // Course Module ID
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
$userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose sumtrains need to be modified.
$notify     = optional_param('notify', '', PARAM_ALPHA);

$url = new moodle_url('/mod/sumtrain/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('sumtrain', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}
$cm = cm_info::create($cm);

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    throw new \moodle_exception('coursemisconf');
}

require_course_login($course, false, $cm);

if (!$sumtrain = sumtrain_get_sumtrain($cm->instance)) {
    throw new \moodle_exception('invalidcoursemodule');
}

$strsumtrain = get_string('modulename', 'sumtrain');
$strsumtrains = get_string('modulenameplural', 'sumtrain');

$context = context_module::instance($cm->id);

list($sumtrainavailable, $warnings) = sumtrain_get_availability_status($sumtrain);

if ($action == 'delsumtrain' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/sumtrain:choose') and $sumtrain->allowupdate
        and $sumtrainavailable) {
    $answercount = $DB->count_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $USER->id));
    if ($answercount > 0) {
        $sumtrainanswers = $DB->get_records('sumtrain_answers', array('sumtrainid' => $sumtrain->id, 'userid' => $USER->id),
            '', 'id');
        $todelete = array_keys($sumtrainanswers);
        sumtrain_delete_responses($todelete, $sumtrain, $cm, $course);
        redirect("view.php?id=$cm->id");
    }
}

$PAGE->set_title($sumtrain->name);
$PAGE->set_heading($course->fullname);

/// Submit any new data if there is any
if (data_submitted() && !empty($action) && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/sumtrain:deleteresponses', $context)) {
        if ($action === 'delete') {
            // Some responses need to be deleted.
            sumtrain_delete_responses($attemptids, $sumtrain, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            sumtrain_modify_responses($userids, $attemptids, $newoptionid, $sumtrain, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
    }

    // Redirection after all POSTs breaks block editing, we need to be more specific!
    if ($sumtrain->allowmultiple) {
        $answer = optional_param_array('answer', array(), PARAM_INT);
    } else {
        $answer = optional_param('answer', '', PARAM_INT);
    }

    if (!$sumtrainavailable) {
        $reason = current(array_keys($warnings));
        throw new moodle_exception($reason, 'sumtrain', '', $warnings[$reason]);
    }

    if ($answer && is_enrolled($context, null, 'mod/sumtrain:choose')) {
        sumtrain_user_submit_response($answer, $sumtrain, $USER->id, $course, $cm);
        redirect(new moodle_url('/mod/sumtrain/view.php',
            array('id' => $cm->id, 'notify' => 'sumtrainsaved', 'sesskey' => sesskey())));
    } else if (empty($answer) and $action === 'makesumtrain') {
        // We cannot use the 'makesumtrain' alone because there might be some legacy renderers without it,
        // outdated renderers will not get the 'mustchoose' message - bad luck.
        redirect(new moodle_url('/mod/sumtrain/view.php',
            array('id' => $cm->id, 'notify' => 'mustchooseone', 'sesskey' => sesskey())));
    }
}

// Completion and trigger events.
sumtrain_view($sumtrain, $course, $cm, $context);

$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();

if ($notify and confirm_sesskey()) {
    if ($notify === 'sumtrainsaved') {
        echo $OUTPUT->notification(get_string('sumtrainsaved', 'sumtrain'), 'notifysuccess');
    } else if ($notify === 'mustchooseone') {
        echo $OUTPUT->notification(get_string('mustchooseone', 'sumtrain'), 'notifyproblem');
    }
}

/// Display the sumtrain and possibly results
$eventdata = array();
$eventdata['objectid'] = $sumtrain->id;
$eventdata['context'] = $context;

/// Check to see if groups are being used in this sumtrain
$groupmode = groups_get_activity_groupmode($cm);

// Check if we want to include responses from inactive users.
$onlyactive = $sumtrain->includeinactive ? false : true;

$allresponses = sumtrain_get_response_data($sumtrain, $cm, $groupmode, $onlyactive);   // Big function, approx 6 SQL calls per user.


if (has_capability('mod/sumtrain:readresponses', $context) && !$PAGE->has_secondary_navigation()) {
    sumtrain_show_reportlink($allresponses, $cm);
}

echo '<div class="clearer"></div>';

$timenow = time();
$current = sumtrain_get_my_response($sumtrain);
//if user has already made a selection, and they are not allowed to update it or if sumtrain is not open, show their selected answer.
if (isloggedin() && (!empty($current)) &&
    (empty($sumtrain->allowupdate) || ($timenow > $sumtrain->timeclose)) ) {
    $sumtraintexts = array();
    foreach ($current as $c) {
        $sumtraintexts[] = format_string(sumtrain_get_option_text($sumtrain, $c->optionid));
    }
    echo $OUTPUT->box(get_string("yourselection", "sumtrain") . ": " . implode('; ', $sumtraintexts), 'generalbox', 'yourselection');
}

/// Print the form
$sumtrainopen = true;
if ((!empty($sumtrain->timeopen)) && ($sumtrain->timeopen > $timenow)) {
    if ($sumtrain->showpreview) {
        echo $OUTPUT->box(get_string('previewing', 'sumtrain'), 'generalbox alert');
    } else {
        echo $OUTPUT->footer();
        exit;
    }
} else if ((!empty($sumtrain->timeclose)) && ($timenow > $sumtrain->timeclose)) {
    $sumtrainopen = false;
}

if ( (!$current or $sumtrain->allowupdate) and $sumtrainopen and is_enrolled($context, NULL, 'mod/sumtrain:choose')) {

    // Show information on how the results will be published to students.
    $publishinfo = null;
    switch ($sumtrain->showresults) {
        case sumtrain_SHOWRESULTS_NOT:
            $publishinfo = get_string('publishinfonever', 'sumtrain');
            break;

        case sumtrain_SHOWRESULTS_AFTER_ANSWER:
            if ($sumtrain->publish == sumtrain_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonafter', 'sumtrain');
            } else {
                $publishinfo = get_string('publishinfofullafter', 'sumtrain');
            }
            break;

        case sumtrain_SHOWRESULTS_AFTER_CLOSE:
            if ($sumtrain->publish == sumtrain_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonclose', 'sumtrain');
            } else {
                $publishinfo = get_string('publishinfofullclose', 'sumtrain');
            }
            break;

        default:
            // No need to inform the user in the case of sumtrain_SHOWRESULTS_ALWAYS since it's already obvious that the results are
            // being published.
            break;
    }

    // Show info if necessary.
    if (!empty($publishinfo)) {
        echo $OUTPUT->notification($publishinfo, 'info');
    }

    // They haven't made their sumtrain yet or updates allowed and sumtrain is open.
    $options = sumtrain_prepare_options($sumtrain, $USER, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_sumtrain');
    echo $renderer->display_options($options, $cm->id, $sumtrain->display, $sumtrain->allowmultiple);
    $sumtrainformshown = true;
} else {
    $sumtrainformshown = false;
}

if (!$sumtrainformshown) {
    $sitecontext = context_system::instance();

    if (isguestuser()) {
        // Guest account
        echo $OUTPUT->confirm(get_string('noguestchoose', 'sumtrain').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
    } else if (!is_enrolled($context)) {
        // Only people enrolled can make a sumtrain
        $SESSION->wantsurl = qualified_me();
        $SESSION->enrolcancel = get_local_referer(false);

        $coursecontext = context_course::instance($course->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<p align="center">'. get_string('notenrolledchoose', 'sumtrain') .'</p>';
        echo $OUTPUT->container_start('continuebutton');
        echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
        echo $OUTPUT->container_end();
        echo $OUTPUT->box_end();

    }
}

// print the results at the bottom of the screen
if (sumtrain_can_view_results($sumtrain, $current, $sumtrainopen)) {
    $results = prepare_sumtrain_show_results($sumtrain, $course, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_sumtrain');
    if ($results->publish) { // If set to publish full results, display a heading for the responses section.
        echo html_writer::tag('h3', format_string(get_string("responses", "sumtrain")), ['class' => 'mt-4']);
    }

    if ($groupmode) { // If group mode is enabled, display the groups selector.
        groups_get_activity_group($cm, true);
        $groupsactivitymenu = groups_print_activity_menu($cm, new moodle_url('/mod/sumtrain/view.php', ['id' => $id]),
            true);
        echo html_writer::div($groupsactivitymenu, 'mt-3 mb-1');
    }

    $resultstable = $renderer->display_result($results);
    echo $OUTPUT->box($resultstable);

} else if (!$sumtrainformshown) {
    echo $OUTPUT->box(get_string('noresultsviewable', 'sumtrain'));
}

echo $OUTPUT->footer();
