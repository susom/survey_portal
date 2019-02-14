<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */



$sub = isset($_GET['s']) ? $_GET['s'] : "";

$module->emLog("------- Starting Repeating Survey Portal:  Invitation Cron for  $project_id with config sub-setting $sub-------");
echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------";
$module->sendInvitations($sub);