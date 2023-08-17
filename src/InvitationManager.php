<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-02-26
 * Time: 12:11
 */

namespace Stanford\RepeatingSurveyPortal;

use REDCap;
use DateTime;
use Exception;
use Message;
use Piping;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Portal $Portal */
/** @var  Stanford\RepeatingSurveyPortal\PortalConfig $portalConfig */

/**
 * Class called by InvitationCron job. Evaluates date and participants and then sends day invitations by email/text
 * Class InvitationManager
 * @package Stanford\RepeatingSurveyPortal
 */
class InvitationManager {

    public $portalConfig;

    public $configID;

    public $project_id;

    protected $email_from;
    protected $email_subject;
    protected $email_type; //Invite or Reminder
    protected $email_text;
    protected $email_url_label;
    protected $sms_text;
    protected $modifier_by_logic;
    protected $valid_day_array;
    protected $allowed_inactive_days;

    protected $config_text_disabled;
    protected $config_email_disabled;

    protected $target_day;
    protected $target_day_str;


    public function __construct($project_id, $sub) {
        global $module;

        $this->project_id = $project_id;

        //get the config id from the passed in hash
        $this->configID = $module->getConfigIDFromSubID($sub);

        if ($this->configID != null) {
            $this->portalConfig = new PortalConfig($this->configID);
        } else {
            $err_msg ="Cron job attempted to send invites for a non-existent configId: ". $this->configID . " in this subsetting :  ". $sub;
            $module->emError($err_msg);
            throw new Exception($err_msg);
        }

        $this->email_from            = $this->portalConfig->invitationEmailFrom;
        $this->email_subject         = $this->portalConfig->invitationEmailSubject;
        $this->email_type            = "Invite";
        $this->email_text            = $this->portalConfig->invitationEmailText;
        $this->email_url_label       = $this->portalConfig->invitationUrlLabel;
        $this->sms_text              = $this->portalConfig->invitationSmsText;
        $this->modifier_by_logic     = $this->portalConfig->invitationDaysModLogic;
        $this->logic_event           = $this->portalConfig->invitationDaysModLogicEvent;
        $this->allowed_inactive_days = $this->portalConfig->invitationDaysModInactivity;

        $this->config_text_disabled  = $this->portalConfig->disableTexts;
        $this->config_email_disabled = $this->portalConfig->disableEmails;

        $this->valid_day_array = $this->portalConfig->inviteValidDayArray;

        //for invitations, use current day for lagged_str
        $this->target_day  = new DateTime();
        $this->target_day_str = $this->target_day->format('Y-m-d');

        $module->emDebug("inactive is ".$this->allowed_inactive_days);
        $module->emDebug("target day is ".$this->target_day_str);

        //CRON JOB already checks that
        // enable-portal is set
        // enable-invitations is set
        // disable-texts and disable-emails ARE NOT both set

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
    function countInvitations($sub)
    {
        global $module;

        $module->emDebug("Invite type is : ".$this->email_type);
        $module->emDebug("inactive is ".$this->allowed_inactive_days);
        $module->emDebug("target day is ".$this->target_day_str);


        if ($sub != $this->portalConfig->subSettingID) {
            $module->emError("Wrong subsetting received while sending Invitations from cron");
            return null;
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
    public function sendInvitations($sub) {
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

        $ct_sent_email = 0;
        $ct_sent_sms = 0;

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
                $logic_result = REDCap::evaluateLogic($this->modifier_by_logic, $this->project_id, $rec_id, $this->logic_event);
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
                    $email_status = $this->sendEmail($rec_id, $survey_link, $candidate[$this->portalConfig->emailField], $repeat_instance,
                                     $valid_day,$this->email_subject,$this->email_text,$this->email_url_label,$this->email_from, $this->email_type);

                    if ($email_status == true) $ct_sent_email++;
                }

                if (($disable_sms <> '1') &&
                    ($candidate[$this->portalConfig->phoneField] <> '') &&
                    ($this->config_text_disabled === false))
                {
                    $sms_status = $this->sendSms($rec_id,$survey_link, $candidate[$this->portalConfig->phoneField], $repeat_instance,
                                   $valid_day, $this->sms_text, $this->email_type);
                    if ($sms_status === true) {
                        $ct_sent_sms++;
                        $module->emDebug($sms_status);
                    }


                }

            }


        }

        $module->emDebug("{$this->email_type} : Sent $ct_sent_email emails");
        $module->emDebug("{$this->email_type} : Sent $ct_sent_sms texts");
        REDCap::logEvent(
            "Count of {$this->email_type} Sent from Survey Portal EM",  //action
            " Sent $ct_sent_email emails and $ct_sent_sms texts",  //changes
            NULL, //sql optional
            NULL, //$participant->getParticipantID(), //record optional
            $this->portalConfig->surveyEventName, //event optional
            $this->project_id //project ID optional
        );


    }

