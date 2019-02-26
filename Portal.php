<?php

namespace Stanford\RepeatingSurveyPortal;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */
/** @var \Stanford\RepeatingSurveyPortal\Participant $participant */
/** @var \Stanford\RepeatingSurveyPortal\PortalConfig  $portalConfig */

require_once $module->getModulePath().'Participant.php';
require_once $module->getModulePath().'ParticipantMultipleResponse.php';
require_once $module->getModulePath().'PortalConfig.php';



use REDCap;

class Portal
{

    public $event_name;
    public $participant_hash;
    public $participant_id;
    public $survey_statuses;

    public $valid_day_array;

    public $portalConfig;
    public $portalSubSettingID;

    public $portalConfigID;

    public $participantID;
    public $participant;  // participant object

    public function __construct($config_id, $hash) {
        global $module;

        $this->portalConfig = new PortalConfig($config_id);
        //$module->emDebug($this->portalConfig); exit;

        $sub = $this->portalConfig->getSubsettingID();
        //$module->emDebug("Using SUB:  ". $sub . ' for CONFIG_ID: '. $config_id);

        //set event_name to the participant event from id
        $event_name = REDCap::getEventNames(true, false, $this->mainConfigEventName);

        //$module->emDebug($valid_day_array, $this->validDayNumber, $config['valid-day-number']['value'][$sub],"VALID DAY"); exit;
        //setup the participant
        //TODO: if multiple response per day is allowed, then use different class, ParticipantMultipleResponse

        if ($this->portalConfig->maxResponsePerDay != 1) {
            $this->participant = new ParticipantMultipleResponse($this->portalConfig, $hash);
        } else {
            $this->participant = new Participant($this->portalConfig, $hash);
        }
        $this->participantID = $this->participant->participantID;


    }

    public function setPortalConfigs() {
        global $module;
        //$event_name = REDCap::getEventNames(true, false, $this->mainConfigEventName);
        //$filter = "[" . $this->event_name . "][" . $this->personalHashField . "] = '$hash'";


        // Use alternative passing of parameters as an associate array
        $params = array(
            'return_format' => 'array',
            'events'        => $this->event_name,
            'fields'        => array( REDCap::getRecordIdField(), $this->validDayNumber, $this->validDayLag),
        );

        $records = REDCap::getData($params);

    }

    public function getParticipantId() {
        $this->participant->participant_id;
    }

    public function getParticipant() {
        return $this->participant;
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
     * Returns all surveys for a given record id
     *
     * @param $id
     *
     * @return mixed
     */
    public function getAllSurveys($id) {
        $event_name = REDCap::getEventNames(true, false, $this->surveyEventName);
        $filter = "[" . $event_name . "][" . $this->surveyFKField . "] = '$id'";

        $params = array(
            'return_format' => 'json',
            'events'        => $this->surveyEventName,
            'fields'        => array( REDCap::getRecordIdField(),$this->surveyFKField, $this->surveyInstrument,$this->surveyDayNumberField),
            //how about surveyTimestampField, surveyDateField
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);

        $results = json_decode($q,true);

        return $results;

    }



    /**
     * @param $pk
     * @param $project_id
     * @param $start_field
     * @param $start_field_event
     * @param $valid_day_number_array
     * @return array
     */
    static function getValidDayNumbers($pk, $project_id, $start_field, $start_field_event, $valid_day_number_array) {
        $start_date = StaticUtils::getFieldValue($pk, $project_id, $start_field, $start_field_event);
        $window_dates = array();

        foreach ($valid_day_number_array as $day) {
            $date = self::getDateFromDayNumber($start_date,$day);
            $window_dates[$date] = array(
                "START_DATE" => $start_date,
                "RECORD_NAME" => $pk . "-" . "D" . $day,
                "DAY_NUMBER" => $day
            );
        }
        return $window_dates;
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