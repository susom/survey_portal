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

require_once 'InvitationManager.php';

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Portal $Portal */
/** @var  Stanford\RepeatingSurveyPortal\PortalConfig $portalConfig */

/**
 * Class called by ReminderCron job to evaluate date and participants and sends reminders by email/text
 *
 * Class ReminderManager
 * @package Stanford\RepeatingSurveyPortal
 */
class ReminderManager extends InvitationManager
{
    public $portalConfig;

    public $configID;

    public $project_id;

    private $email_from;
    private $email_subject;
    private $email_type; //Invite or Reminder
    private $email_text;
    private $email_url_label;
    private $sms_text;
    private $modifier_by_logic;
    private $allowed_inactive_days;
    private $valid_day_array;

    public $config_text_disabled;
    public $config_email_disabled;

    private $target_day;
    private $target_day_str;


    public function __construct($project_id, $sub) {
        global $module;

        $this->project_id = $project_id;

        //get the config id from the passed in hash
        $this->configID = $module->getConfigIDFromSubID($sub);

        if ($this->configID != null) {
            $this->portalConfig = new PortalConfig($this->configID);
        } else {
            $err_msg ="Cron job attempted to send reminders for a non-existent configId: ". $this->configID . " in this subsetting :  ". $sub;
            $module->emError($err_msg);
            throw new Exception($err_msg);
        }

        $this->email_from    = $this->portalConfig->reminderEmailFrom;
        $this->email_subject = $this->portalConfig->reminderEmailSubject;
        $this->email_type        = "Reminder";
        $this->email_text        = $this->portalConfig->reminderEmailText;
        $this->email_url_label   = $this->portalConfig->reminderUrlLabel;
        $this->sms_text          = $this->portalConfig->reminderSMSText;
        $this->modifier_by_logic = $this->portalConfig->reminderDaysModLog;
        $this->allowed_inactive_days = $this->portalConfig->reminderDaysModInactivity;

        $this->config_text_disabled = $this->portalConfig->disableTexts;
        $this->config_email_disabled = $this->portalConfig->disableEmails;

        $this->valid_day_array   = $this->portalConfig->reminderValidDayArray;

        //check that reminderLag is set
        if (!isset($this->portalConfig->reminderLag)) {
            $err_no_lag = "Attempting to send reminders, but reminderLag is not set in the config";
            $module->emError($err_no_lag);
            throw new Exception($err_no_lag);
        }

        //calculate the target day
        $this->target_day = new DateTime();
        $this->target_day->sub(new DateInterval('P' . $this->portalConfig->reminderLag . 'D'));
        $this->target_day_str = $this->target_day->format('Y-m-d');

        $module->emDebug("inactive is ".$this->allowed_inactive_days);
        $module->emDebug("target day is ".$this->target_day_str);

        //CRON JOB already checks that
        // enable-portal is set
        // enable-invitations is set
        // disable-texts and disable-emails ARE NOT both set
        // reminder-lag has no value
    }

    /******************************************************************************************************************
     *  METHODS FOR TESTING COUNT
     *
     ******************************************************************************************************************/


    /**
     *
     * @param $sub
     * @throws Exception
     */
    public function countReminders($sub)
    {
        global $module;

        $module->emDebug("inactive is ".$this->allowed_inactive_days);
        $module->emDebug("target day is ".$this->target_day_str);


        if ($sub != $this->portalConfig->subSettingID) {
            $module->emError("Wrong subsetting received while sending Invitations from cron");
            return;
        }

        $msgs= array();
        // search all records where config_id = ConfigID
        // AND email not null and not disabled
        /// OR phonenum not null and not idsabled
        //redo the search by SQL not by filter getData
        //$candidates = $this->getInviteReminderCandidates();
        $candidates = $this->getInviteReminderCandidates();
        $msgs[] = "Count by getData: ".count($candidates);

        $sql_result = $this->getInviteReminderCandidatesBySQL($this->allowed_inactive_days);
        //$not_empty = $this->getRecordsBySql();
        $msgs[] = "Count by sql on inactives only : ". $sql_result->num_rows;

        //count how many inactive there were
        $inactive = $this->getInactiveRecords($this->project_id, $this->allowed_inactive_days);
        $msgs[] = "Count of inactive records : ".$inactive->num_rows;
        $module->emDebug($msgs);
        return $msgs;
    }


    /******************************************************************************************************************
     *  METHODS
     *
     ******************************************************************************************************************/