    public function getSurveyLink($rec_id, $valid_day, $personal_hash, $personal_url, $target_day) {
        global $module;

        if (!isset($personal_hash)) {
            $module->emError("Hash missing for record $rec_id: ".$personal_hash);
            return null;
        }

        //set up the new record and prefill it with survey metadata
        //create participant object. we need it to know the next instance.
        try {
            $participant = new Participant($this->portalConfig, $personal_hash);
            $module->emDebug("Checking invitations for ". $participant->getParticipantID() . " record $rec_id");
        } catch (Exception $e) {
            $module->emError($e->getMessage());
            return null;
        }

        //check that the portal is not disabled
        if ( $participant->getParticipantPortalDisabled()) {
            $module->emDebug("Participant portal disabled for ". $participant->getParticipantID());
            return null;
        }

        //check that the survey already not completed for today
        if ( $participant->isSurveyComplete($target_day)) {
            $module->emDebug("Participant # ".$participant->getParticipantID().": Survey for day number $valid_day is already complete. Don't send invite for today");
            return null;
        }

        //create a new ID and prefill the new survey entry with the metadata
        $next_id = $participant->getPartialResponseInstanceID($valid_day, new DateTime());
        $participant->newSurveyEntry($valid_day, new DateTime(), $next_id);


        //create url. Nope ue the &d= version of portal (so it will check daynumber)
        //$survey_link = REDCap::getSurveyLink($participant->participant_id, $participant->surveyInstrument,
        //$participant->surveyEventName, $next_id);

        //$portal_url   = $module->getUrl("src/landing.php", true,true);
        $survey_link = $personal_url."&d=" . $valid_day;

        return $survey_link;

    }

    public function sendEmail($rec_id, $survey_link, $email_addr, $repeat_instance, $valid_day, $email_subject, $email_text,
                              $email_url_label, $email_from, $email_type) {
        global $module;
        $module->emDebug("Sending email invite to participant record id: ".$rec_id);

        $msg = $this->formatEmailMessage(
            $email_text, // $this->portalConfig->invitationEmailText,
            $survey_link,
            $email_url_label //$this->portalConfig->invitationUrlLabel);
        );

        //prep email
        $piped_email_subject = Piping::replaceVariablesInLabel( $email_subject, $rec_id, $this->portalConfig->surveyEventID, $repeat_instance,array(), false, null, false);
        $piped_email_msg = Piping::replaceVariablesInLabel($msg, $rec_id, $this->portalConfig->surveyEventID, $repeat_instance,array(), false, null, false);

        $email = new Message();
        $email->setTo($email_addr);
        $email->setFrom($email_from);
        $email->setSubject($piped_email_subject);
        $email->setBody($piped_email_msg); //format message??

        $send_status = $email->send();

        //TODO: log send status to REDCap Logging?
        if ($send_status == false) {
            $send_status_msg = "Error sending {$email_type} email to ";
        } else {
            $send_status_msg = "{$email_type} email sent to ";
        }
        REDCap::logEvent(
            "Email {$email_type}  Sent from Survey Portal EM",  //action
            $send_status_msg . $email_addr . " for day_number " . $valid_day . " with status " .$send_status,  //changes
            NULL, //sql optional
            $rec_id, //$participant->getParticipantID(), //record optional
            $this->portalConfig->surveyEventName, //event optional
            $this->project_id //project ID optional
        );
        return $send_status;
    }

