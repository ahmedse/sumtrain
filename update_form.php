<?php

require_once("../../config.php");
require_once("lib.php");
global $DB, $CFG, $USER;

$results= '';

$username = $USER->username;
$institute = "";
$session = "";
$results= [];

 // get user groups
 list($groups, $str, $batch)= getUserGroups();

 $sql= "select sessionid from mdl_summtrain_session_student as ss where ss.username='{$username}' and ss.year= '2023'";
 $res= $DB->get_records_sql($sql); 
 $exists= (count($res));

 if ($exists > 0) {   
    
     //if already regsitered, show details
     $sessionid= array_keys($res)[0];
     $sql= "select institute, concat((DATE_FORMAT(start_date, '%a %e %b %Y')), ' - group:', s.institute_group, '- available:' 
        , (s.max_students - (select count(ss.sessionid) from mdl_summtrain_session_student as ss where ss.sessionid= s.sessionid)))
        as session
        from mdl_summtrain_session as s
        where s.sessionid= {$sessionid}";

    $res= $DB->get_records_sql($sql); 

    foreach ($res as $r) {
        $institute = $r->institute;
        $session = $r->session;

        $ret= "Institute: $institute , Session=$session ";
    }
 }
 $results[0]= 0;
 $results[1]= $ret;

$response = $results; 
echo json_encode($response, JSON_UNESCAPED_UNICODE);