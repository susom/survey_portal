<?php

namespace Stanford\RepeatingSurveyPortal;

use \ExternalModules;
use \REDCap;
use \DateTime;
use \Message;
use Exception;

require_once 'Participant.php';
require_once 'PortalConfig.php';

/**
 * Class RepeatingSurveyPortal
 * @package Stanford\RepeatingSurveyPortal
 *
 *
 * WEB
 *
 * Portal Landing Page
 *
 * web/landing.php   NOAUTH
 * web/forecast.php (tries to show what will happen based on certain dates)
 * web/cron.php     NOAUTH (landing page to instantiate cron)
 *  - load the project config, for each config, it will execute check to see if each record needs notification...
 *  -
 *
 *
 *
 */
class RepeatingSurveyPortal extends \ExternalModules\AbstractExternalModule
{

    const KEY_VALID_CONFIGURATION = "survey_portal_config_valid";


    public function redcap_module_system_enable() {
        // SET THE
        // Do Nothing
    }


    public function redcap_module_link_check_display($project_id, $link) {
        // TODO: Loop through each portal config that is enabled and see if they are all valid.
        if ($this->getSystemSetting(self::KEY_VALID_CONFIGURATION) == 1) {
            // Do nothing - no need to show the link
        } else {
            $link['icon'] = "exclamation";
        }
        return $link;
    }


    // CRON METHOD
    /**
     * TODO: Add cron to config.json
     *
     * 1) Determine projects that are using this EM
     * 2) Instantiate instance of EM for each project
     * 3)
     */
    public function inviteCron() {

        //* 1) Determine projects that are using this EM
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('web/InviteCron.php', true, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //check scheduled hour of send
            $scheduled_hour = $this->getProjectSetting('invitation-time', $pid);
            $current_hour = date('H');

            //iterate through all the sub settings
            foreach ($scheduled_hour as $sub => $invite_time) {
                $this->emDebug("project $pid - $sub scheduled at this hour $invite_time vs current hour: $current_hour");


                //if not hour, continue
                if ($scheduled_hour != $current_hour) continue;

                $this_url = $url . '&pid=' . $pid . "&c=" . $sub;
                $this->emDebug("CRON URL IS " . $this_url);

                $resp = http_get($this_url);
                //$this->cronAttendanceReport($pid);
                $this->emDebug("cron for text reminder: " . $resp);
            }
        }

    }


    /**
     * * TODO: Add cron to config.json
     */
    public function reminderCron() {
        //* 1) Determine projects that are using this EM
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('web/ReminderCron.php', true, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //check scheduled hour of send
            $scheduled_hour = $this->getProjectSetting('reminder-time', $pid);
            $current_hour = date('H');

            //iterate through all the sub settings
            foreach ($scheduled_hour as $sub => $invite_time) {
                $this->emDebug("project $pid - $sub scheduled at this hour $invite_time vs current hour: $current_hour");


                //if not hour, continue
                if ($scheduled_hour != $current_hour) continue;

                $this_url = $url . '&pid=' . $pid . "&c=" . $sub;
                $this->emDebug("CRON URL IS " . $this_url);

                $resp = http_get($this_url);
                //$this->cronAttendanceReport($pid);
                $this->emDebug("cron for text reminder: " . $resp);
            }
        }
    }

