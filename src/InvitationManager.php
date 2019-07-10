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

    public function __construct($project_id, $sub) {
        global $module;

        $this->project_id = $project_id;

        //get the config id from the passed in hash
        $this->configID = $module->getConfigIDFromSubID($sub);

        if ($this->configID != null) {

            $this->portalConfig = new PortalConfig($this->configID);
        } else {
            $module->emError("Cron job to send invitations attempted for a non-existent configId: ". $this->configID .
                " in this subsetting :  ". $sub);
        }

        //$module->emDebug("in construct with ". $project_id, $sub, $this->configID, $this->portalConfig);
    }


    public function sendInvitations($sub) {
        global $module;

        //sanity check that the subsetting matches the stored portalConfig;
        if ($sub != $this->portalConfig->subSettingID) {
            $module->emError("Wrong subsetting received while sending Invitations from cron");
        }

        $candidates = $this->getInviteReminderCandidates();

        if (empty($candidates)) {
            $module->emLog("No candidates to send invitations for project: ". $this->project_id . " today: ". date('Y-m-d'));
            return;
        }

        foreach ($candidates as $candidate) {

            $valid_day = $this->checkIfDateValid($candidate[$this->portalConfig->startDateField], $this->portalConfig->inviteValidDayArray);
            //$module->emDebug("ID: " .$candidate['participant_id'], " / VALID DAY: ".$valid_day);
            //$module->emDebug($this->portalConfig->inviteValidDayArray, "IN ARRAY");


            //$module->emDebug($valid_day, $this->portalConfig->inviteValidDayArray, $isDateEmpty);
            if ($valid_day != null)  {
                //check if valid (multiple allowed, widow )

                //set up the new record and prefill it with survey data
                //create participant object. we need it to know the next instance.
                try {
                    $participant = new Participant($this->portalConfig, $candidate[$this->portalConfig->personalHashField]);
                    $module->emDebug("Checking invitations for ". $participant->getParticipantID());
                } catch (Exception $e) {
                    $module->emError($e);
                    continue;
                }

                //check that the portal is not disabled
                if ( $participant->getParticipantPortalDisabled()) {
                    $module->emDebug("Participant portal disabled for ". $participant->getParticipantID());
                    continue;
                }

                if ( $participant->isSurveyComplete(new DateTime())) {
                    $module->emDebug("Survey for $valid_day is already complete. Don't send invite for today");
                    continue;
                }

                //create a new ID and prefill the new survey entry with the metadata
                $next_id = $participant->getPartialResponseInstanceID($valid_day, new DateTime());
                $participant->newSurveyEntry($valid_day, new DateTime(), $next_id);


                //create url. Nope ue the &d= version of portal (so it will check daynumber)
                //$survey_link = REDCap::getSurveyLink($participant->participant_id, $participant->surveyInstrument,
                //$participant->surveyEventName, $next_id);

                $portal_url   = $module->getUrl("src/landing.php", true,true);
                $survey_link = $candidate[$this->portalConfig->personalUrlField]."&d=" . $valid_day;
                $module->emDebug($survey_link, $candidate[$this->portalConfig->disableParticipantEmailField."___1"],$candidate[$this->portalConfig->emailField]);

                //send invite to email OR SMS

                if (($candidate[$this->portalConfig->disableParticipantEmailField."___1"] <> '1') &&
                    ($candidate[$this->portalConfig->emailField] <> '')) {


                    $module->emDebug("Sending email invite to ".$candidate[REDCap::getRecordIdField()]);

                    $msg = $this->formatEmailMessage(
                        $this->portalConfig->invitationEmailText,
                        $survey_link,
                        $this->portalConfig->invitationUrlLabel);

                    //send email

                    $send_status = $this->sendEmail(
                        $candidate[$this->portalConfig->emailField],
                        $this->portalConfig->invitationEmailFrom,
                        $this->portalConfig->invitationEmailSubject,
                        $msg);


                    //TODO: log send status to REDCap Logging?
                    REDCap::logEvent(
                        "Email Invitation Sent from Survey Portal EM",  //action
                        "Email sent to " . $candidate[$this->portalConfig->emailField] . " for day_number " . $valid_day . " with status " .$send_status,  //changes
                        NULL, //sql optional
                        $participant->getParticipantID(), //record optional
                        $this->portalConfig->surveyEventName, //event optional
                        $this->project_id //project ID optional
                    );

                }

                if (($candidate[$this->portalConfig->disableParticipantSMSField."___1"] <> '1') &&
                        ($candidate[$this->portalConfig->phoneField] <> '')) {
                    $module->emDebug("Sending text invite to ".$candidate[REDCap::getRecordIdField()]);
                    //TODO: implement text sending of URL
                    $msg = $this->formatTextMessage($this->portalConfig->invitationSmsText, $survey_link);

                    //$sms_status = $this->sms_messager->sendText($candidate[$phone_field], $msg);
                    //$twilio_status = $text_manager->sendSms($candidate[$phone_field], $msg);
                    $twilio_status = $module->emText($candidate[$this->portalConfig->phoneField], $msg);

                    if ($twilio_status !== true) {
                        $module->emError("TWILIO Failed to send to ". $candidate[$this->portalConfig->phoneField] . " with status ". $twilio_status);
                        REDCap::logEvent(
                            "Text Invitation Failed to send from Survey Portal EM",  //action
                            "Text failed to send to " . $candidate[$this->portalConfig->phoneField] . " with status " .  $twilio_status . " for day_number " . $valid_day ,  //changes
                            NULL, //sql optional
                            $participant->getParticipantID(), //record optional
                            $this->portalConfig->surveyEventName, //event optional
                            $this->project_id //project ID optional
                        );
                    } else {
                        $module->emDebug($twilio_status);
                        REDCap::logEvent(
                            "Text Invitation Sent from Survey Portal EM",  //action
                            "Text sent to " . $candidate[$this->portalConfig->phoneField],  //changes
                            NULL, //sql optional
                            $participant->getParticipantID(), //record optional
                            $this->portalConfig->surveyEventName, //event optional
                            $this->project_id //project ID optional
                        );
                    }



                }

            }


        }


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

        //1. Obtain all records where this 'config-id' matches the in the patient record
        //Also filter that either email or sms  is populated.
        $filter = "(".
            "([".$this->portalConfig->participantConfigIDField ."] = '{$this->portalConfig->configID}') AND ".
            "(".
            "(([".$this->portalConfig->disableParticipantEmailField."(1)] <> 1) and  ([".$this->portalConfig->emailField."] <> ''))".
            " OR ".
            "(([".$this->portalConfig->disableParticipantSMSField."(1)] <> 1) and  ([".$this->portalConfig->phoneField."] <> ''))"
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

       //$module->emDebug($result, $not_empty, "Count of invitations to be sent:  ".count($result). " not empty". count($not_empty));
       //exit;

        //return $result;
        return $not_empty;

    }


    /**
     * Given start date and valid_day_number array, check if date is a valid survey date
     *
     * @param $start
     * @param $valid_day_number
     */
    public function checkIfDateValid($start_str, $valid_day_number, $date_str = null) {
        global $module;
        //$module->emDebug("Incoming to check If Date Valid", $start_str,$valid_day_number, $date_str);
        //use today
        $date = new DateTime($date_str);
        $start = new DateTime($start_str);

        $interval = $date->diff($start);
        //$module->emDebug("DIFF in Days: ".  $interval->days);

        // need at add one day since start is day 0
        if (in_array($interval->days, $valid_day_number)) {
            return ($interval->days + 1);
        }
        return null;

    }

    /**
     * TODO: implement this
     * @param $date
     * @return bool
     */
    public function checkIfSurveyEntered($date) {

        //check if survey already exists for this date
        return true;

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


    function sendEmail($to, $from, $subject, $msg) {

        // Prepare message
        $email = new Message();
        $email->setTo($to);
        $email->setFrom($from);
        $email->setSubject($subject);
        $email->setBody($msg); //format message??

        $result = $email->send();
        //$module->emDebug($to, $from, $subject, $msg, $result);

    // Send Email
        if ($result == false) {
            $module->emLog('Error sending mail: ' . $email->getSendError() . ' with ' . json_encode($email));
            return false;
        }

        return true;
    }

    function formatTextMessage($msg, $survey_link) {

        $target_str = "[invitation-url]";

        //don't use for text messages
        $tagged_link = "<a href='{$survey_link}'>link</a>";
        //if there is the inviation-url tag included, switch it out for the actual url.  if not, then add it to the end.


        if (strpos($msg, $target_str) !== false) {
            $msg = str_replace($target_str, $survey_link, $msg);
        } else {
            $msg = $msg . "  Use this link to take the survey:".$survey_link;
        }

        return $msg;
    }

}