<?php

namespace Stanford\RepeatingSurveyPortal;

use REDCap;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

$url = $module->getUrl('web/InviteCron.php', true, true);
echo "<br><br>This is the InviteCron Link: <br>".$url . "&s=0";
echo "<br> Check that subsetting parameter is correct for your test.";

$url = $module->getUrl('web/ReminderCron.php', true, true);
echo "<br><br>This is the ReminderCron Link: <br>".$url . "&s=0";
echo "<br> Check that subsetting parameter is correct for your test.";