    /**
     *
     * TODO: add send status to REDCap logging
     *
     * Called by cron method - process for the current project for this subsetting
     *
     * @param $sub  SubSetting number
     */
    public function sendInvitations($sub) {

        $from = $this->getProjectSetting("invitation-email-from")[$sub];
        $subject = $this->getProjectSetting("invitation-email-subject")[$sub];
        //Sanity test printouts
        //$this->emDebug($sub, $this->getProjectSettings(), $this->getProjectSetting('config-id')); exit;

        //1. from the $sub ID derive the Config ID
        $config_id             = ($this->getProjectSetting('config-id'))[$sub];
        $invitation_email_field  = ($this->getProjectSetting('invitation-email-field'))[$sub];
        $invitation_sms_field  = ($this->getProjectSetting('invitation-sms-field'))[$sub];
        $event_id                 = ($this->getProjectSetting('main-config-event-name'))[$sub];

        $config_field = ($this->getProjectSetting('participant-config-id-field'))[$sub];
        $email_field = ($this->getProjectSetting('email-field'))[$sub];  // using non-blank email field to trigger send?
        $phone_field = ($this->getProjectSetting('phone-field'))[$sub];  // using non-blank phone field to trigger send?
        $email_text = ($this->getProjectSetting('invitation-email-text'))[$sub];
        $sms_text = ($this->getProjectSetting('invitation-sms-text'))[$sub];
        $invitation_days = ($this->getProjectSetting('invitation-days'))[$sub];
        $start_str = ($this->getProjectSetting('start-date-field'))[$sub];
        $url = ($this->getProjectSetting('personal-url-field'))[$sub];
        $hash = ($this->getProjectSetting('personal-hash-field'))[$sub];

        //$this->emDebug($config_id, ($this->getProjectSetting('config-id'))[$sub],"CONFIG ID"); exit;

        //1. Obtain all records where this 'config-id' matches the in the patient record
        //Also filter that either invitation_method_field is populated.

        $filter = "(".
            "([".$config_field."] = '$config_id') AND ".
            "(".
            "(([".$invitation_email_field."(1)] = 1) and  ([".$email_field."] <> ''))".
            " OR ".
            "(([".$invitation_sms_field."(1)] =1) and  ([".$phone_field."] <> ''))"
            .")"
            .")";
        $filter1 =  "([".$config_field."] = '$config_id')";
        $this->emDebug($filter);
        $params = array(
                'return_format' => 'json',
                'fields' => array(
                    REDCap::getRecordIdField(),
                    $invitation_email_field,
                    $invitation_sms_field,
                    $url,
                    $start_str,
                    $email_field,
                    $phone_field,
                    $hash
                ),
                'events' => $event_id,
                'filterLogic'  => $filter
            );

        //$this->emDebug($params, "PARAMS"); exit;
        $q = REDCap::getData($params);
        $result = json_decode($q, true);
//                'array', NULL, array($cfg['MAIN_SURVEY_HASH_FIELD']), $config_event[$sub],
//                NULL,FALSE,FALSE,FALSE,$filter);
        $this->emDebug("Count of invitations to be sent:  ".count($result));

        //iterate over and check if the phone/email
        foreach ($result as $candidate) {

            //check if today is an invitation day
            $valid_day_array = self::parseRangeString($invitation_days);
            $valid_day = $this->checkIfDateValid($candidate[$start_str],$valid_day_array );
            //$this->emDebug($valid_day, $valid_day_array, "IN ARRAY");

            if ($valid_day != null) {
                //check if valid (multiple allowed, widow )

                //set up the new record and prefill it with survey data
                //create participant object. we need it to know the next instance.
                try {
                    $participant = new Participant($sub, $candidate[$hash], $valid_day_array);
                } catch (Exception $e) {
                    $this->emError($e);
                    continue;
                }
                //$participant->newSurveyEntry($valid_day, new DateTime());

                $next_id = $participant->max_instance + 1;

                //create url. Nope ue the &d= version of portal (so it will check daynumber)
                //$survey_link = REDCap::getSurveyLink($participant->participant_id, $participant->surveyInstrument,
                //$participant->surveyEventName, $next_id);
                $config_id = $this->getConfgIDFromSubID($sub);
                $portal_url   = $this->getUrl("web/landing.php", true,true);
                $survey_link = $portal_url. "&h=" . $candidate[$hash] . "&c=" . $config_id ."&d=" . $valid_day;

                //send invite to email OR SMS
                if ($candidate[$invitation_email_field."___1"] == '1') {

                    $this->emDebug("Sending email invite to ".$candidate[REDCap::getRecordIdField()]);

                    $msg = $this->formatEmailMessage($email_text, $survey_link);

                    //send email

                    $send_status = $this->sendEmail($candidate[$email_field], $from, $subject."  1" , $msg);

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

    public function sendReminders($sub) {

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

        if (in_array($interval->days, $valid_day_number)) {
            return $interval->days;
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
    function formatEmailMessage($msg, $survey_link) {
        $target_str = "[invitation-url]";

        //if there is the inviation-url tag included, switch it out for the actual url.  if not, then add it to the end.


        if (strpos($msg, $target_str) !== false) {
            $msg = str_replace($target_str, $survey_link, $msg);
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


    // SAVE CONFIG HOOK
    // if config-id is null, then generate a config id for that the configs...
    //todo: HOLD ON THIS. saving works, but delete ignores this setting we add. punt for now.
    public function hold_redcap_module_save_configuration($project_id) {
        $enable_portal = $this->getProjectSetting('enable-portal');
        $config_ids = $this->getProjectSetting('config-id');

        $this->emDebug($enable_portal, $config_ids);

        //for each portal, make sure it has an id
        //just using enable_portal as proxy for all existing configurations since it will need to be set for each sub setting
        foreach ($enable_portal as $sub => $v) {

            //make sure the config_id for this sub already doesn't exist
            if (empty($config_ids[$sub])) {

                $new_config_id = $this->generateUniqueConfigID('config-id');

                //add this to the original config setting from the db and save it
                $config_ids[$sub] = $new_config_id;

            }
            $this->setProjectSetting('config-id', $config_ids);

        }

        $this->emDebug(" ENDING Config id for $project_id : " , $config_ids);
    }


    //REDCap_survey_complete
    public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id,  $survey_hash,  $response_id,  $repeat_instance) {
        $cookie_key = $this->PREFIX."_".$project_id."_".$record;  //this won't work if mother/child are on same machine at same time.
            //$module->PREFIX."_".$project_id."_".$portal->getParticipantId()
        $cookie_config = $_COOKIE[$cookie_key];


        //if redirect has been turned on redirect to the landing page

        $sub = $this->getSubIDFromConfigID($cookie_config);

        $redirect = $this->getProjectSetting('survey-complete-redirect')[$sub];

        if (isset($redirect) && ($redirect == $instrument) ) {

            $config_event_id = $this->getProjectSetting('main-config-event-name');
            $config_event_name = REDCap::getEventNames(true, false, $config_event_id);
            $config_field = $this->getProjectSetting('participant-config-id-field');
            $hash_field = $this->getProjectSetting('personal-hash-field')[$sub];
            $hash_return  = $this->retrieveParticipantFieldWithFilter($record, $config_event_name, $config_field,$cookie_config, array($hash_field) );
            $hash = $hash_return[$hash_field];

            $portal_url   = $this->getUrl("web/landing.php", true,true);
            $return_hash_url = $portal_url. "&h=" . $hash . "&c=" . $cookie_config;

            $this->emDebug("this is new hash: ". $return_hash_url);

            //now redirect back to the landing page
            header("Location: " . $return_hash_url);
            exit;

        }


    }

    // SAVE_RECORD HOOK
    // make portal objects and verify that current record has hash and personal url saved

    /**
     * @param $project_id
     * @param null $record
     * @param $instrument
     */
    public function redcap_save_record($project_id, $record = NULL,  $instrument,  $event_id,  $group_id = NULL,  $survey_hash = NULL,  $response_id = NULL, $repeat_instance) {
        //If instrument is the right one, create the portal url and save it to the designated field

        //iterate through all of the sub_settings
        $target_form        = $this->getProjectSetting('main-config-form-name');

        if ($instrument == $target_form) {
            $config_field       = $this->getProjectSetting('participant-config-id-field');
            $config_event       = $this->getProjectSetting('main-config-event-name');

            //get the config_id for this participant
            $config_id          = $this->getFieldValue($record, $event_id, $config_field, $instrument, $repeat_instance);


            if ($config_id == null) {
                $this->emError("Config ID for record $record is not set.");
                return;
                $this->exitAfterHook();  //todo: ask andy, this doesn't seem to exit?
            }

            $sub = $this->getSubIDFromConfigID($config_id);

            //if sub is empty, then the participant is using a config_id that doesn't exist.
            if ($sub === false) {
                $this->emError("This $config_id entered in participant $record is not found the EM config settings.");
                return;
                $this->exitAfterHook(); //todo: ask andy, this doesn't seem to exit?
            }

            $personal_hash_field = $this->getProjectSetting('personal-hash-field')[$sub];
            $personal_url_field = $this->getProjectSetting('personal-url-field')[$sub];

            // First check if hashed portal already has been created
            $f_value = $this->getFieldValue($record, $config_event, $personal_hash_field, $instrument, $repeat_instance);
            //$this->emDebug("Saving record with this sub: ". $sub . " and this hash field " . $personal_hash_field
            //    . " is it empty?" .empty($personal_hash_field) .  " ahs this value: " . $f_value);

            if ($f_value == null) {
                //generate a new URL
                $new_hash     = $this->generateUniquePersonalHash($project_id, $personal_hash_field, $config_event);
                $portal_url   = $this->getUrl("web/landing.php", true,true);
                $new_hash_url = $portal_url. "&h=" . $new_hash . "&c=" . $config_id;

                $this->emDebug("this is new hash: ". $new_hash_url);

                // Save it to the record (both as hash and hash_url for piping)
                $event_name = REDCap::getEventNames(true,false, $config_event);
                $this->emDebug($event_id, $event_name, $config_event);

                $data = array(
                    REDCap::getRecordIdField() => $record,
                    'redcap_event_name'        => $event_name,
                    'redcap_repeat_instrument' => $instrument,
                    'redcap_repeat_instance'   => $repeat_instance,
                    $personal_url_field        => $new_hash_url,
                    $personal_hash_field       => $new_hash
                );
                $response = REDCap::saveData('json', json_encode(array($data)));
                //$this->emDebug($response,  "Save Response for count"); exit;

                if (!empty($response['errors'])) {
                    $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
                    $this->emError($msg, $response['errors']);
                }
                $this->emDebug($record . ": Set unique Hash Url to $new_hash_url with result " . json_encode($response));
            }
        }
    }

    public function getConfgIDFromSubID($sub) {

        $config_ids = $this->getProjectSetting('config-id');

        return $config_ids[$sub];
    }

    /**
     * @param $record
     * @param $filter_event : event NAME not id
     * @param $filter_field
     * @param $filter_value
     * @param null $retrieve_array
     */
    public function retrieveParticipantFieldWithFilter($record, $filter_event,  $filter_field, $filter_value, $retrieve_array = null) {

        $filter = "[" . $filter_event . "][" . $filter_field . "] = '$filter_value'";

        $params = array(
            'return_format'    => 'json',
            'events'           => $filter_event,
            'fields'           => $retrieve_array,
            'filterLogic'      => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        //$this->emDebug("RETRIEVE", $filter,$retrieve_array,  $records); exit;

        return current($records);


    }


    public function getSubIDFromConfigID($config_id) {
        $config_ids = $this->getProjectSetting('config-id');
        return array_search($config_id, $config_ids);

    }
    /**
     *
     *

    "redcap_every_page_before_render",
    "redcap_module_system_enable",
    "redcap_module_link_check_display",
    "redcap_module_save_configuration"

     */


    /**
     * @param $project_id
     * @param $url_field
     * @param $event
     * @return string
     */
    public function generateUniqueConfigID($hash_field) {
        $config_ids = $this->getProjectSetting($hash_field);
        $max = max($config_ids);

        if ($max == null) {
            return 1;
        }
        return $max + 1;

    }


    /**
     *
     *
     * @param $project_id
     * @param $url_field
     * @param $event
     * @return string
     */
    public function generateUniquePersonalHash($project_id, $hash_field, $event) {
        //$url_field   = $this->getProjectSetting('personal-url-fields');  // won't work with sub_settings

        //todo: if we are allowing multiple URLS per record, this uniqueness check won't work.
        // Generate a unique hash for this project
        $i = 0;
        do {
            $new_hash = generateRandomHash(8, false, TRUE, false);

            $this->emDebug("NEW HASH ($i):" .$new_hash);
            $params = array(
                'return_format' => 'array',
                'fields' => array($hash_field),
                'events' => $event,
                'filterLogic'  => "[".$hash_field."] = '$new_hash'"
            );
            $q = REDCap::getData($params);
//                'array', NULL, array($cfg['MAIN_SURVEY_HASH_FIELD']), $config_event[$sub],
//                NULL,FALSE,FALSE,FALSE,$filter);
            $this->emDebug($params, "COUNT IS ".count($q));
            $i++;
        } while ( count($q) > 0 AND $i < 10 ); //keep generating until nothing returns from get

        //$new_hash_url = $portal_url. "&h=" . $new_hash . "&sp=" . $project_id;

        return $new_hash;
    }

    /**
     * Convenience method to see if the REDCap field passed for this event and record is already set
     * Return value or null if not set
     *
     * @param $record
     * @param $event
     * @param $target_field
     * @return |null
     */
    public function getFieldValue($record, $event, $target_field, $instrument, $repeat_instance = 1) {

        $this->emDebug($record, $event, $target_field, $instrument, $repeat_instance);

        //Right instrument, carry on
        // First check if hashed portal already has been created
        $params = array(
            'return_format'       => 'json',
            'records'             => $record,
            'fields'              => array($target_field),
            'events'              => $event,
            'redcap_repeat_instrument' => $instrument,
            'redcap_repeat_instance'   => $repeat_instance
        );
        $params = array(
            'return_format'       => 'json',
            'records'             => $record,
            //'fields'              => array($target_field, 'redcap_repeat_instance'),
            'events'              => $event,
            'redcap_repeat_instrument' => $instrument,
            'redcap_repeat_instance'   => $repeat_instance   //this doesn't seem to do anything!
        );

        $q = REDCap::getData($params);
        $results = json_decode($q, true);

        //get the key for this repeat_instance
        $key = array_search($repeat_instance, array_column($results, 'redcap_repeat_instance'));
        $target = $results[$key][$target_field];

        return $target;
    }

    /**
     * Returns all surveys for a given record id
     *
     * @param $id  participant_id (if null, return all)
     * @param $cfg
     * @return mixed
     */
    public function getAllSurveys($id = null) {

        //get fields of each hash - separately? can't assume that they will be in the same event.
        $survey_config_field= $this->getProjectSetting('survey-config-field');
        $enable_portal = $this->getProjectSetting('enable-portal');
        $config_ids = $this->getProjectSetting('config-id');

        //run these separately by $sub (subsetting)
        $all_surveys = array();
        foreach ($enable_portal as $sub => $enabled) {

            //only execute if enabled
            if ($enabled) {
                $config_id = $config_ids[$sub];
                //$this->emDebug($config_id . " for " . $sub);
                $all_surveys[$sub] = $this->getSurveysForConfig($sub);
            }
        }

        //$this->emDebug($all_surveys); exit;
        return $all_surveys;
    }

    public function getSurveysForConfig($sub) {
        //get the config id and filter surveys on the config
        $config_id = ($this->getProjectSetting('config-id'))[$sub];
        $survey_config_field = ($this->getProjectSetting('survey-config-field'))[$sub];
        $survey_event_id = ($this->getProjectSetting('survey-event-name'))[$sub];
        $survey_event_arm_name = REDCap::getEventNames(true, false, $survey_event_id);
        $survey_event_prefix = empty($survey_event_arm_name) ? "" : "[" . $survey_event_arm_name . "]";


        if ($config_id == null) {
            $filter = null; //get all ids
        } else {
            $filter = $survey_event_prefix . "[$survey_config_field]='$config_id'";
        }


        $get_data = array(
            REDCap::getRecordIdField(),
            ($this->getProjectSetting('survey-config-field'))[$sub],
            ($this->getProjectSetting('survey-day-number-field'))[$sub],
            ($this->getProjectSetting('survey-date-field'))[$sub],
            ($this->getProjectSetting('survey-launch-ts-field'))[$sub],
            ($this->getProjectSetting('valid-day-number'))[$sub],
            ($this->getProjectSetting('survey-instrument'))[$sub] . '_complete'
        );

        $params = array(
            'return_format' => 'json',
            'fields'        => $get_data,
            'events'        => $survey_event_id,
            'filterLogic'   => $filter
        );


        $q = REDCap::getData($params);
        $results = json_decode($q,true);

        $arranged = $this->arrangeSurveyByID($sub, $results);
        return $arranged;

    }


    public function getPortalData() {
        $enable_portal = $this->getProjectSetting('enable-portal');

        //run these separately by $sub (subsetting)
        $portal_data = array();
        foreach ($enable_portal as $sub => $enabled) {

            //only execute if enabled
            if ($enabled) {
                $portal_data[$sub] = $this->getPortalDataForConfig($sub);
            }

        }
        return $portal_data;

    }




    public function getPortalDataForConfig($sub) {


        $portal_fields = array(
            REDCap::getRecordIdField(),
            ($this->getProjectSetting('start-date-field'))[$sub],
            ($this->getProjectSetting('participant-config-id-field'))[$sub]
        );

        $portal_params = array(
            'return_format' => 'json',
            'fields'        =>$portal_fields,
            'events'        => ($this->getProjectSetting('main-config-event-name'))[$sub]
        );
        $q = REDCap::getData($portal_params);
        $portal_data = json_decode($q, true);


        //rearrange so that the id is the key
        $portal_data = $this->makeFieldArrayKey($portal_data, REDCap::getRecordIdField());
        //$this->emDebug($portal_data); exit;

        return $portal_data;

    }



    public function arrangeSurveyByID($sub, $surveys ) {

         $survey_date_field = $this->getProjectSetting('survey-date-field')[$sub];
         $survey_day_number_field = $this->getProjectSetting('survey-day-number-field')[$sub];
         $survey_form_name_complete= $this->getProjectSetting('survey-instrument')[$sub] . '_complete';

        $arranged = array();

        foreach ($surveys as $k => $v) {
            $id = $v[REDCap::getRecordIdField()];
            $day_number = $v[$survey_day_number_field];

            //$this->emDebug($k, $v, $id, $day_number, $survey_day_number_field, $survey_form_name_complete); exit;

            $arranged[$id][$day_number] = array(
                "SURVEY_DATE"  => $v[$survey_date_field],
                "STATUS"       => $v[$survey_form_name_complete]
            );
        }

        return $arranged;

    }

    public function dumpResource($name) {
        $file =  $this->getModulePath() . $name;
        if (file_exists($file)) {
            $contents = file_get_contents($file);
            echo $contents;
        } else {
            $this->emError("Unable to find $file");
        }
    }




    /**
     * Make key_field as key
     *
     * @param $data
     * @param $key_field
     * @return array
     */
    static function makeFieldArrayKey($data, $key_field) {
        $r = array();
        foreach ($data as $d) {
            $r[$d[$key_field]] = $d;
        }
        return $r;

    }

    /**
     * Get the bookmark html
     * @return string
     */
    static function getBookmarkHelp() {
        $html=<<<EOD
    <div class='container text-center'>
        <p>You may bookmark this page to your home screen for faster access in the future.</p>
        <div class='text-center'>
            <span><a href='#Instructions' class='btn btn-default' data-toggle='collapse'>Show Instructions</a></span>
            <div id='Instructions' class='collapse'>
                <br/>
                <div class='panel panel-default'>
                    <div class='panel-body text-left'>
                        <p><strong>On an iOS phone</strong></p>
                         <ol>
                             <li>If you do not see the toolbar at the bottom of your Safari window, tap once at the bottom of your screen</li>
                             <li>Click on the Action button in the center of the toolbar (a square box with an upward arrow)</li>
                             <li>Scroll the lower row of options to the right until you see 'Add to Home Screen' (a box with a plus sign).</li>
                         </ol>
                         <p><strong>On an Android phone</strong></p>
                         <ol>
                             <li>Open the Chrome menu <img src="//lh3.googleusercontent.com/vOgJaWNbkf_Y0kOEQXe4wSlufkMuTb8NqGMIXSP-mRm72oR4ABGkR1L4sXyMmb7lBHnz=h18" width="auto" height="18" alt="More" title="More">.</li>
                             <li>Tap the star icon <img src="//lh3.ggpht.com/SEdDjoaQ-qufNcDGhJh5KXW0q3-tABnuWjM5fpqE9kbOyJaXN3co5MEcQu7kqoCIqHA5O84=w20" width="20" height="18" alt="Bookmark" title="Bookmark">.</li>
                             <li>Optional: If you want to edit the bookmark's name and URL or change the folder, go to the bottom bar and tap&nbsp;<strong>Edit</strong>.</li>
                             <li>When you're done, tap the checkmark .</li>
                             <li>Optional:  If you want to make the bookmark appear on your home screen (like an app) open the bookmarks folder and <strong>press and hold</strong> your finger on the bookmark.  A new menu will appear with an option to Add to Home screen</li>
                         </ol>
                         <p><strong>On an Android tablet</strong></p>
                         <ol>
                             <li>In the address bar at the top, tap the star icon&nbsp;<img src="//lh3.ggpht.com/SEdDjoaQ-qufNcDGhJh5KXW0q3-tABnuWjM5fpqE9kbOyJaXN3co5MEcQu7kqoCIqHA5O84=w20" width="20" height="18" alt="Bookmark" title="Bookmark">.</li>
                             <li>Optional: If you want to edit the bookmark's name and URL or change the folder, go to the bottom bar and tap&nbsp;<strong>Edit</strong>.</li>
                             <li>When you're done, tap the checkmark .</li>
                             <li>Optional:  If you want to make the bookmark appear on your home screen (like an app) open the bookmarks folder and <strong>press and hold</strong> your finger on the bookmark.  A new menu will appear with an option to Add to Home screen</li>
                         </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
EOD;
        return $html;
    }

    function emText($number, $text) {
        global $module;

        $emTexter = ExternalModules\ExternalModules::getModuleInstance('twilio_utility');
        $text_status = $emTexter->emSendSms($number, $text);
        return $text_status;
    }


    function emLog()
    {
        global $module;
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($module->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }
}