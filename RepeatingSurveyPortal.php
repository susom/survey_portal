<?php

namespace Stanford\RepeatingSurveyPortal;



use ExternalModules\ExternalModules;
use \REDCap;
use \DateTime;
use \Message;
use Exception;

require_once 'src/Participant.php';
require_once 'src/PortalConfig.php';
require_once 'src/InsertInstrumentHelper.php';

/**
 * Class RepeatingSurveyPortal
 * @package Stanford\RepeatingSurveyPortal
 *
 *
 * WEB
 *
 * Portal Landing Page
 *
 * src/landing.php   NOAUTH
 * src/forecast.php (tries to show what will happen based on certain dates)
 * src/cron.php     NOAUTH (landing page to instantiate cron)
 *  - load the project config, for each config, it will execute check to see if each record needs notification...
 *  -
 *
 *
 *
 */
class RepeatingSurveyPortal extends \ExternalModules\AbstractExternalModule
{

    const KEY_VALID_CONFIGURATION = "survey_portal_config_valid";
    const PARTICIPANT_INFO_FORM   = "rsp_participant_info";
    const SURVEY_METADATA_FORM   = "rsp_survey_metadata";

    public $iih;

    /*******************************************************************************************************************/
    /* HOOK METHODS                                                                                                    */
    /***************************************************************************************************************** */


    public function redcap_module_system_enable() {
        // SET THE
        // Do Nothing
        //create instrument participant info. upload zip for instrument
        REDCap::getInstrumentNames(); //to get instrument name

        //upload instrument zip

        //verify that default fields aren't already existing. if exists, then abort
        //if pi_ already exists, notify admin that the field already exists
         \ExternalModules\ExternalModules::sendAdminEmail('subject', 'message');

         //make sure its in dev mode
        //status > 0
        //$current_forms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;

         //make sure form name doesn't already exist.

        //insert the form
    }


    public function redcap_module_link_check_display($project_id, $link) {
        // TODO: Loop through each portal config that is enabled and see if they are all valid.
        //TODO: ask andy123; i'm not sure what KEY_VALID_CONFIGURATION is for...
        //if ($this->getSystemSetting(self::KEY_VALID_CONFIGURATION) == 1) {
        list($result, $message)  = $this->getConfigStatus();
        if ($result === true) {
                    // Do nothing - no need to show the link
        } else {
            $link['icon'] = "exclamation";
        }
        return $link;
    }

    // SAVE CONFIG HOOK
    // if config-id is null, then generate a config id for that the configs...
    //todo: HOLD ON THIS. saving works, but delete ignores this setting we add. punt for now.
    public function redcap_module_save_configuration($project_id) {
    }