    public function sendSms($rec_id, $survey_link, $phone_num, $repeat_instance, $valid_day, $sms_text, $email_type) {
        global $module;
        $module->emDebug("Sending text invite to record id: ".$rec_id);

        //TODO: implement text sending of URL
        $msg = $this->formatTextMessage($sms_text,
                                        $survey_link,
                                        $rec_id,
                                        $this->portalConfig->surveyEventID,
                                        $repeat_instance
        );

        //$sms_status = $this->sms_messager->sendText($candidate[$phone_field], $msg);
        //$twilio_status = $text_manager->sendSms($candidate[$phone_field], $msg);
        $twilio_status = $module->emText($phone_num, $msg);

        if ($twilio_status !== true) {
            $module->emError("TWILIO  {$email_type}  Failed to send to ". $phone_num . " with status ". $twilio_status);
            REDCap::logEvent(
                "Text {$email_type} failed to send from Survey Portal EM",  //action
                "Text {$email_type} failed to send to " . $phone_num . " with status " .  $twilio_status . " for day_number " . $valid_day ,  //changes
                NULL, //sql optional
                $rec_id, //record optional
                $this->portalConfig->surveyEventName, //event optional
                $this->project_id //project ID optional
            );
        } else {
            $module->emDebug($twilio_status);
            REDCap::logEvent(
                "Text {$email_type} Sent from Survey Portal EM",  //action
                " {$email_type} text sent to " . $phone_num,  //changes
                NULL, //sql optional
                $rec_id, //record optional
                $this->portalConfig->surveyEventName, //event optional
                $this->project_id //project ID optional
            );
        }

        return $twilio_status;
    }


    /**
     * Do a REDCap filter search on the project where
     *    1. config-id field matches the config-id in the subsetting for this config
     *    2. emails has not been disabled for this participant and the email field is not empty
     *    3. phone has not been disabled for this participant and the phone field is not empty
     *
     * @return bool|mixed
     */
    public function getInviteReminderCandidates() {
        global $module;

        if ($this->portalConfig->configID  == null) {
            $module->emError("config ID is not set!");
            return false;
        }

        $enrollment_arm = $this->portalConfig->mainConfigEventName;

        //1. Obtain all records where this 'config-id' matches the in the patient record
        //Also filter that either email or sms  is populated.
        $filter = "(".
            "([$enrollment_arm][".$this->portalConfig->participantConfigIDField ."] = '{$this->portalConfig->configID}') AND ".
            "(".
            "(([$enrollment_arm][".$this->portalConfig->disableParticipantEmailField."(1)] <> 1) and  ([$enrollment_arm][".$this->portalConfig->emailField."] <> ''))".
            " OR ".
            "(([$enrollment_arm][".$this->portalConfig->disableParticipantSMSField."(1)] <> 1) and  ([$enrollment_arm][".$this->portalConfig->phoneField."] <> ''))"
            .")"
            .")";

        $module->emDebug($filter);
        $params = array(
            'return_format' => 'json',
            'fields' => array(
                REDCap::getRecordIdField(),
                $this->portalConfig->emailField,
                $this->portalConfig->phoneField,
                $this->portalConfig->personalUrlField,
                $this->portalConfig->startDateField,
                $this->portalConfig->emailField,
                $this->portalConfig->disableParticipantEmailField,
                $this->portalConfig->phoneField,
                $this->portalConfig->disableParticipantSMSField,
                $this->portalConfig->personalHashField
            ),
            'events' => $this->portalConfig->mainConfigEventName,
            'filterLogic'  => $filter
        );

        //$module->emDebug($params, "PARAMS");
        $q = REDCap::getData($params);
        $result = json_decode($q, true);

        //there is a bug since 9.1 where the filter returns an empty array for every found array.
        //iterate over the returned result and delete the ones where redcap_repeat_instance is blank
        $not_empty = array();
        foreach ($result as $k => $v) {
            if (!empty($v['redcap_repeat_instance'])) {
                $not_empty[] = $v;
            }
        }

        //$sql_result = $this->getInviteReminderCandidatesBySQL();
        //$not_empty = $this->getRecordsBySql();

        //$module->emDebug("count by getData: ".count($not_empty). " / count by sql: ".count($sql_result));

       //$module->emDebug($result, $not_empty, "Count of invitations to be sent:  ".count($result). " not empty". count($not_empty));
       //exit;

        //return $result;
        return $not_empty;

    }

