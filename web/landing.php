<?php


namespace Stanford\RepeatingSurveyPortal;

use REDCap;
use DateTime;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

require_once $module->getModulePath() . 'Portal.php';



/** @var \Stanford\RepeatingSurveyPortal\Portal $portal */
/** @var \Stanford\RepeatingSurveyPortal\Participant $participant */

$module->emDebug("===== Firing up the landing page ====");


$config_ids = $module->getProjectSetting('config-id');


$p_hash = isset($_REQUEST['h']) ? $_REQUEST['h'] : "";
$p_config = isset($_REQUEST['c']) ? $_REQUEST['c'] : "";
$p_daynumber = isset($_REQUEST['d']) ? $_REQUEST['d'] : "";


$portal = new Portal($p_config, $p_hash);
$participant = $portal->getParticipant();

/**
 * How do we handle the content of hte landing page?
 *
 * What is encoded in the url for a landing page?
 *  - pid
 *  - record  |  hash??
 *  - which config (if multiple)
 *  - day number (optional) - from invitations
 * https://redcap.stanford.edu/api/?type=module&id=11&page=web%20Portal.php&pid=12067&record=1234&config=?      &day=x
 *
 *
 * Personal URL:
 *  https://redcap.stanford.edu/api/?type=module&id=11&page=web%20Portal.php&pid=12067 + h = XXXX
 * Issues - need to obfuscate record...
 *
 * 1) each url generated is unique, we store it in the em_logs with parameters....
 * 1. retrieve record for this hash
 *
 *
 * If Autostart:
 *
 *
 *  1) if day number is not set, then calculate day number from start date nad current date
 *
 *      IF day number is present, then use that day number
 *
 * 2) Validate current time window for daynumber
 *
 * 3) Create survey for date and redirect
 *
 * If Not Autostart, then Calendar (//todo: is there another option?
 *
 * 1) Configure all teh Calendar options
 *    (a) show-missing-day-buttons
 *    (b)
 *
 * 2) if date is selected, validate time window, create survey, redirect
 */

/**
 * if auto_start,
 *    if daynumber set, confirm valid day, and start
 *    if daynumber NOT set, find day number, confirm valid day, and start
 *
 * if calendar, display calendar
 * if NOT calendar, display try later message
 */
$today = new DateTime();
$error_msg = null;

if ($portal->autoStartSurvey) {
    $module->emDebug("Autostarting Survey");
    if ($p_daynumber == "") {
        $module->emDebug("No day number is set, so find day number, confirm valid day and start");

        //Given today's date, get daynumber
        $day_number = $participant->getDayNumberFromDate($today);
        $survey_date = $today;
        $module->emDebug("Day number for " . $today->format('Y-m-d') . " is " . $day_number);

        if ($day_number == null) {
            $error_msg[] = "This day number is not a valid day number. It is not in the range.";
        }


    } else {

        $day_number = intval($p_daynumber);
        $survey_date = $participant->getSurveyDateFromDayNumber($day_number);
        $module->emDebug($survey_date, "Day number is set, so confirm in allowed window and start: " . $day_number);
    }

    //confirm valid window
    if (!$portal->validTimeWindow($survey_date)) {
        $error_msg[] = "This day is not a valid window.";
    }


    $module->emDebug($error_msg, ($error_msg != null));
    //$survey_date_str = $survey_date->format('Y-m-d');

    if ($error_msg == null) {
        $next_id = $participant->max_instance + 1;
        //$module->emDebug($participant->max_instance,"NEXT ID IS ".$next_id );exit;
        //setup survey link for the correct survey
        //prefill new survey with day_number / date/
        $participant->newSurveyEntry($day_number, $survey_date);


        // surveyDayNumberField: Day number
        // surveyDateField : this should be day of day number not actual day
        $survey_link = REDCap::getSurveyLink($participant->participant_id, $participant->surveyInstrument, $participant->surveyEventName,
            $next_id);

        //$module->emDebug($participant->participant_id, $participant->surveyInstrument, $participant->surveyEventName, $survey_link, "SURVEY LINK");
        //start
        header("Location: " . $survey_link);
        exit;
    }


} elseif ($portal->showCalendar) {


} else {
    $module->emDebug("Display error message");
    $error_msg[] = "There is no survey to auto-start today.";
}

$module->emDebug($error_msg, ($error_msg != null));


/**
 * // METHOD:   x()
 * verify hash and personal url for record (called by save_record hook)
 *
 *
 * // METHOD:  xx ($hash)
 * retrieve record based on hash or record+config
 *
 * //METHOD:    xxx ($record, $config)
 * calculate day number by retrieving start date and calculating against current date
 *
 * //METHOD:   xxxx($record, $config, $daynumber)
 * validate window (time) for current time
 *
 *
 *
 */


?>

    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Survey Portal
        </title>
        <link rel="stylesheet" href="<?php echo $module->getUrl('css/bootstrap.min.css', true, true) ?>"
              type="text/css"/>
        <link rel="stylesheet" href="<?php echo $module->getUrl('css/survey_portal.css', true, true) ?>"
              type="text/css"/>

        <link href='https://fonts.googleapis.com/css?family=Oxygen:400,300,700' rel='stylesheet' type='text/css'>
        <link href='https://fonts.googleapis.com/css?family=Lora' rel='stylesheet' type='text/css'>
    </head>
    <body>

    <div id="project_title" alt="Project Title"></div>

    <div id="error_msg" class="alert alert-danger" role="alert" style="display:none;">
        <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
        <span class="sr-only">Error:</span>
        <?php echo implode("<br>", $error_msg) ?>
    </div>

    <div id="calendar_widget" alt="Calendar Widget"></div>

    <footer class="panel-footer">

    </footer>
    <script src="<?php echo $module->getUrl('js/jquery-3.2.1.min.js', true, true) ?>"></script>
    <script src="<?php echo $module->getUrl('js/bootstrap.min.js', true, true) ?>"></script>
    <script src="<?php echo $module->getUrl('js/survey_portal.js', true, true) ?>"></script>
    </body>
    </html>
    <script>

        // Convenience function for inserting innerHTML for 'select'
        var insertHtml = function (selector, html) {
            var targetElem = document.querySelector(selector);
            targetElem.innerHTML = html;
        };

        $(document).ready(function () {
            <?php if ($error_msg != null) { ?>
            $('#error_msg').show();

            <?php }?>
            //fold in project_title into 'project_title' div
            insertHtml("#project_title", '<?php echo $portal->landingPageHeader;?>');

        });

    </script>
<?php

