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
use Exception;
use Message;

require_once $module->getModulePath().'InvitationManager.php';

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Portal $Portal */
/** @var  Stanford\RepeatingSurveyPortal\PortalConfig $portalConfig */

class ReminderManager extends InvitationManager {
  public $portalConfig;

    public $configID;

    public function __construct($project_id, $sub)  {
        global $module;

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

        foreach ($candidates as $candidate) {
            //create a Participant object for the candidate and get the survey_status array
              try {
                  $participant = new Participant($this->portalConfig, $candidate[$this->portalConfig->personalHashField]);
              } catch (Exception $e) {
                  $this->emError($e);
                  continue;
              }
        }

        //create array of valid dates and completion status (include allowed lag)
        $valid_dates = $participant->getValidDates();
        //$module->emDebug($valid_dates); exit;

        //for each incomplete survey, fire off a reminder if a reminder day
        foreach ($valid_dates as $date_str => $status) {

            if ($status['STATUS'] != 2) {
                $valid_day = $status['DAY_NUMBER'];
                //send a reminder email
                $survey_link = $candidate[$this->portalConfig->personalUrlField]."&d=" . $valid_day;
                $module->emDebug($survey_link, $candidate[$this->portalConfig->disableParticipantEmailField."___1"],$candidate[$this->portalConfig->emailField]);

                //send invite to email OR SMS

                if (($candidate[$this->portalConfig->disableParticipantEmailField."___1"] <> '1') &&
                    ($candidate[$this->portalConfig->emailField] <> '')) {


                    $module->emDebug("Sending email reminder to ".$candidate[REDCap::getRecordIdField()]);

                    $msg = $this->formatEmailMessage($this->portalConfig->reminderEmailText, $survey_link);

                    //send email

                    $send_status = $this->sendEmail(
                        $candidate[$this->portalConfig->emailField],
                        $this->portalConfig->reminderEmailFrom,
                        $this->portalConfig->reminderEmailSubject,
                        $msg);


                    //TODO: log send status to REDCap Logging?

                }

                if (($candidate[$this->portalConfig->disableParticipantSMSField."___1"] <> '1') &&
                        ($candidate[$this->portalConfig->phoneField] <> '')) {
                    $module->emDebug("Sending text reminder to ".$candidate[REDCap::getRecordIdField()]);
                    //TODO: implement text sending of URL
                    $msg = $this->formatTextMessage($this->portalConfig->reminderSMSText, $survey_link);

                    //$sms_status = $this->sms_messager->sendText($candidate[$phone_field], $msg);
                    //$twilio_status = $text_manager->sendSms($candidate[$phone_field], $msg);
                    $twilio_status = $module->emText($candidate[$this->portalConfig->phoneField], $msg);

                    if (!$twilio_status) {
                        $this->emError("TWILIO Failed to send to ". $candidate[$this->portalConfig->phoneField] . " with status ". $twilio_status);
                    }



                }






            }
        }
    }

}