    public function getInviteReminderCandidatesBySQL($allowed_inactive_days) {
        global $module;

        $module->emDebug("Running count for {$this->email_type} getting sql: inactive: ". $allowed_inactive_days);

        if ($this->portalConfig->configID  == null) {
            $module->emError("config ID is not set!");
            return false;
        }

        $fields = array(
            $this->portalConfig->emailField,
            $this->portalConfig->phoneField,
            $this->portalConfig->personalUrlField,
            $this->portalConfig->startDateField,
            $this->portalConfig->disableParticipantEmailField,
            $this->portalConfig->disableParticipantSMSField,
            $this->portalConfig->personalHashField,
            $this->portalConfig->participantConfigIDField
        );

        $select_str = "select distinct(r0.record), r0.event_id, r0.instance";
        $from_str   = " from redcap_data r0 ";
        $where_str  = " where r0.project_id = %d and r0.event_id = %d";
        $cross_str = " and (
    (coalesce(r5.value,'') != '1') and (coalesce(r1.value,'') != '')
    or
    (coalesce(r6.value,'') != '1') and (coalesce(r2.value,'') != '')
    )
    and (coalesce(r8.value,'') = '{$this->portalConfig->configID}')";

        $i = 1;
        foreach ($fields as $k => $v) {
            $select_str .= ",r{$i}.value as '{$v}'";
            $from_str .= " left join redcap_data r{$i} on r0.project_id = r{$i}.project_id and ".
                "r0.event_id = r{$i}.event_id and r0.record = r{$i}.record and ".
                "r0.instance <=> r{$i}.instance and r{$i}.field_name = '%s'\n ";
            $i++;
        }

        array_push($fields, $this->project_id, $this->portalConfig->mainConfigEventID);

        //If an allowed inactive window is set, then create a sql fragment and add this to the sql query.
        $inactive_sql = '';
        if ((!empty($allowed_inactive_days)) && (is_numeric($allowed_inactive_days))) {


            $inactive_sql = $this->getInactiveSql();

            //
            array_push($fields,$this->project_id,
                       $this->portalConfig->surveyInstrument,
                       $this->portalConfig->surveyEventID,
                       $allowed_inactive_days,
                       $this->project_id);
        }

