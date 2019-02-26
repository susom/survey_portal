<?php



namespace Stanford\RepeatingSurveyPortal;



/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Portal $Portal */

//require_once $module->getModulePath().'Portal.php';

use REDCap;
use DateTime;
use Exception;

class Participant
{

    public $portalConfig;  //config for the subsetting
    public $module;

    //This participant's survey data
    public $participant_hash;
    public $participantID;   //PK of this participant
    public $start_date;       //starting date of
    public $survey_status;    //survey from start_date to endate with date / day_number/ valid/ completed
    public $event_name;
    public $valid_day_array;
    public $config_id;      // subsetting config ID
    public $max_instance;   //last instance number


    public function __construct($portalConfig, $hash) {
        global $module;
        $this->portalConfig = $portalConfig;

        $this->event_name = REDCap::getEventNames(true, false, $this->surveyEventName);


        //setup the participant surveys
        //given the hash, find the participant and set id and start date in object
        $this->participantID =  $this->locateParticipantFromHash($hash);

        //$module->emDebug($this->participantID, $hash);

        if ($this->participantID == null) {
            throw new Exception("Participant not found from this hash: ".$hash);

        }


        //get all Surveys for this particpant and determine status
        //limit the
        $this->survey_status = $this->getAllSurveyStatus(
            $this->participantID,
            min($this->portalConfig->validDayArray),
            max($this->portalConfig->validDayArray));

        //$module->emDebug($this->getValidDates()); exit;
        //$window_dates = $module->getValidDayNumbers($participant, $project_id, $cfg['START_DATE_FIELD'], $cfg['START_DATE_EVENT'], $valid_day_number_array);


        // Get the status' for each date in the window array
        //$window_dates = $module->getSurveyStatusArray($participant, $window_dates, $cfg);

    }


    /**
     * Construct a status array using startdate and final date
     * todo: double check that we should do it this way to collect data on surveys taken on 'invalid' days
     *
     *
     * date
     *    record_name
     *    day_number  : get all the dates from
     *    valid       : T F
     *    complete    : T / F
     *    date_taken  : might differ from assigned date because of window
     *
     *
     *
     * @param $id
     */
    public function getAllSurveyStatus($participantID, $min, $max) {
        global $module;
        $survey_status = array();

        $all_surveys = $this->getAllSurveys($participantID);
        //$this->max_instance = max(array_keys($all_surveys));
        $max_repeat_instance = max(array_column($all_surveys, 'redcap_repeat_instance'));
        $this->max_instance = $max_repeat_instance;

        //$module->emDebug($all_surveys, $this->max_instance, $max_repeat_instance); exit;
        //$module->emDebug($this->valid_day_array, $this->start_date);

        $start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $date = $start_date;

        //$module->emDebug($all_surveys, $min, $max); exit;
        for ($i = $min; $i <= $max; $i++) {

            $found_survey_key = array_search($i, array_column($all_surveys, $this->portalConfig->surveyDayNumberField));

            $date_str = $date->format('Y-m-d');
            $survey_status[$date_str]['day_number']  = $i;
            $survey_status[$date_str]['valid']       = in_array($i, $this->portalConfig->validDayArray);
            if (!($found_survey_key === false)) { //because one of the found keys is 0 which reads as false.
                $survey_status[$date_str]['completed'] = $all_surveys[$found_survey_key][$this->portalConfig->surveyInstrument . '_complete'];
                $survey_status[$date_str]['survey_date'] = $all_surveys[$found_survey_key][$this->portalConfig->surveyDateField];
                $survey_status[$date_str]['date_taken']  = $all_surveys[$found_survey_key][$this->portalConfig->surveyLaunchTSField];
            }
            $date = $start_date->modify('+ 1 days');
        }


        return $survey_status;


    }

