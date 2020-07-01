<?php

namespace Stanford\RepeatingSurveyPortal;

use DateTime;
use DateInterval;
use DatePeriod;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'InvitationManager.php';
require_once 'ReminderManager.php';

$begin = '';
$end = '';

$sub = isset($_GET['s']) ? $_GET['s'] : "0";

$module->emLog("Starting Invitation Manager for " . $module->getProjectId() . " with config $sub");
echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------<br>";
echo "To change the sub add &s=1<br>";

//check if this $sub is enabled
$enabled = $module->getSubSettings('survey-portals')[$sub]['enable-portal'];
$invite_enabled = $module->getSubSettings('survey-portals')[$sub]['enable-invitations'];

//check if either the emails or texts have been disabled for this portal
$text_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-texts'];
$email_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-emails'];

if ((! $enabled ) || (! $invite_enabled) ||  (($text_disabled == true) && ($email_disabled==true)) ){
    if (! $enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub is not enabled -- not sending invitations";
    }
    if (! $invite_enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Invitations for subsetting $sub is not enabled -- not sending invitations";
    }
    if (($text_disabled == true) && ($email_disabled==true)) {
        $err_1 = "Text and Email both disabled for project: ". $module->getProjectId() . " for subsetting $sub -- not sending invitations";
    }

    echo $err_1;
    exit;
}

try {
    $inviteMgr = new InvitationManager($module->getProjectId(), $sub);
    $count_msg = $inviteMgr->countInvitations($sub);
    $msg = "<COMPLETED Sub $sub for project " . $module->getProjectId();
    echo "<br>";
    echo $msg;
    echo "<br>";
    echo "Inactive window set to ".$module->getSubSettings('survey-portals')[$sub]['invitation-days-mod-inactivity'];
    foreach ($count_msg as $msg) {
        echo "<br>";
        echo $msg;
    }
} catch (\Exception $e) {
    $module->emError("InvitationManager not started: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}
echo "<br>";
echo "<br>";
echo "------- Starting Repeating Survey Portal:  Reminder Count for $project_id with config sub-setting $sub-------<br>";
//check if this $sub is enabled
$enabled = $module->getSubSettings('survey-portals')[$sub]['enable-portal'];
$reminder_enabled = $module->getSubSettings('survey-portals')[$sub]['enable-reminders'];

//check if either the emails or texts have been disabled for this portal
$text_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-texts'];
$email_disabled = $module->getSubSettings('survey-portals')[$sub]['disable-emails'];

if ((! $enabled ) || (! $reminder_enabled) ||  (($text_disabled == true) && ($email_disabled==true)) ){
    if (! $enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Subsetting $sub is not enabled -- not sending invitations";
    }
    if (! $reminder_enabled) {
        $err_1 = "PID ".$module->getProjectId(). " : Reminder for subsetting $sub is not enabled -- not counting reminders";
    }
    if (($text_disabled == true) && ($email_disabled==true)) {
        $err_1 = "Text and Email both disabled for project: ". $module->getProjectId() . " for subsetting $sub -- not counting reminders";
    }

    echo $err_1;
    exit;
}

try {
    $remindMgr = new ReminderManager($module->getProjectId(), $sub);
    $count_msg = $remindMgr->countInvitations($sub);
    $msg = "<COMPLETED Sub $sub for project " . $module->getProjectId();
    echo "<br>";
    echo $msg;
    echo "<br>";
    echo "Inactive window set to ".$module->getSubSettings('survey-portals')[$sub]['reminder-days-mod-inactivity'];
    foreach ($count_msg as $msg) {
        echo "<br>";
        echo $msg;
    }
} catch (\Exception $e) {
    $module->emError("ReminderManager not started: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}