        try {
            $sql = vsprintf($select_str . $from_str . $where_str . $cross_str . $inactive_sql,
                            $fields);

            //$module->emDebug($sql);

            $q = db_query($sql);

            return $q;

        } catch (Exception $e) {
            $module->emError("Exception while executing sql to find inactive records", $e->getMessage());
            return null;
        }
    }

    public function getInactiveSql()
    {
        $sql_str = " and r0.record in (select distinct(rd.record) from  redcap_data rd
    left join (
        select
            rsr.record
        from
            redcap_surveys_response rsr
        join redcap_surveys_participants rsp on rsr.participant_id = rsp.participant_id
        where
            rsp.survey_id = (select survey_id from redcap_surveys where project_id = %d and form_name = '%s')
            and rsp.event_id = %d
        group by
            rsr.record
        having
            max(rsr.completion_time) >= NOW() - INTERVAL %d DAY
     ) as old on rd.record = old.record
where
    rd.project_id = %d
    and old.record is null)";

        return $sql_str;
    }

    public function getInactiveRecords($project_id, $allowed_inactive_days) {
        global $module;

        try {
            $sql = sprintf("
select distinct(rd.record)
from
    redcap_data rd
    left join (
        select
            rsr.record
        from
            redcap_surveys_response rsr
        join redcap_surveys_participants rsp on rsr.participant_id = rsp.participant_id
        where
            rsp.survey_id = (select survey_id from redcap_surveys where project_id = %d and form_name = '%s')
            and rsp.event_id = %d
        group by
            rsr.record
        having
            max(rsr.completion_time) >= NOW() - INTERVAL %d DAY
     ) as old on rd.record = old.record
where
    rd.project_id = %d
    and old.record is null",
                $project_id,
                $this->portalConfig->surveyInstrument,
                $this->portalConfig->surveyEventID,
                $allowed_inactive_days,
                $project_id
           );

            //$module->emDebug($sql);

            $q = db_query($sql);

            return $q;

        } catch (\Exception $e) {
            $module->emError("Exception while executing sql to find inactive records", $e->getMessage());
            return null;
        }



    }

    /**
     * Given start date and valid_day_number array, check if date is a valid survey date
     *
     * @param $start
     * @param $valid_day_number
     * @param $date_str
     * @return int|null  : day number for date passed in, NULL if not valid date.
     */
    public function checkIfDateValid($start_str, $valid_day_number, $date_str = null) {
        global $module;
        //$module->emDebug("Incoming to check If this date valid:". $date_str . ' with this start date: '. $start_str);
        //$module->emDebug("valid day array: ".implode(',',$valid_day_number));

        //use today
        $date = new DateTime($date_str);
        $start = new DateTime($start_str);

        $interval = $start->diff($date);

        $diff_date = $interval->format("%r%a");
        $diff_hours = $interval->format("%r%h");
        //$module->emDebug("DATE is {$date->format('Y-m-d H:i')} and start is {$start->format('Y-m-d H:i')} DIFF in DAYS: $diff_date /  DIFF in hours: ".  $diff_hours);

        //$module->emDebug("INTERVAL: ".$diff_date, $diff_hours);
        //$module->emDebug($interval->days, $interval->invert,$diff_date, $interval->days * ( $interval->invert ? -1 : 1));

        // need at add one day since start is day 0??
        //Need to check that the diff in hours is greater than 0 as date diff is calculating against midnight today
        //and partial days > 12 hours was being considered as 1 day.
        if ( ($diff_hours >= 0) && (in_array($diff_date, $valid_day_number))) {
            //actually, don't add 1. start date should be 0.
            //return ($interval->days + 1);
            return ($diff_date);
        }
        return null;

    }

    /**
     * Replace the url tag [invitation-url] with the $survey-link passed in as parameter
     * If url tag not embedded in $msg, add link to bottom of the email
     *
     * TODO: get wording for the link at the bottom of the email.
     *
     * @param $msg
     * @param $survey_link
     * @return mixed|string
     */
    function formatEmailMessage($msg, $survey_link, $survey_link_label) {
        $target_str = "[invitation-url]";

        if (empty($survey_link_label)) {
            $survey_link_label = $survey_link;
        }

        $tagged_link = "<a href='{$survey_link}'>$survey_link_label</a>";
        //if there is the inviation-url tag included, switch it out for the actual url.  if not, then add it to the end.

        if (strpos($msg, $target_str) !== false) {
            $msg = str_replace($target_str, $tagged_link, $msg);
        } else {
            $msg = $msg . "<br>Use this link to take the survey: ".$tagged_link;
        }

        return $msg;
    }


    /**
     * Switches out [invitation-url] with friendly text if provided
     * Adds standard message if no text entered.
     *
     * @param $msg
     * @param $survey_link
     * @return mixed|string
     */
    function formatTextMessage($msg, $survey_link, $record, $event_id, $repeat_instance) {

        $target_str = "[invitation-url]";

        //if there is the invitation-url tag included, switch it out for the actual url.  if not, then add it to the end.
        if (strpos($msg, $target_str) !== false) {
            $msg = str_replace($target_str, $survey_link, $msg);
        } else {
            $msg = $msg . "  Use this link to take the survey:".$survey_link;
        }

        $piped_msg = Piping::replaceVariablesInLabel($msg, $record, $event_id, $repeat_instance,array(), false, null, false);

        return $piped_msg;
    }

}