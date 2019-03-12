<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once 'InvitationManager.php';

$sub = isset($_GET['s']) ? $_GET['s'] : "";

$module->emLog("------- Starting Repeating Survey Portal:  Invitation Cron for  $project_id with config sub-setting $sub-------");
echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------";

$inviteMgr = new InvitationManager($project_id, $sub);

if (isset($inviteMgr)) {
    $inviteMgr->sendInvitations($sub);
}