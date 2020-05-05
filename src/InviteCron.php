<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'InvitationManager.php';

// $bt = debug_backtrace();
// $module->emDebug("INVITATION_CRON", $_REQUEST, $bt);

$sub = isset($_GET['s']) ? $_GET['s'] : "";

$module->emLog("Starting Invitation Manager for " . $module->getProjectId() . " with config $sub");
// echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------";

//check if this $sub is enabled
$enabled = $module->getSubSettings('survey-portals')[$sub]['enable-portal'];
if (! $enabled ) {
    $module->emLog("Subsetting $sub is not enabled -- skipping");
    exit;
}

// Process
try {
    $inviteMgr = new InvitationManager($module->getProjectId(), $sub);
    $inviteMgr->sendInvitations($sub);
    echo "COMPLETED Sub $sub for project " . $module->getProjectId();
} catch (\Exception $e) {
    $this->emError("Excepting in InvitationManager " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}