    public function locateParticipantFromHash($hash) {
        global $module;

        $filter = "[" . $this->portalConfig->mainConfigEventName . "][" . $this->portalConfig->personalHashField . "] = '$hash'";

        // Use alternative passing of parameters as an associate array
        $params = array(
            'return_format' => 'json',
            'events'        =>  $this->portalConfig->mainConfigEventName,
            'fields'        => array( REDCap::getRecordIdField(), $this->portalConfig->personalHashField, $this->portalConfig->startDateField),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        // return record_id or false
        $main = current($records);

        $this->participantID = $main[REDCap::getRecordIdField()];
        $this->start_date    = $main[$this->portalConfig->startDateField];
        return ($this->participantID);
    }



    /**
     *
     *
     * @param $date
     */
    public function getDayNumberFromDate($date) {
        global $module;

        $date_str = $date->format('Y-m-d');

        $day_number = $this->survey_status[$date_str]['day_number'];

        return $day_number;

    }

    public function getSurveyDateFromDayNumber($day_number) {
        global $module;

        //$survey_date_3 = array_search($day_number, array_column( $this->survey_status, "[day_number]"));

        foreach ($this->survey_status as $survey_date => $val) {

            if ($val['day_number'] == $day_number) {

                return new DateTime($survey_date);
            }
        }
        return null;

    }

     /**
     * Returns all surveys for a given record id
     *
     * @param $id
     *
     * @return mixed
     */
    public function getAllSurveys($id) {
        global $module;

        //restrict the getAllSurveys to the config for this participant

        //$filter = "[" . $this->event_name . "][" . $this->surveyFKField . "] = '$id'";
        $filter = "[" . $this->portalConfig->surveyEventName . "][" .$this->portalConfig->surveyConfigField . "] = '{$this->portalConfig->configID}'";

        $get_array = array(
            REDCap::getRecordIdField(),
            $this->portalConfig->configID,
            $this->portalConfig->surveyDayNumberField,
            $this->portalConfig->surveyDateField,
            $this->portalConfig->surveyLaunchTSField,
            $this->portalConfig->surveyInstrument . '_complete');

        $params = array(
            'return_format' => 'json',
            'records'       => $id,
            'events'        => $this->portalConfig->surveyEventName,
            'fields'        => $get_array,
            'filterLogic'   => $filter
            //how about surveyTimestampField, surveyDateField
        );

        $q = REDCap::getData($params);

        $results = json_decode($q,true);

        //$module->emDebug($filter, $params,$results, "ALL SURVEY GET");

        return $results;

    }

    public function getCountOfDayNumber($id, $day_number) {
        global $module;

        $filter = '';
        $params = array(
            'return_format' => 'json',
            'records'       => $id

        );
    }

    public function isDayLagValid($survey_date) {
        global $module;
        if (!isset($this->portalConfig->validDayLag)) {
            $module->emDebug("not set");
            return true;
        } else {
            $today = new DateTime();

            $date_diff = $today->diff($survey_date)->days;
            if ($date_diff <= $this->portalConfig->validDayLag ) {
                $module->emDebug("valid day lag".  $date_diff, $today, $survey_date);
                return true;
            }
        }
        $module->emDebug("FAILED in valid day lag. Date DIff : ". $date_diff, $today, $survey_date);
        return false;
    }

    public function isStartTimeValid($survey_date) {
        global $module;
        if (!isset($this->portalConfig->earliestTimeAllowed)) {
            $module->emDebug("not set", $survey_date);
            return true;
        } else {
            $allowed_earliest = $survey_date->setTime($this->portalConfig->earliestTimeAllowed , 0);
            $module->emDebug($allowed_earliest);

            $now = new DateTime();


            if ($now >= $allowed_earliest ) {
                $module->emDebug("valid time ".  $allowed_earliest->format('Y-m-d H:i:s'), $now->format('Y-m-d  H:i:s'));
                return true;
            }
        }
        $module->emDebug("FAILED with invalid start time. Date DIff : ". $now->format('Y-m-d H:i:s') . " vs " . $allowed_earliest->format('Y-m-d H:i:s'));
        return false;
    }




    /** This version only allows one */
    public function isMaxResponsePerDayValid($day_number, $survey_date) {
        global $module;
        $survey_date_str = $survey_date->format('Y-m-d');
        $survey_complete = $this->survey_status[$survey_date_str]['completed'];

        $module->emDebug($this->survey_status[$survey_date_str], $survey_complete,  ($survey_complete) == 2, "SURVEY COMPLETED?");

        if (($survey_complete) == 2) {
            return false;
        }
        return true;

    }

    /**
     *
     */
    public function getNextInstanceID() {
        global $module;
        $record = $this->participantID;
        $event = $this->portalConfig->surveyEventID;
        $instrument = $this->portalConfig->surveyInstrument;

        //$module->emDebug("MAX ID 2", $record, $event, $instrument);
        //getData for all surveys for this reocrd
         //get the survey for this day_number and survey_data
        $params = array(
            'return_format'       => 'array',
            //fields'              => $get_fields,
            'records'             => $this->participantID,
            'events'              => $this->portalConfig->surveyEventID
        );
        $q = REDCap::getData($params);
        $results = json_decode($q, true);

        /**
         * array return gives you
         * [record]
         *    [event_id]
         *       ['repeat_instance']
         *           [event_id]
         *              [survey_name]
         *                 [repeat_instance_id]
         */

        //$used_instance_ids = $q[$this->participantID][$this->portalConfig->surveyEventID]['repeat_instances'][$this->portalConfig->surveyEventID][$this->portalConfig->surveyInstrument];
        $used_instance_ids = $q[$record]['repeat_instances'][$event][$instrument];
        if ($used_instance_ids == null) {
            $max_id = 0;
        } else {
            $max_id = max(array_keys($used_instance_ids));
        }
        //$module->emDebug($results, $q, $used_instance_ids, $max_id);

        return $max_id + 1;




    }

    public function getPartialResponseInstanceID($day_number, $survey_date) {
        global $module;
        $survey_date_str = $survey_date->format('Y-m-d');
        $survey_complete = $this->survey_status[$survey_date_str]['completed'];
        $filter  =  "[" . $this->portalConfig->surveyEventName . "][".$this->portalConfig->surveyDayNumberField."] = '$day_number'";  // and config_id is config
        $filter .= " and [" . $this->portalConfig->surveyEventName . "][" .$this->portalConfig->surveyConfigField . "] = '{$this->portalConfig->configID}'";

        //can only get redcap_repeat_instance if all the fields are retrieved!!
        $get_fields = array(
            'redcap_repeat_instance',
            $this->portalConfig->surveyDayNumber,
            $this->portalConfig->surveyDateNumber,
            $this->portalConfig->surveyInstrument . '_complete'
        );

        //get the survey for this day_number and survey_data
        $params = array(
            'return_format'       => 'json',
            //fields'              => $get_fields,
            'records'             => $this->portalConfig->participantID,
            'events'              => $this->portalConfig->surveyEventID,
            'filterLogic'         => $filter
        );

        $module->emDebug($params);
        $q = REDCap::getData($params);
        $results = json_decode($q, true);

        //just in case there are more than one (shouldn't happen), get the key by the largest timestamp
        $latest_key = array_keys($results, max($results))[0];

        //if 0 or 1, return the redcap_repeat_instance, otherwise
        $survey_complete = $results[$latest_key][$this->portalConfig->surveyInstrument . '_complete'];
        $timestamp       = $results[$latest_key][$this->portalConfig->surveyLaunchTSField];

        //$module->emDebug($results, $q, $this->portalConfig->surveyEventID, $this->participantID); exit;
        //$module->emDebug($this->portalConfig->surveyInstrument . '_complete',            $survey_complete, $survey_complete == '0', $survey_complete == '1'); exit;

        $max_repeat_instance = 0;
        //if (($survey_complete == '0') || ($survey_complete == '1')) {
        if (isset($timestamp)) {
            $max_repeat_instance =  $results[$latest_key]['redcap_repeat_instance'];
            $module->emDebug($max_repeat_instance,$results[$latest_key], $survey_complete,  "Existing INSTANCE");
        } else {
            //it's new, so just get the next instance id to create new one.
            //can't return this->max_instance because instance IDs are shared between parent and child.
            //so need to get max instance ID for this RECORD, instance ids are sequential by record.
            //$max_repeat_instance = $this->max_instance +1;

            $max_repeat_instance  = $this->getNextInstanceID();


            //$module->emDebug($survey_complete, $results[$latest_key], $max_repeat_instance, "NEW INSTANCE");
        }

        return $max_repeat_instance;

    }

    public function newSurveyEntry($day_number, $survey_date, $instance) {
        global $module;

        /**
        //check max-response-per-day / base case is 1
        if ($this->portalConfig->maxResponsePerDay == 1) {

            //see how many responses already exist for this day_number

            //TODO: refresh survey_status?? is this overkill?  should refresh happen at end of survey hook?
            $this->survey_status = $this->getAllSurveyStatus(
                $this->participantID,
                min($this->portalConfig->validDayArray),
                max($this->portalConfig->validDayArray));

            //get the status for the survey_date
            $this->survey_status[$survey_date->format('Y-m-d')]['completed'];
        }
         */

        $params = array(
            REDCap::getRecordIdField()                => $this->participantID,
            "redcap_event_name"                       => $this->portalConfig->surveyEventName,
            "redcap_repeat_instrument"                => $this->portalConfig->surveyInstrument,
            "redcap_repeat_instance"                  => $instance,
            $this->portalConfig->surveyConfigField    => $this->portalConfig->configID,
            $this->portalConfig->surveyDayNumberField => $day_number,
            $this->portalConfig->surveyDateField      => $survey_date->format('Y-m-d'),
            $this->portalConfig->surveyLaunchTSField  => date("Y-m-d H:i:s")
        );

        $result = REDCap::saveData('json', json_encode(array($params)));
        if ($result['errors']) {
            $module->emError($result['errors'], $params);
        }

    }

    public function getFirstDate() {
        global $module;

        $dates = array_keys($this->survey_status);

        return min($dates);
    }

    public function getLastDate() {
        global $module;
        $dates = array_keys($this->survey_status);
        return max($dates);
    }

    /**
     * Return array of 'valid' survey dates
     * Current guess is that the desired format is
     *   [date]['STATUS'] = 1/2/0 - REDCap completion status?
     *
     * @return array
     */
    public function getValidDates() {
        global $module;
        //$module->emDebug($this->survey_status);
        $valid_dates = array();
        foreach ($this->survey_status as $date => $status) {
            if ($status['valid']) {
                $valid_dates[$date]['STATUS'] = $status['completed'] ? $status['completed'] : 0;
            }
        }
        //$module->emDebug($valid_dates);
        return $valid_dates;

    }


    /**
     * Return array of 'invalid' survey dates
     *  - not in valid days list described in config
     *  - todo: also exclude time window considerations??
     *  - todo: also exclude if completed?  I think no; already handeled by completed
     * @return array
     */
    public function getInvalidDates() {
        global $module;
        //$module->emDebug($this->survey_status);
        $invalid_dates = array();
        foreach ($this->survey_status as $date => $status) {
            if (!$status['valid']) {
                $invalid_dates[$date] = '1';
            }
        }
        //$module->emDebug($invalid_dates);
        return array_keys($invalid_dates);

    }



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
     */



    // /**
    //  * Factory method for creating a portal
    //  *
    //  * @param $field_name
    //  * @param $form_name
    //  * @param $field_label
    //  * @param string $field_annotation
    //  * @return Field
    //  */
    // public static function create($field_name, $form_name, $field_label, $field_annotation = '')
    // {
    //     // Any subclass can use this factory method because we detect the calling class
    //     try {
    //         $class = new \ReflectionClass(static::class);
    //     } catch (\ReflectionException $e) {
    //         // It is impossible for this to occur. static::class will always yield a valid class name
    //         return new Portal();
    //     }
    //
    //     /** @var Portal $portal */
    //     $portal = $class->newInstance();
    //     $portal->
    //
    //     $field->field_name = $field_name;
    //     $field->form_name = $form_name;
    //     $field->field_label = $field_label;
    //     $field->field_annotation = $field_annotation;
    //     return $field;
    // }
}