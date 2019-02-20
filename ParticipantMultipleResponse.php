<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-02-19
 * Time: 10:08
 */

namespace Stanford\RepeatingSurveyPortal;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */


class ParticipantMultipleResponse extends Participant
{


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
        parent::getAllSurveyStatus($participant_id,$min,$max);
    }

    public function checkMaxResponsePerDay() {

        $module->emDebug("Not implemented yet. just use parent method for now");
        return true;
    }

}