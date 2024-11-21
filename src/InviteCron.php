<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'InvitationManager.php';

// $bt = debug_backtrace();
// $module->emDebug("INVITATION_CRON", $_REQUEST, $bt);

$sub = isset($_GET['s']) ? htmlspecialchars($_GET['s']) : "";

$module->emLog("Starting Invitation Manager for " . $module->getProjectId() . " with config $sub");
// echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------";

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

    $module->emLog($err_1);
    echo $err_1;
    exit;
}

// Process
try {
    $inviteMgr = new InvitationManager($module->getProjectId(), $sub);
    $inviteMgr->sendInvitations($sub);
    $msg = "COMPLETED Sub $sub for project " . $module->getProjectId();
    echo $msg;
    $module->emDebug($msg);
} catch (\Exception $e) {
    $module->emError("InvitationManager not started: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}