    //REDCap_survey_complete
    public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id,  $survey_hash,  $response_id,  $repeat_instance) {
        $cookie_key = $this->PREFIX."_".$project_id."_".$record;  //this won't work if mother/child are on same machine at same time.
            //$module->PREFIX."_".$project_id."_".$portal->getParticipantId()
        $cookie_config = $_COOKIE[$cookie_key];

        //$this->emDebug("COOKIE KEY ". $cookie_key);
        //$this->emDebug("COOKIE CONFIG ". $cookie_config);


        //if redirect has been turned on redirect to the landing page

        $sub = $this->getSubIDFromConfigID($cookie_config);
        //$this->emDebug("COOKIE CONFIG ". $cookie_config . "SUB ".$sub);

        $redirect = $this->getProjectSetting('survey-complete-redirect')[$sub];

        if (isset($redirect) && ($redirect == $instrument) ) {
            $this->emDebug($redirect);

            $config_event_id = $this->getProjectSetting('main-config-event-name')[$sub];
            $config_event_name = REDCap::getEventNames(true, false, $config_event_id);
            $config_field = $this->getProjectSetting('participant-config-id-field');
            $hash_field = $this->getProjectSetting('personal-hash-field')[$sub];
            $hash_return  = $this->retrieveParticipantFieldWithFilter($record, $config_event_name, $config_field,$cookie_config, array($hash_field) );
            $hash = $hash_return[$hash_field];

            $portal_url   = $this->getUrl("src/landing.php", true,true);
            $return_hash_url = $portal_url. "&h=" . $hash . "&c=" . $cookie_config;

            $this->emDebug("this is new hash: ". $return_hash_url);

            //now redirect back to the landing page
            header("Location: " . $return_hash_url);
            $this->exitAfterHook();  //TODO: should there be an exit at the end of the hook?
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
        $target_forms        = $this->getProjectSetting('main-config-form-name');


        foreach ($target_forms as $sub => $target_form) {


            if ($instrument == $target_form) {

                $config_field = $this->getProjectSetting('participant-config-id-field');
                $config_event = $this->getProjectSetting('main-config-event-name')[$sub];

                //get the config_id for this participant
                $config_id = $this->getFieldValue($record, $event_id, $config_field, $instrument, $repeat_instance);


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
                    //$this->exitAfterHook(); //todo: ask andy, this doesn't seem to exit?
                }

                $personal_hash_field = $this->getProjectSetting('personal-hash-field')[$sub];
                $personal_url_field = $this->getProjectSetting('personal-url-field')[$sub];

                //$this->emDebug($sub,  $this->getProjectSetting('personal-url-field'),$personal_hash_field, $personal_url_field); exit;

                // First check if hashed portal already has been created
                $f_value = $this->getFieldValue($record, $config_event, $personal_hash_field, $instrument, $repeat_instance);
                //$this->emDebug("Saving record with this sub: ". $sub . " and this hash field " . $personal_hash_field
                //    . " is it empty?" .empty($personal_hash_field) .  " ahs this value: " . $f_value);

                if ($f_value == null) {
                    //generate a new URL
                    $new_hash = $this->generateUniquePersonalHash($project_id, $personal_hash_field, $config_event);
                    $portal_url = $this->getUrl("src/landing.php", true, true);
                    $new_hash_url = $portal_url . "&h=" . $new_hash . "&c=" . $config_id;

                    $this->emDebug("this is new hash: " . $new_hash_url);

                    // Save it to the record (both as hash and hash_url for piping)
                    $event_name = REDCap::getEventNames(true, false, $config_event);
                    $this->emDebug($event_id, $event_name, $config_event);

                    $data = array(
                        REDCap::getRecordIdField() => $record,
                        'redcap_event_name' => $event_name,
                        'redcap_repeat_instrument' => $instrument,
                        'redcap_repeat_instance' => $repeat_instance,
                        $personal_url_field => $new_hash_url,
                        $personal_hash_field => $new_hash
                    );
                    $response = REDCap::saveData('json', json_encode(array($data)));
                    //$this->emDebug($sub, data, $response,  "Save Response for count"); exit;

                    if (!empty($response['errors'])) {
                        $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
                        $this->emError($msg, $response['errors']);
                    }
                    $this->emDebug($record . ": Set unique Hash Url to $new_hash_url with result " . json_encode($response));
                }
            }
        }
    }


    /*******************************************************************************************************************/
    /* CRON METHODS                                                                                                    */
    /***************************************************************************************************************** */

    /**
     * TODO: Add cron to config.json
     *
     * 1) Determine projects that are using this EM
     * 2) Instantiate instance of EM for each project
     * 3)
     */
    public function inviteCron() {

        $this->emDebug("STARTING INVITE CRON");
        //* 1) Determine projects that are using this EM
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('src/InviteCron.php', true, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //check scheduled hour of send
            $scheduled_hour = $this->getProjectSetting('invitation-time', $pid);
            $current_hour = date('H');

            //iterate through all the sub settings
            foreach ($scheduled_hour as $sub => $invite_time) {

                //TODO: check that the 'enable-invitations' is not set. test this
                $enabled_invite = $this->getProjectSetting('enable-invitations', $pid)[$sub];
                if ($enabled_invite == '1') {

                    $this->emDebug("PROJECT $pid : SUB $sub scheduled at this hour $invite_time vs current hour: $current_hour");

                    //if not hour, continue
                    if ($invite_time != $current_hour) continue;

                    $this_url = $url . '&pid=' . $pid . "&s=" . $sub;
                    $this->emDebug("INVITE CRON URL IS " . $this_url);

                    $resp = http_get($this_url);
                    //$this->cronAttendanceReport($pid);
                    $this->emDebug("cron for invitations: " . $resp);
                }
            }
        }

    }


    /**
     * * TODO: Add cron to config.json
     */
    public function reminderCron() {

        $this->emDebug("STARTING REMINDER CRON");

        //* 1) Determine projects that are using this EM
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('src/ReminderCron.php', true, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //check scheduled hour of send
            $scheduled_hour = $this->getProjectSetting('reminder-time', $pid);
            $current_hour = date('H');

            //iterate through all the sub settings
            foreach ($scheduled_hour as $sub => $reminder_time) {
                //TODO: check that the 'enable-reminders' is not set. test this
                $enabled_reminder = $this->getProjectSetting('enable-reminders', $pid)[$sub];
                if ($enabled_reminder == '1') {

                    $this->emDebug("project $pid - $sub scheduled at this hour $reminder_time vs current hour: $current_hour");

                    //if not hour, continue
                    if ($reminder_time != $current_hour) continue;

                    $this_url = $url . '&pid=' . $pid . "&s=" . $sub;
                    $this->emDebug("REMINDER CRON URL IS " . $this_url);

                    $resp = http_get($this_url);
                    //$this->cronAttendanceReport($pid);
                    $this->emDebug("cron for reminder: " . $resp);
                }
            }
        }
    }


    /*******************************************************************************************************************/
    /*  METHODS                                                                                                    */
    /***************************************************************************************************************** */


    public function getConfigStatus() {

        $iih = new InsertInstrumentHelper($this);

        $alerts = array();
        $result = false;

        $main_events = $this->getProjectSetting('main-config-event-name');

        $survey_events = $this->getProjectSetting('survey-event-name');
        //$this->emDebug("SURVEY",$survey_events);

        if (!$iih->formExists(self::PARTICIPANT_INFO_FORM)) {
            $p = "<b>Participant Info form has not yet been created. </b> 
              <div class='btn btn-xs btn-primary float-right' data-action='insert_form' data-form='" . self::PARTICIPANT_INFO_FORM ."'>Create Form</div>";
            $alerts[] = $p;
        } else {
            // Form exists - check if enabled on event
            foreach ($main_events as $sub => $event) {
                if (isset($event)) {
                    if (!$iih->formDesignatedInEvent(self::PARTICIPANT_INFO_FORM, $event)) {
                        $event_name = REDCap::getEventNames(false, true, $event);
                        $pe = "<b>Participant Info form has not been designated to the event selected for the main event: <br>".$event_name.
                            " </b><div class='btn btn-xs btn-primary float-right' data-action='designate_event' data-event='".$event.
                            "' data-form='".self::PARTICIPANT_INFO_FORM."'>Designate Form</div>";
                        $alerts[] = $pe;
                    }
                }
            }
        }

        if (!$iih->formExists(self::SURVEY_METADATA_FORM)) {
            $s=  "<b>Survey Info form has not yet been created. </b> 
              <div class='btn btn-xs btn-primary float-right' data-action='insert_form' data-form='" . self::SURVEY_METADATA_FORM . "'>Create Form</div>";
            $alerts[] = $s;
        } else {
            foreach ($survey_events as $sub => $event) {
                if (isset($event)) {
                    if (!$iih->formDesignatedInEvent(self::SURVEY_METADATA_FORM, $event)) {
                        $event_name = REDCap::getEventNames(false, true, $event);
                        $se = "<b>Survey Metadata form has not been designated to the event selected for the survey event: <br>".$event_name.
                            " </b><div class='btn btn-xs btn-primary float-right' data-action='designate_event' data-event='".$event.
                            "' data-form='".self::SURVEY_METADATA_FORM."'>Designate Form</div>";
                        $alerts[] = $se;
                    }
                }
            }
        }

        //$this->emDebug($alerts);

        if (empty($alerts)) {
            $result = true;
            $alerts[] = "Your configuration appears valid!";
        }

        return array( $result, $alerts );
    }

    public function insertForm($form) {
        $iih = new InsertInstrumentHelper($this);

        $result = $iih->insertForm($form);
        $message = $iih->getErrors();

//        $this->emDebug("INSERT FORM". $form);
//        switch ($form) {
//            case "pi" :
//                $status = $iih->insertParticipantInfoForm();
//                break;
//            case "md" :
//                $status = $iih->insertSurveyMetadataForm();
//                break;
//            default:
//                $status  = false;
//        }
//
//
//        $errors = $status ? null :$iih->getErrors();
//
//        //$status = $this->getConfigStatus();
        $this->emDebug("RETURN STATUS", $result, $message);

        return array($result, $message);

    }


    public function designateEvent($form, $event) {
        $iih = new InsertInstrumentHelper($this);

        $this->emDebug("DESIGNATING EVENT: ". $form . $event);
        $result = $iih->designateFormInEvent($form, $event);
        $message = $iih->getErrors();

        $this->emDebug("RETURN STATUS", $result, $message);

        return array($result, $message);

    }

    /*******************************************************************************************************************/
    /* HELPER METHODS                                                                                                    */
    /***************************************************************************************************************** */




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
            'records'          => $record,
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

    public function getConfigIDFromSubID($sub) {
        $config_ids = $this->getProjectSetting('config-id');
        return $config_ids[$sub];
    }

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
    public function  getAllSurveys($id = null) {

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


    /**
     * Returns the portal related data for each participant by sub
     *
     * @return array
     */
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


    /**
     * Return portal data for the participants which is assigned to this sub setting
     *
     * @param $sub
     * @return array|mixed
     */
    public function getPortalDataForConfig($sub) {

        //get the config id associated with this subsetting id
        $config_id = $this->getConfigIDFromSubID($sub);

        $this->emDebug("SUB IS " . $sub . " and config id is ". $config_id);

        //filter out participant for which this config id is assigned
        $main_event_id = ($this->getProjectSetting('main-config-event-name'))[$sub];
        $survey_event_id = ($this->getProjectSetting('survey-event-name'))[$sub];
        $main_event_name = REDCap::getEventNames(true, false, $main_event_id);
        $config_field = ($this->getProjectSetting('participant-config-id-field'));


        $filter = "[" . $main_event_name . "][" . $config_field . "] = '{$config_id}'";

        $portal_fields = array(
            REDCap::getRecordIdField(),
            ($this->getProjectSetting('start-date-field'))[$sub],
            ($this->getProjectSetting('participant-config-id-field'))
        );

        $portal_params = array(
            'return_format' => 'json',
            'fields'        => $portal_fields,
            'filterLogic'   => $filter,
            'events'        => $main_event_id
        );
        $q = REDCap::getData($portal_params);
        $portal_data = json_decode($q, true);


        //rearrange so that the id is the key
        $portal_data = $this->makeFieldArrayKey($portal_data, REDCap::getRecordIdField());

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
     * @param $input    A string like 1,2,3-55,44,67
     * @return mixed    An array with each number enumerated out [1,2,3,4,5,...]
     */
    static function parseRangeString($input) {
        $input = preg_replace('/\s+/', '', $input);
        $string = preg_replace_callback('/(\d+)-(\d+)/', function ($m) {
            return implode(',', range($m[1], $m[2]));
        }, $input);
        $array = explode(",",$string);
        return empty($array) ? false : $array;
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


    /*******************************************************************************************************************/
    /* EXTERNAL MODULEXS METHODS                                                                                                    */
    /***************************************************************************************************************** */



    function emText($number, $text) {
        global $module;

        $emTexter = ExternalModules::getModuleInstance('twilio_utility');
        //$this->emDebug($emTexter);
        $text_status = $emTexter->emSendSms($number, $text);
        return $text_status;
    }


    function emLog()
    {
        global $module;
        $emLogger = ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($module->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || ( !empty($_GET['pid']) && $this->getProjectSetting('enable-project-debug-logging'))) {
            $emLogger = ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }
}