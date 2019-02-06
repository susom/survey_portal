<?php

namespace Stanford\RepeatingSurveyPortal;

use \ExternalModules;
use \REDCap;

/**
 * Class RepeatingSurveyPortal2
 * @package Stanford\RepeatingSurveyPortal2
 *
 *
 * WEB
 *
 * Portal2 Landing Page
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


    // CRON METHOD
    /**
     * 1) Determine projects that are using this EM
     * 2) Instantiate instance of EM for each project
     * 3)
     */

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



    // SAVE_RECORD HOOK
    // make portal objects and verify that current record has hash and personal url saved

    /**
     * @param $project_id
     * @param null $record
     * @param $instrument
     */
    public function redcap_save_record($project_id, $record = NULL, $instrument)  {
        //If instrument is the right one, create the portal url and save it to the designated field
        $this->emDebug($record . "In Hook Save Record");
        $this->emDebug($instrument.  "Instrument:", $this->getProjectSetting('main-config-form-name'));

        //iterate through all of the sub_settings
        $personal_hash_field = $this->getProjectSetting('personal-hash-field');
        $personal_url_field = $this->getProjectSetting('personal-url-field');
        $config_event       = $this->getProjectSetting('main-config-event-name');
        $target_form        = $this->getProjectSetting('main-config-form-name');

        foreach ($target_form as $sub => $candidate_target) {

            if ($instrument == $candidate_target) {

                $this->emDebug("Saving record with this sub: ". $sub . " and this hash field " . $personal_hash_field[$sub]
                    . " is it empty?" .empty($personal_hash_field[$sub]));

                if (empty($personal_hash_field[$sub])) {
                    continue; //empty hash field
                }

                // First check if hashed portal already has been created
                $f_value = $this->getFieldValue($record, $config_event[$sub], $personal_hash_field[$sub]);

                $this->emDebug("Saving record with this sub: ". $sub . " and this hash field " . $personal_hash_field[$sub]
                    . " is it empty?" .empty($personal_hash_field[$sub]) .  " ahs this value: " . $f_value);

                if ($f_value === null) {

                    //  What should be encoded in the url for a landing page?
                    //  - pid
                    //  - record | hash?
                    //  - which config (if multiple)
                    //  - day number (optional) - from invitations

                    //generate a new URL
                    $new_hash     = $this->generateUniquePersonalHash($project_id, $personal_hash_field[$sub], $config_event[$sub]);
                    $portal_url   = $this->getUrl("web/landing.php", true,true);
                    $new_hash_url = $portal_url. "&h=" . $new_hash . "&c=" . $sub;

                    $this->emDebug("this is new hash: ". $new_hash_url);
                    // Save it to the record (both as hash and hash_url for piping)
                    $result[$personal_hash_field] = $new_hash;
                    $result[$personal_url_field]  = $new_hash_url;

                    $event_name = REDCap::getEventNames(true,false, $config_event[$sub]);

                    $data = array(
                        REDCap::getRecordIdField() => $record,
                        'redcap_event_name'        => $event_name,
                        $personal_url_field[$sub]  => $new_hash_url,
                        $personal_hash_field[$sub] => $new_hash
                    );
                    $response = REDCap::saveData('json', json_encode(array($data)));
                    //$this->emDebug($response,  "Save Response for count");

                    if (!empty($response['errors'])) {
                        $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
                        $this->emError($msg);
                    }
                    $this->emDebug($record . ": Set unique Hash Url to $new_hash_url with result " . json_encode($response));
                } else {
                    continue; //leave and setup the other sub_settings
                }
            }

        }
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
    public function getFieldValue($record, $event, $target_field) {

        //Right instrument, carry on
        // First check if hashed portal already has been created
        $params = array(
            'return_format' => 'json',
            'records' => $record,
            'fields' => array($target_field),
            'events' => $event
        );

        $q = REDCap::getData($params);
        $results = json_decode($q, true);
        $result = current($results);

        //$this->emDebug("Current Record", $result, $result[$target_field], empty($result[$target_field]), isset($result[$target_field]));

        //url field already has a value, so punt
        if (empty($result[$target_field])) {
            return null;
        } else {
            return $result[$target_field];
        }
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