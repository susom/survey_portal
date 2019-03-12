<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'ReminderManager.php';


$sub = isset($_GET['s']) ? $_GET['s'] : "";

$module->emLog("------- Starting Repeating Survey Portal:  Reminder Cron for  $project_id with config sub-setting $sub-------");
echo "------- Starting Repeating Survey Portal:  Reminder Cron for $project_id with config sub-setting $sub-------";

$reminderMgr = new ReminderManager($project_id, $sub);

if (isset($reminderMgr)) {
    $reminderMgr->sendReminders($sub);
}

