<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-03-01
 * Time: 12:27
 */

namespace Stanford\RepeatingSurveyPortal;

require_once $module->getModulePath().'src/InsertInstrumentHelper.php';

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

//check for presence p info

//if NO
//present modal


//IF YES
// import form

//populate defaults

if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case "test":

            $module->emDebug($_POST);
            // SAVE A CONFIGURATION
            $participant_config_id = $_POST['config_field'];


            // $module->debug($raw_config,"DEBUG","Raw Config");


            //TODO: STUCK!  can't pass subsetting fields

            //if this were working, check that the fields don't already exist in file
            $zip_loader = new InsertInstrumentHelper($module);
            $zip_loader->insertParticipantInfoForm();
            $zip_loader->insertSurveyMetadataForm(); //todo: designate to event with config id
            //how to deal with designating for event

            $sub_settings = $module->getSubSettings('survey-portals');
            //$module->emDebug($sub_settings);

            foreach ($sub_settings as $sub) {
                //TODO: designate for each event

            }

            $test_error = "foo bar";

            $status = true;
            if ($status) {
                // SAVE
                $result = array(
                    'result' => 'success',
                    'message' => $test_error
                );
            } else {
                $test_error = 'not foobar';
            }
            $result = array(
                'result' => 'success',
                'message' => $test_error
            );
    }
    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}
