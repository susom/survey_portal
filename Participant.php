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

    /** @var bool Is portal enabled? */
    public $enablePortal;
    /** @var string Descriptive text for landing page */
    public $landingPageDesc;
    /** @var string Name of event where email and sms fields are stored */
    public $configID;

    public $mainConfigEventName;
    public $mainConfigFormName;
    public $emailField;
    public $phoneField;
    public $twilioSid;
    public $twilioToken;
    public $twilioNumber;
    public $startDateField;
    public $personalUrlField;
    public $personalHashField;
    public $surveyEventName;
    public $surveyDateField;
    public $surveyLaunchTSField;
    public $surveyConfigIDField;
    public $surveyDayNumberField;
    public $surveyInstrument;
    public $validDayNumber;
    public $maxResponsePerDay;
    public $validDayLag;
    public $earliestTimeAllowed;
    public $landingPageHeader;
    public $showCalendar;
    public $showMissingDayButtons;
    public $autoStartSurvey;
    public $surveyCompleteRedirect;
    public $invitationDays;
    public $invitationTime;
    public $invitationReminderTime;
    public $invitationEmailText;
    public $invitationSmsText;
    public $invitationReminder;

    private $map = array(
        'config-id'               => 'configID',
        'landing-page-desc'      => 'landingPageDesc',
        'landing-page-header'    => 'landingPageHeader',
        'main-config-event-name' => 'mainConfigEventName',
        'main-config-form-name'  => 'mainConfigFormName',
        'email-field'            => 'emailField',
        'phone-field'            => 'phoneField',
        'start-date-field'       => 'startDateField',
        'personal-hash-field'    => 'personalHashField',
        'personal-url-field'     => 'personalUrlField',
        'survey-event-name'       => 'surveyEventName',
        'survey-date-field'       => 'surveyDateField',
        'survey-launch-ts-field' => 'surveyLaunchTSField',
        'survey-config-field'     => 'surveyConfigIDField',
        'survey-day-number-field' => 'surveyDayNumberField',
        'survey-instrument'       => 'surveyInstrument',
        'valid-day-number'        => 'validDayNumber',
        'max-response-per-day'    => 'maxResponsePerDay',
        'valid-day-lag'           => 'validDayLag',
        'earliest-time-allowed'   => 'earliestTimeAllowed',
        'show-calendar'            => 'showCalendar',
        'show-missing-day-buttons' => 'showMissingDayButtons',
        'auto-start-survey'        => 'autoStartSurvey',
        'survey-complete-redirect' => 'surveyCompleteRedirect',
        'invitation-days'          => 'invitationDays',
        'invitation-time'          => 'invitationTime',
        'invitation-reminder-time' => 'invitationReminderTime',
        'invitation-email-text'    => 'invitationEmailText',
        'invitation-sms-text'      => 'invitationSmsText',
        'invitation-reminder'      => 'invitationReminder'
    );

    //This participant's survey data
    public $participant_hash;
    public $participant_id;   //PK of this participant
    public $start_date;       //starting date of
    public $survey_status;    //survey from start_date to endate with date / day_number/ valid/ completed
    public $event_name;
    public $valid_day_array;
    public $config_id;      // subsetting config ID
    public $max_instance;   //last instance number


    public function __construct($sub, $hash,$valid_day_array) {
        global $module;
        $config = $module->getProjectSettings();

        //setup parameters from the config
        foreach ($this->map as $k => $v) {

            $this->{$v} =  $config[$k]['value'][$sub];
        }

        $this->event_name = REDCap::getEventNames(true, false, $this->surveyEventName);


        //setup the participant surveys
        //given the hash, find the participant and set id and start date in object
        $this->participant_id =  $this->locateParticipantFromHash($hash);

        //$module->emDebug($this->participant_id, $hash);

        if ($this->participant_id == null) {
            throw new Exception("Participant not found from this hash: ".$hash);

        }

        //set up the participant survey map

        $this->valid_day_array = $valid_day_array;

        //get all Surveys for this particpant and determine status
        $this->survey_status = $this->getAllSurveyStatus($this->participant_id,min($valid_day_array), max($valid_day_array));

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
    public function getAllSurveyStatus($participant_id, $min, $max) {
        global $module;
        $survey_status = array();

        $all_surveys = $this->getAllSurveys($this->participant_id);
        $this->max_instance = max(array_keys($all_surveys));
        //$module->emDebug($all_surveys, $max_instance); exit;
        //$module->emDebug($this->valid_day_array, $this->start_date);

        $start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $date = $start_date;

        for ($i = $min; $i <= $max; $i++) {


            $found_survey_key = array_search($i, array_column($all_surveys, $this->surveyDayNumberField));
            //$module->emDebug($i, $found_survey_key, "FOUND");

            $date_str = $date->format('Y-m-d');
            $survey_status[$date_str]['day_number']  = $i;
            $survey_status[$date_str]['valid']       = in_array($i, $this->valid_day_array);
            if ($found_survey_key) {
                $survey_status[$date_str]['completed'] = $all_surveys[$found_survey_key][$this->surveyInstrument . '_complete'];
                $survey_status[$date_str]['survey_date'] = $all_surveys[$found_survey_key][$this->surveyDateField];
                $survey_status[$date_str]['date_taken']  = $all_surveys[$found_survey_key][$this->surveyLaunchTSField];
            }
            $date = $start_date->modify('+ 1 days');
        }

        return $survey_status;


    }

    public function locateParticipantFromHash($hash) {
        global $module;

        $event_name = REDCap::getEventNames(true, false, $this->mainConfigEventName);
        $filter = "[" . $event_name . "][" . $this->personalHashField . "] = '$hash'";

        // Use alternative passing of parameters as an associate array
        $params = array(
            'return_format' => 'json',
            'events'        => $event_name,
            'fields'        => array( REDCap::getRecordIdField(), $this->personalHashField, $this->startDateField),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        // return record_id or false
        $main = current($records);

        $this->participant_id = $main[REDCap::getRecordIdField()];
        $this->start_date     = $main[$this->startDateField];
        return ($this->participant_id);
    }

    /**
     *
     *
     * @param $date
     */
    public function getDayNumberFromDate($date) {
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
        //$filter = "[" . $this->event_name . "][" . $this->surveyFKField . "] = '$id'";

        $get_array = array(
            REDCap::getRecordIdField(),
            $this->configID,
            $this->surveyDayNumberField,
            $this->surveyDateField,
            $this->surveyInstrument . '_complete');

        $params = array(
            'return_format' => 'json',
            'records'       => $id,
            'events'        => $this->surveyEventName,
            'fields'        => $get_array
            //how about surveyTimestampField, surveyDateField
        );

        $q = REDCap::getData($params);

        $results = json_decode($q,true);

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

    public function newSurveyValidNow($day_number, $survey_date) {
        global $module;
        $valid = false;

        //check if survey already exists for this $survey_date

        $this->validDayLag;
        $this->maxResponsePerDay;
        $this->earliestTimeAllowed;

        //check whether it's within bounds of validDayLag
        //check if time is okay for earliest Time allowed
        if (($this->isDayLagValid($survey_date)) && ($this->isStartTimeValid($survey_date)) && ($this->checkMaxResponsePerDay($day_number, $survey_date))) {
            $valid = true;
        }


        //check if within maxResponsePerDay

        $module->emDebug("REturnign. ".$valid);
        return $valid;
    }

    public function isDayLagValid($survey_date) {
        global $module;
        if (!isset($this->validDayLag)) {
            $module->emDebug("not set");
            return true;
        } else {
            $today = new DateTime();

            $date_diff = $today->diff($survey_date)->days;
            if ($date_diff <= $this->validDayLag ) {
                $module->emDebug("valid day lag".  $date_diff, $today, $survey_date);
                return true;
            }
        }
        $module->emDebug("FAILED in valid day lag. Date DIff : ". $date_diff, $today, $survey_date);
        return false;
    }

    public function isStartTimeValid($survey_date) {
        global $module;
        if (!isset($this->earliestTimeAllowed)) {
            $module->emDebug("not set", $survey_date);
            return true;
        } else {
            $allowed_earliest = $survey_date->setTime($this->earliestTimeAllowed , 0);
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
    public function checkMaxResponsePerDay($day_number, $survey_date) {
        global $module;
        $survey_date_str = $survey_date->format('Y-m-d');
        $survey_complete = $this->survey_status[$survey_date_str]['complete'];

        $module->emDebug($this->survey_status[$survey_date_str]);

        if (($survey_complete) == 2) {
            return false;
        }
        return true;

    }


    public function newSurveyEntry($day_number, $survey_date) {
        global $module;

        //check max-response-per-day / base case is 1
        if ($this->maxResponsePerDay == 1) {

            //see how many responses already exist for this day_number

            //TODO: refresh survey_status?? is this overkill?  should refresh happen at end of survey hook?
            $this->survey_status = $this->getAllSurveyStatus($this->participant_id, min($this->valid_day_array), max($this->valid_day_array));

            //get the status for the survey_date
            $this->survey_status[$survey_date->format('Y-m-d')]['completed'];


        }



        $params = array(
            REDCap::getRecordIdField() => $this->participant_id,
            "redcap_event_name"        => $this->event_name,
            "redcap_repeat_instrument" => $this->surveyInstrument,
            "redcap_repeat_instance"   => $this->max_instance + 1,
            $this->surveyConfigIDField => $this->configID,
            $this->surveyDayNumberField => $day_number,
            $this->surveyDateField      => $survey_date->format('Y-m-d'),
            $this->surveyLaunchTSField  => date("Y-m-d H:i:s")
        );

        $result = REDCap::saveData('json', json_encode(array($params)));

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