    /**
     *
     * @param $sub
     * @throws Exception
     */
    public function sendReminders($sub) {
        global $module;


        if ($sub != $this->portalConfig->subSettingID) {
            $module->emError("Wrong subsetting received while sending Invitations from cron");
            return;
        }

        // search all records where config_id = ConfigID
        // AND email not null and not disabled
        /// OR phonenum not null and not idsabled
        //redo the search by SQL not by filter getData
        //$candidates = $this->getInviteReminderCandidates();
        $candidates = $this->getInviteReminderCandidatesBySQL($this->allowed_inactive_days);

        //report count for debugging:
        $module->emDebug("Count of {$this->email_type} retrieved for pid ". $this->project_id . " : ". $candidates->num_rows);

        if (empty($candidates)) {
            $module->emLog("No candidates to send invitations for project: ". $this->project_id . " today: ". date('Y-m-d'));
            return;
        }


        foreach ($candidates as $candidate) {

            //TODO: this is to test the doubled cron job
            //sleep(60);

            $rec_id = $candidate['record'];  //result of sql query so use the redcap_data column name
            $event_id = $candidate['event_id'];  //result of sql query so use the redcap_data column name
            $repeat_instance = $candidate['instance'] == null ? 1 : $candidate['instance']; //Need repeat_instance for piping
            $start_date = $candidate[$this->portalConfig->startDateField];
            $disable_email = $candidate[$this->portalConfig->disableParticipantEmailField] == null ? "0" : $candidate[$this->portalConfig->disableParticipantEmailField];
            $disable_sms = $candidate[$this->portalConfig->disableParticipantSMSField] == null ? "0" : $candidate[$this->portalConfig->disableParticipantSMSField];
            $personal_url = $candidate[$this->portalConfig->personalUrlField];
            $personal_hash = $candidate[$this->portalConfig->personalHashField];

            //check the logic if there is logic
            if (isset($this->modifier_by_logic)) {
                //$logic_result = REDCap::evaluateLogic($modifier_by_logic, $this->project_id, $candidate[REDCap::getRecordIdField()], $this->portalConfig->surveyEventID); // repeat instance
                $logic_result = REDCap::evaluateLogic($this->modifier_by_logic, $this->project_id, $rec_id,$this->portalConfig->surveyEventID, $repeat_instance);
                if ($logic_result == false) {
                    //logic failed for this candidate
                    continue;
                }
            }

            //check if today is a valid day for invitation:
            $valid_day = $this->checkIfDateValid($start_date, $this->valid_day_array, $this->target_day_str);

            //NULL is returned if the date is not valid
            //0 is evaluating to null?
            //if ($valid_day != null)  {
            if (isset($valid_day)) {
                //check that the valid_day is in the original valid_day_array
                if (!in_array($valid_day, $this->valid_day_array)) {
                    $module->emError("Attempting to send {$this->email_type} on a day not set for Valid Day Number. Day: $valid_day / Valid Day Numbers : ".
                                     $this->portalConfig->validDayNumber);
                    continue;
                }

                //check if valid (multiple allowed, window )

                $survey_link = $this->getSurveyLink($rec_id, $valid_day, $personal_hash, $personal_url, $this->target_day);

                if ($survey_link == null) {
                    $module->emError("Not sending {$this->email_type} for $rec_id.");
                    continue;
                }
                //$module->emDebug($survey_link, $candidate[$this->portalConfig->disableParticipantEmailField."___1"],$candidate[$this->portalConfig->emailField]);

                // SKIP EMAILS FOR 19184 (TODO: MUST REMOVE THIS AFTERNOON !!!)
                // if ($this->project_id == 19184) {
                //     $module->emDebug("Sending invite/reminder for " . $participant->getParticipantID());
                //     usleep(450000);
                // }
                // continue;

                //send invite to email OR SMS
                if (($disable_email <> '1') &&
                    ($candidate[$this->portalConfig->emailField] <> '') &&
                    ($this->config_email_disabled === false))
                {
                    $this->sendEmail($rec_id, $survey_link, $candidate[$this->portalConfig->emailField], $repeat_instance,$valid_day,
                        $this->email_subject,$this->email_text, $this->email_url_label,$this->email_from, $this->email_type);
                }

                if (($disable_sms <> '1') &&
                    ($candidate[$this->portalConfig->phoneField] <> '') &&
                    ($this->config_text_disabled === false))
                {
                    $this->sendSms($rec_id,$survey_link, $candidate[$this->portalConfig->phoneField], $repeat_instance,
                                   $valid_day, $this->sms_text, $this->email_type);

                }

            }


        }


    }

    public function getSurveyLink($rec_id, $valid_day, $personal_hash, $personal_url, $target_day) {
        global $module;


        if (!isset($personal_hash)) {
            $module->emError("Hash missing for record $rec_id: ".$personal_hash);
            return null;
        }

        //create a Participant object for the candidate and get the survey_status array
        try {
            $participant = new Participant($this->portalConfig,$personal_hash);
        } catch (Exception $e) {
            $module->emError($e);
            return null;
        }

        //check that the portal is not disabled
        if ( $participant->getParticipantPortalDisabled()) {
            $module->emDebug("Participant portal disabled for ". $participant->getParticipantID());
            return null;
        }

        //check that the survey has not already been completed
        if ($participant->isSurveyComplete($target_day)) {
            $module->emDebug("Participant # ".$participant->getParticipantID().": Survey for $valid_day is already complete. Don't send the reminder for today");
            return null;
        }

        //send a reminder email
        $survey_link = $personal_url . "&d=" . $valid_day;

        return $survey_link;
    }

}

