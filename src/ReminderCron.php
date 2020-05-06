<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'ReminderManager.php';


$sub = isset($_GET['s']) ? $_GET['s'] : "";

$module->emLog("------- Starting Repeating Survey Portal:  Reminder Cron for  $project_id with config sub-setting $sub-------");
echo "------- Starting Repeating Survey Portal:  Reminder Cron for $project_id with config sub-setting $sub-------";

//This page is only triggered if the 'Enable Reminders' checkbox has been checked for this subsetting in the Survey Portal config

//check if this $sub is portal enabled
$enabled = $module->getSubSettings('survey-portals')[$sub]['enable-portal'];
if (! $enabled ) {
    $module->emLog("Subsetting $sub is not enabled. Not sending reminders.");
    exit;
}

// Process
try {
    $inviteMgr = new ReminderManager($module->getProjectId(), $sub);
    $inviteMgr->sendReminders($sub);
    $msg = "COMPLETED Sub $sub for project " . $module->getProjectId();
    echo $msg;
    $module->emDebug($msg);
} catch (\Exception $e) {
    $module->emError("Excepting in InvitationManager " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}