<?php

require_once("../../config.php");
require_once("lib.php");

$value = $_GET['value'];
// Do something with the selected value, such as update a database or generate a response
global $DB;
$response = "You selected: " . $value; 

$sql= "select s.sessionid, concat((DATE_FORMAT(start_date, '%a %e %b %Y')), '- available:' 
    , (s.max_students - (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid)))
    as available_sessions
    from mdl_summtrain_session as s
    where s.max_students>= (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid)
    and s.institute= '{$value}'
    and s.year= '2023'
    and s.student_groups= 'Cohort 2022-2027'
    and (s.max_students - (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid)) > 0";
$options= $DB->get_records_sql_menu($sql);
header('Content-Type: application/json');
echo json_encode($options);