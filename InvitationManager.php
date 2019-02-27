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

class InvitationManager {

    public $portalConfig;

    public $configID;

    public function __construct($project_id, $sub) {
        global $module;

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


    public function sendInvitations() {
        global $module;

        $candidates = $this->getInviteCandidates();

        foreach ($candidates as $candidate) {

            $valid_day = $this->checkIfDateValid($candidate[$this->portalConfig->startDateField], $this->portalConfig->inviteValidDayArray);
            //$module->emDebug($valid_day, $this->portalConfig->inviteValidDayArray, "IN ARRAY");
            $isDateEmpty = $this->checkIfSurveyEntered(new DateTime());

            //$module->emDebug($valid_day, $this->portalConfig->inviteValidDayArray, $isDateEmpty);
            if (($valid_day != null) && $isDateEmpty) {
                //check if valid (multiple allowed, widow )

                //set up the new record and prefill it with survey data
                //create participant object. we need it to know the next instance.
                try {
                    $participant = new Participant($this->portalConfig, $candidate[$this->portalConfig->personalHashField]);
                } catch (Exception $e) {
                    $this->emError($e);
                    continue;
                }

                //create a new ID and prefill the new survey entry with the metadata
                $next_id = $participant->getPartialResponseInstanceID($valid_day, new DateTime());
                $participant->newSurveyEntry($valid_day, new DateTime(), $next_id);


                //create url. Nope ue the &d= version of portal (so it will check daynumber)
                //$survey_link = REDCap::getSurveyLink($participant->participant_id, $participant->surveyInstrument,
                //$participant->surveyEventName, $next_id);

                $portal_url   = $module->getUrl("web/landing.php", true,true);
                $survey_link = $candidate[$this->portalConfig->personalUrlField]."&d=" . $valid_day;
                $module->emDebug($survey_link, $candidate[$this->portalConfig->disableParticipantEmailField."___1"],$candidate[$this->portalConfig->emailField]);

                //send invite to email OR SMS

                if (($candidate[$this->portalConfig->disableParticipantEmailField."___1"] <> '1') &&
                    ($candidate[$this->portalConfig->emailField] <> '')) {


                    $module->emDebug("Sending email invite to ".$candidate[REDCap::getRecordIdField()]);

                    $msg = $this->formatEmailMessage($this->portalConfig->invitationEmailText, $survey_link);

                    //send email

                    $send_status = $this->sendEmail(
                        $candidate[$this->portalConfig->emailField],
                        $this->portalConfig->invitationEmailFrom,
                        $this->portalConfig->invitationEmailSubject,
                        $msg);


                    //TODO: log send status to REDCap Logging?

                }

                if ($candidate[$invitation_sms_field."___1"] == '1') {
                    $this->emDebug("Sending text invite to ".$candidate[REDCap::getRecordIdField()]);
                    //TODO: implement text sending of URL
                    $msg = $this->formatEmailMessage($sms_text, $survey_link);

                    //$sms_status = $this->sms_messager->sendText($candidate[$phone_field], $msg);
                    //$twilio_status = $text_manager->sendSms($candidate[$phone_field], $msg);
                    $twilio_status = $this->emText($candidate[$phone_field], $msg);

                    if (!$twilio_status) {
                        $this->emError("TWILIO Failed to send to ". $candidate[$phone_field] . " with status ". $twilio_status);
                    }



                }

            }


        }


    }

    public function getInviteCandidates() {
        global $module;

        if ($this->portalConfig->configID  == null) {
            $module->emError("config ID is not set!");
            return false;
        }

        //1. Obtain all records where this 'config-id' matches the in the patient record
        //Also filter that either invitation_method_field is populated.
        $filter = "(".
            "([".$this->portalConfig->participantConfigIDField ."] = '{$this->portalConfig->configID}') AND ".
            "(".
            "(([".$this->portalConfig->disableParticipantEmailField."(1)] <> 1) and  ([".$this->portalConfig->emailField."] <> ''))".
            " OR ".
            "(([".$this->portalConfig->disableParticipantSMSField."(1)] <> 1) and  ([".$this->portalConfig->phoneField."] <> ''))"
            .")"
            .")";
        $filter1 =  "([".$config_field."] = '$config_id')";
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
                $this->portalConfig->phoneField,
                $this->portalConfig->personalHashField
            ),
            'events' => $this->portalConfig->surveyEventName,
            'filterLogic'  => $filter
        );

        //$this->emDebug($params, "PARAMS"); exit;
        $q = REDCap::getData($params);
        $result = json_decode($q, true);

        $module->emDebug($result, "Count of invitations to be sent:  ".count($result));

        return $result;

    }


    /**
     * Given start date and valid_day_number array, check if date is a valid survey date
     *
     * @param $start
     * @param $valid_day_number
     */
    public function checkIfDateValid($start_str, $valid_day_number, $date_str = null) {


        //use today
        $date = new DateTime($date_str);
        $start = new DateTime($start_str);

        $interval = $date->diff($start);
        //$this->emDebug("DIFF in Days", $interval->days, $valid_day_number);

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
    function formatEmailMessage($msg, $survey_link) {
        $target_str = "[invitation-url]";

        $tagged_link = "<a href='{$survey_link}'>link</a>";
        //if there is the inviation-url tag included, switch it out for the actual url.  if not, then add it to the end.


        if (strpos($msg, $target_str) !== false) {
            $msg = str_replace($target_str, $tagged_link, $msg);
        } else {
            $msg = $msg . "<br>".$target_str;
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
        //$this->emDebug($to, $from, $subject, $msg, $result);

    // Send Email
        if ($result == false) {
            $this->emLog('Error sending mail: ' . $email->getSendError() . ' with ' . json_encode($email));
            return false;
        }

        return true;
    }

}