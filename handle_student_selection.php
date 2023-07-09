<?php

require_once("../../config.php");
require_once("lib.php");
global $DB, $CFG, $USER;

$results= [];
$exists= 1;
$sql= '';

$username = $USER->username;
$institute = $_GET['institute'];
$session = $_GET['session'];


// Do something with the selected value, such as update a database or generate a response

// check if selection is still available, if not, return applogize.
$sql= "select s.max_students - (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid) as available_sessions
    from mdl_summtrain_session as s
    where s.max_students>= (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid)
    and s.institute= '{$institute}'
    and s.sessionid= {$session}
    and s.year= '2023'
    and s.student_groups= 'Cohort 2022-2027'";
$res= $DB->get_records_sql($sql);
$available= array_keys($res)[0];

if ($available> 0) {
    // check if user already have selection. if so, update, else insert.
    $sql= "select ss.username as name_exists from mdl_summtrain_session_student as ss where ss.username='{$username}' and ss.year= '2023'";
    $res= $DB->get_records_sql($sql); 
    $exists= (count($res));

    if ($exists > 0) {
        // update
        $sql= "update mdl_summtrain_session_student
            set sessionid= {$session}
            where username= '{$username}'
            and year='2023'";
    }
    else {
        // insert
        $sql= "insert into mdl_summtrain_session_student(username, sessionid) values('{$username}', {$session})";        
    }
    $ret= $DB->execute($sql);
    $results[0]= 0;
    $results[1]= "Successfully registered in summar train session";
}
else{
    $results[0]= 1;
    $results[1]= "This option is not available anymore. Please select a new session";
}

$response =$results;
echo json_encode($response, JSON_UNESCAPED_UNICODE);