<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-02-27
 * Time: 09:32
 */

namespace Stanford\RepeatingSurveyPortal;


use REDCap;
use DateTime;
use DateInterval;
use Exception;
use Message;

require_once $module->getModulePath() . 'InvitationManager.php';

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Portal $Portal */

/** @var  Stanford\RepeatingSurveyPortal\PortalConfig $portalConfig */
class ReminderManager extends InvitationManager
{
    public $portalConfig;

    public $configID;

    public $project_id;

    public function __construct($project_id, $sub)
    {
        global $module;

        $this->project_id = $project_id;

        //get the config id from the passed in hash
        $this->configID = $module->getConfigIDFromSubID($sub);

        if ($this->configID != null) {

            $this->portalConfig = new PortalConfig($this->configID);
        } else {
            $module->emError("Cron job to send reminders attempted for a non-existent configId: " . $this->configID .
                " in this subsetting :  " . $sub);
        }

        //$module->emDebug("in construct with ". $project_id, $sub, $this->configID, $this->portalConfig); exit;
    }

    /**
     *
     * TODO: use the reminder valid day array
     * @param $sub
     */
    public function sendReminders($sub)
    {
        global $module;

        //sanity check that the subsetting matches the stored portalConfig;
        if ($sub != $this->portalConfig->subSettingID) {
            $module->emError("Wrong subsetting received while sending Reminders from cron");
        }

        $candidates = $this->getInviteCandidates();

        if (empty($candidates)) {
            $module->emLog("No candidates to send reminders for project: " . $this->project_id . " today: " . date('Y-m-d'));
            return;
        }

        //check that reminderLag is set
        if (!isset($this->portalConfig->reminderLag)) {
            $module->emError('Attempting to send reminders, but reminderLag is not set in the config');
            return null;
        }

        //calculate the target day
        $lagged_day = new DateTime();
        $lagged_day->sub(new DateInterval('P' . $this->portalConfig->reminderLag . 'D'));
        $lagged_str = $lagged_day->format('Y-m-d');


        foreach ($candidates as $candidate) {

            //check that today is a valid reminder day
            $valid_day = $this->checkIfDateValid($candidate[$this->portalConfig->startDateField], $this->portalConfig->reminderValidDayArray, $lagged_str);

            if ($valid_day != null) {

                //create a Participant object for the candidate and get the survey_status array
                try {
                    $participant = new Participant($this->portalConfig, $candidate[$this->portalConfig->personalHashField]);
                } catch (Exception $e) {
                    $this->emError($e);
                    continue;
                }


                //check that the survey has not already been completed
                if ($participant->isSurveyComplete($lagged_day)) {
                    $module->emDebug("Survey for $valid_day is already complete. Don't send invite for today");
                    continue;
                }
//
//                //create array of valid dates and completion status (include allowed lag)
//                $valid_dates = $participant->getValidDates();
//                //$module->emDebug($valid_dates); exit;
//
//                //for each incomplete survey, fire off a reminder if a reminder day
//                foreach ($valid_dates as $date_str => $status) {
//
//                    if ($status['STATUS'] != 2) {
//                        $valid_day = $status['DAY_NUMBER'];

                //send a reminder email
                $survey_link = $candidate[$this->portalConfig->personalUrlField] . "&d=" . $valid_day;
                $module->emDebug($survey_link, $candidate[$this->portalConfig->disableParticipantEmailField . "___1"], $candidate[$this->portalConfig->emailField]);

                //send invite to email OR SMS

                if (($candidate[$this->portalConfig->disableParticipantEmailField . "___1"] <> '1') &&
                    ($candidate[$this->portalConfig->emailField] <> '')) {


                    $module->emDebug("Sending email reminder to " . $candidate[REDCap::getRecordIdField()]);

                    $msg = $this->formatEmailMessage($this->portalConfig->reminderEmailText, $survey_link);

                    //send email

                    $send_status = $this->sendEmail(
                        $candidate[$this->portalConfig->emailField],
                        $this->portalConfig->reminderEmailFrom,
                        $this->portalConfig->reminderEmailSubject,
                        $msg);

                    //TODO: log send status to REDCap Logging?

                    REDCap::logEvent(
                        "Email Reminder Sent from Survey Portal EM",  //action
                        "Email sent to " . $candidate[$this->portalConfig->emailField] . " with status " .$send_status,  //changes
                        NULL, //sql optional
                        $participant->participantID, //record optional
                        $this->portalConfig->surveyEventName, //event optional
                        $this->project_id //project ID optional
                    );


                }

                if (($candidate[$this->portalConfig->disableParticipantSMSField . "___1"] <> '1') &&
                    ($candidate[$this->portalConfig->phoneField] <> '')) {
                    $module->emDebug("Sending text reminder to " . $candidate[REDCap::getRecordIdField()]);
                    //TODO: implement text sending of URL
                    $msg = $this->formatTextMessage($this->portalConfig->reminderSMSText, $survey_link);

                    //$sms_status = $this->sms_messager->sendText($candidate[$phone_field], $msg);
                    //$twilio_status = $text_manager->sendSms($candidate[$phone_field], $msg);
                    $twilio_status = $module->emText($candidate[$this->portalConfig->phoneField], $msg);

                    if (!$twilio_status) {
                        $this->emError("TWILIO Failed to send to " . $candidate[$this->portalConfig->phoneField] . " with status " . $twilio_status);
                        REDCap::logEvent(
                            "Text Reminder Failed to send from Survey Portal EM",  //action
                            "Text failed to send to " . $candidate[$this->portalConfig->phoneField] . $twilio_status,  //changes
                            NULL, //sql optional
                            $participant->participantID, //record optional
                            $this->portalConfig->surveyEventName, //event optional
                            $this->project_id //project ID optional
                        );
                    } else {
                        REDCap::logEvent(
                            "Text Reminder Sent from Survey Portal EM",  //action
                            "Text sent to " . $candidate[$this->portalConfig->phoneField],  //changes
                            NULL, //sql optional
                            $participant->participantID, //record optional
                            $this->portalConfig->surveyEventName, //event optional
                            $this->project_id //project ID optional
                        );
                    }
                }
            }
        }
    }

    /**
     *
     * pass $date_str as null if wnat to check for today
     * @param $start_str
     * @param $valid_day_number
     * @param null $date_str
     * @param $date_lag
     * @return |null
     * @throws Exception
     */
    public function checkIfReminderDateValid($start_str, $valid_day_number, $date_str = null, $date_lag = 0)
    {
        global $module;

        $lagged_day = new DateTime($date_str); //today if null is passed
        $lagged_day->sub(new DateInterval('P' . $date_lag . 'D'));
        $lagged_str = $lagged_day->format('Y-m-d');

        $module->emDebug($lagged_str, $date_lag, "DATE LAG");

        //use parent method on new date
        return $this->checkIfDateValid($start_str, $valid_day_number, $lagged_str);

    }
}

