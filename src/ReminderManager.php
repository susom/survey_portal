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

        $this->email_from            = $this->portalConfig->reminderEmailFrom;
        $this->email_subject         = $this->portalConfig->reminderEmailSubject;
        $this->email_type            = "Reminder";
        $this->email_text            = $this->portalConfig->reminderEmailText;
        $this->email_url_label       = $this->portalConfig->reminderUrlLabel;
        $this->sms_text              = $this->portalConfig->reminderSMSText;
        $this->modifier_by_logic     = $this->portalConfig->reminderDaysModLogic;
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

        $module->emDebug("Type is ".$this->email_type);
        $module->emDebug("target day is ".$this->target_day_str);

        //CRON JOB already checks that
        // enable-portal is set
        // enable-invitations is set
        // disable-texts and disable-emails ARE NOT both set
        // reminder-lag has no value
    }


    /******************************************************************************************************************
     *  METHODS
     *
     ******************************************************************************************************************/


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

