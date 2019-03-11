<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-02-19
 * Time: 10:08
 */

namespace Stanford\RepeatingSurveyPortal;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

use REDCap;

class ParticipantMultipleResponse extends Participant
{

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
        $this->survey_status = $this->getAllSurveyStatus(
            $this->participantID,
            $portalConfig,
            min($this->portalConfig->validDayArray),
            max($this->portalConfig->validDayArray));

        //$module->emDebug($this->survey_status); exit;

    }


    /**
     *
     * Overwrite parent class to handle multiple responses in a day
     *
     *
     * @param $participant_id
     * @param $min
     * @param $max
     * @return array|void
     */
    public function getAllSurveyStatus($participant_id, $min, $max) {
        global $module;
        $module->emDebug("Not implemented yet. just use parent method for now");
        return parent::getAllSurveyStatus($participant_id,$min,$max);
    }

    public function isMaxResponsePerDayValid($day_number, $survey_date) {
        global $module;
        $module->emDebug("Not implemented yet. just use parent method for now");
        $status = parent::isMaxResponsePerDayValid($day_number, $survey_date);
        $module->emDebug("STatus from parent",$status);
        return $status;
    }

}