<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'ReminderManager.php';


$sub = isset($_GET['s']) ? htmlspecialchars($_GET['s']) : "";

$module->emLog("------- Starting Repeating Survey Portal:  Reminder Cron config sub-setting $sub-------");
//echo "------- Starting Repeating Survey Portal:  Reminder Cron for $project_id with config sub-setting $sub-------";

//This page is only triggered if the 'Enable Reminders' checkbox has been checked for this subsetting in the Survey Portal config

//check if this $sub is portal enabled
$enabled = $module->getSubSettings('survey-portals')[$sub]['enable-portal'];
$reminder_enabled = $module->getSubSettings('survey-portals')[$sub]['enable-reminders'];

//check if either the emails or texts have been disabled for this portal
$text_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-texts'];
$email_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-emails'];

//for reminders, reminder-lag needs to be populated
$reminder_lag = $module->getSubSettings('survey-portals')[$sub]['reminder-lag'];


if ((! $enabled ) || (! $reminder_enabled) || (!isset($reminder_lag))|| (($text_disabled === true) && ($email_disabled===true))) {
    if (!isset($reminder_lag)) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub : reminder_lag is not set -- not sending reminders";
    }
    if (! $enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub : enable_portal is not enabled -- not sending reminders";
    }
    if (! $reminder_enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub : reminder_enabled is not enabled -- not sending reminders";
    }
    if (($text_disabled === true) && ($email_disabled===true)) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub : Text (disable_texts) and Email (disable_emails) both disabled -- not sending reminders";
    }

    $module->emLog($err_1);
    echo $err_1;
    exit;
}

// Process
try {
    $remindMgr = new ReminderManager($module->getProjectId(), $sub);
    //$remindMgr->sendReminders($sub);
    $remindMgr->sendInvitations($sub);
    $msg = "COMPLETED Sub $sub for project " . $module->getProjectId();
    echo $msg;
    $module->emDebug($msg);
} catch (\Exception $e) {
    $module->emError("ReminderManager unable to start" . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}