<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    $PAGE->set_url('/mod/sumtrain/index.php', array('id'=>$id));

    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        throw new \moodle_exception('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');

    $eventdata = array('context' => context_course::instance($id));
    $event = \mod_sumtrain\event\course_module_instance_list_viewed::create($eventdata);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    $strsumtrain = get_string("modulename", "sumtrain");
    $strsumtrains = get_string("modulenameplural", "sumtrain");
    $PAGE->set_title($strsumtrains);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strsumtrains);
    echo $OUTPUT->header();

    if (! $sumtrains = get_all_instances_in_course("sumtrain", $course)) {
        notice(get_string('thereareno', 'moodle', $strsumtrains), "../../course/view.php?id=$course->id");
    }

    $usesections = course_format_uses_sections($course->format);

    $sql = "SELECT cha.*
              FROM {sumtrain} ch, {sumtrain_answers} cha
             WHERE cha.sumtrainid = ch.id AND
                   ch.course = ? AND cha.userid = ?";

    $answers = array () ;
    if (isloggedin() and !isguestuser() and $allanswers = $DB->get_records_sql($sql, array($course->id, $USER->id))) {
        foreach ($allanswers as $aa) {
            $answers[$aa->sumtrainid] = $aa;
        }
        unset($allanswers);
    }


    $timenow = time();

    $table = new html_table();

    if ($usesections) {
        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $table->head  = array ($strsectionname, get_string("question"), get_string("answer"));
        $table->align = array ("center", "left", "left");
    } else {
        $table->head  = array (get_string("question"), get_string("answer"));
        $table->align = array ("left", "left");
    }

    $currentsection = "";

    foreach ($sumtrains as $sumtrain) {
        if (!empty($answers[$sumtrain->id])) {
            $answer = $answers[$sumtrain->id];
        } else {
            $answer = "";
        }
        if (!empty($answer->optionid)) {
            $aa = format_string(sumtrain_get_option_text($sumtrain, $answer->optionid));
        } else {
            $aa = "";
        }
        if ($usesections) {
            $printsection = "";
            if ($sumtrain->section !== $currentsection) {
                if ($sumtrain->section) {
                    $printsection = get_section_name($course, $sumtrain->section);
                }
                if ($currentsection !== "") {
                    $table->data[] = 'hr';
                }
                $currentsection = $sumtrain->section;
            }
        }

        //Calculate the href
        if (!$sumtrain->visible) {
            //Show dimmed if the mod is hidden
            $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$sumtrain->coursemodule\">".format_string($sumtrain->name,true)."</a>";
        } else {
            //Show normal if the mod is visible
            $tt_href = "<a href=\"view.php?id=$sumtrain->coursemodule\">".format_string($sumtrain->name,true)."</a>";
        }
        if ($usesections) {
            $table->data[] = array ($printsection, $tt_href, $aa);
        } else {
            $table->data[] = array ($tt_href, $aa);
        }
    }
    echo "<br />";
    echo html_writer::table($table);

    echo $OUTPUT->footer();


