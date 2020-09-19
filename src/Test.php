<?php
namespace Stanford\RepeatingSurveyPortal;
/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

use REDCap;
use Exception;


$sub = isset($_GET['s']) ? $_GET['s'] : "0";

$module->emLog("Starting Test for " . $module->getProjectId() . " with config $sub");
// echo "------- Starting Repeating Survey Portal:  Invitation Cron for $project_id with config sub-setting $sub-------";

$project_id =  $module->getProjectId();

//get the config id from the passed in hash
$configID = $module->getConfigIDFromSubID($sub);

if ($configID != null) {
    $portalConfig = new PortalConfig($configID);
} else {
    $err_msg ="Cron job attempted to send invites for a non-existent configId: ". $configID . " in this subsetting :  ". $sub;
    $module->emError($err_msg);
    throw new Exception($err_msg);
}

$email_from            = $portalConfig->invitationEmailFrom;
$email_subject         = $portalConfig->invitationEmailSubject;
$email_type            = "Invite";
$email_text            = $portalConfig->invitationEmailText;
$email_url_label       = $portalConfig->invitationUrlLabel;
$sms_text              = $portalConfig->invitationSmsText;
$modifier_by_logic     = $portalConfig->invitationDaysModLogic;

$modifier_by_logic_1 = "[current_instance_field][last-instance] = '2'";
$modifier_by_logic_2 = "[covid_symptoms_arm_1][covid_symptoms(990)][last-instance] <> '1'";
$modifier_by_logic_3 = "[covid_symptoms(990)][last-instance] <> '1'";  // having the event name prepended syntax error in the form

$modifier_by_logic_4 = "[foo]='bar'";

$test = array(

);


$rec_id = '1';
$logic_event = $portalConfig->invitationDaysModLogicEvent;
$repeat_instance =3;
$repeat_instrument = 'covid19_symptoms';

//$modifier_by_logic = "((rounddown(datediff([ts_send_update], 'today', 'd', 'ymd')))%7) = 0";
//$modifier_by_logic = "(isinteger([diff_days]/14))";

$modifier_by_logic = "(([send_survey] <> '0'))";

$logic_result = REDCap::evaluateLogic($modifier_by_logic, $project_id,$rec_id, $logic_event  );

echo "<br>1 LOGIC WAS . ".$modifier_by_logic;
echo "<br>1 result WAS . ".$logic_result;
echo "<br>1 record was . ".$rec_id;

$modifier_by_logic = "( ([send_survey] = 0) AND (isinteger((rounddown(datediff([ts_send_update], 'today', 'd', 'ymd')))/14)))";

$logic_result = REDCap::evaluateLogic($modifier_by_logic, $project_id,$rec_id, $logic_event  );

echo "<br>2 LOGIC WAS . ".$modifier_by_logic;
echo "<br>2 result WAS . ".$logic_result;
echo "<br>2 record was . ".$rec_id;

$modifier_by_logic = "([send_survey] <> '0') OR (([send_survey] = 0) AND (isinteger((rounddown(datediff([ts_send_update], 'today', 'd', 'ymd')))/14)))";

$logic_result = REDCap::evaluateLogic($modifier_by_logic, $project_id,$rec_id, $logic_event  );

echo "<br> LOGIC WAS . ".$modifier_by_logic;
echo "<br> result WAS . ".$logic_result;
echo "<br> record was . ".$rec_id;

exit;



class InstrumentHelper {

    private $module;

    public $errors = array();
    private $dd_array = array();
    private $status;                // 0 = dev, 1 = prod
    private $current_metadata;      // this will depend on dev or prod mode

    const ZIP_PATH = "docs/RSPParticipantInfo.zip";

    public function __construct(RepeatingSurveyPortal $module)
    {
        $this->module = $module;

        global $Proj;
        $this->status = $Proj->project['status'];
        $this->current_metadata = ($this->status == 0 ? $Proj->metadata : $Proj->metadata_temp);
    }

    public function insertParticipantInfoForm()
    {
        if (!$this->loadZipFile())  return false;
        if (!$this->verifyFields()) return false;
        if (!$this->verifyForms())  return false;
        if (!$this->saveMetadata()) return false;
        return true;
    }

    private function loadZipFile()
    {
        $zipFile = $this->module->getModulePath() . self::ZIP_PATH;

        $zip = new ZipArchive;
        $res = $zip->open($zipFile);
        if ($res !== TRUE) {
            return $this->addError("Unable to open");
        }

        $instrumentDD = $zip->getFromName('instrument.csv');
        if ($instrumentDD === false) {
            return $this->addError("Unable to get instrument.csv");
        }

        // Create a temp file for the zip contents
        $project_id = $this->module->getProjectId();
        $dd_filename = APP_PATH_TEMP . date('YmdHis') . '_instrumentdd_' . $project_id . '_' . substr(sha1(rand()), 0, 6) . '.csv';
        file_put_contents($dd_filename, $instrumentDD);

        // Parse DD
        $this->dd_array = excel_to_array($dd_filename);

        // Get rid of temp file
        unlink($dd_filename);

        if ($this->dd_array === false || $this->dd_array == "") {
            return $this->addError('Unable to parse file');
        }

        return true;
    }


    private function verifyForms() {
        // Find any variables that are duplicated in the DD
        $existingForms = array();
        foreach ($this->current_metadata as $fields => $metadata) {
            $form = $metadata['form_name'];
            if (!isset($existingForms[$form])) $existingForms[] = $form;
        }

        $newForms = array_unique($this->dd_array['B']);

        $dupForms = array();
        foreach ($newForms as $newForm) {
            if (in_array($newForm, $existingForms)) {
                $dupForms[] = $newForm;
            }
        }
        return empty($dupForms) ? true : $this->addError( "Form(s): " . implode(",",$dupForms) . " already exist!");
    }


    private function verifyFields() {
        $existingFields = array_keys($this->current_metadata);

        $newFields = $this->dd_array['A'];

        $dupFields = array();
        foreach ($newFields as $newField) {
            if (in_array($newField, $existingFields)) {
                $dupFields[] = $newField;
            }
        }

        $this->emDebug("String", FALSE);

        return empty($dupFields) ? true : $this->addError( "Fields(s): " . implode(",",$dupFields) . " already exist!");
    }


    private function saveMetadata() {

        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        // Save data dictionary in metadata table
        $sql_errors = MetaData::save_metadata($this->dd_array, true);

        if (count($sql_errors) > 0) {
            $this->emDebug("ERRORS", $sql_errors);
            // ERRORS OCCURRED, so undo any changes made
            db_query("ROLLBACK");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");
            // Display error messages
            if ($this->status == 0) {
                $this->addError("Unable to save: " . json_encode($sql_errors));
            } else {
                $this->addError("Unable to save - if this is a production project please enter draft mode first");
            }
            return false;
        } else {
            $this->emDebug("SUCCESS");
            // COMMIT CHANGES
            db_query("COMMIT");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");
        }
        return true;
    }


    // Make an emDebug method inside this child class
    private function emDebug() {
       call_user_func_array(array($this->module, "emDebug"), func_get_args());
    }

    // Build an array of errors
    private function addError($error) {
        $this->errors[] = $error;
        return false;
    }

    // Return the errors
    public function getErrors() {
        return $this->errors;
    }
}


/**
echo " TEst to insert an instrument ";
echo "<pre>";

$ih = new InstrumentHelper($module);

$result = $ih->insertParticipantInfoForm();

if (!$result) {
    echo "ERRORS\n" . print_r($ih->getErrors(),true);
} else {
    echo "SUCCESS\n";
}
 *

 */


    global $module;
    $sub = 0;

    echo "Running test for inviteReminderCandidates for project_id $project_id and sub $sub";

    //get the config id from the passed in hash
    $configID = $module->getConfigIDFromSubID($sub);
    $portalConfig;

    if ($configID != null) {

        $portalConfig = new PortalConfig($configID);
    } else {
        $module->emError("Cron job to send invitations attempted for a non-existent configId: ". $configID .
                         " in this subsetting :  ". $sub);
    }

    if ($portalConfig->configID == null) {
        $module->emError("config ID is not set!");
        return false;
    }

    $enrollment_arm = $portalConfig->mainConfigEventName;

    //1. Obtain all records where this 'config-id' matches the in the patient record
    //Also filter that either email or sms  is populated.
    $filter = "(" .
        "([$enrollment_arm][" . $portalConfig->participantConfigIDField . "] = '{$portalConfig->configID}') AND " .
        "(" .
        "(([$enrollment_arm][" . $portalConfig->disableParticipantEmailField . "(1)] <> 1) and  ([$enrollment_arm][" . $portalConfig->emailField . "] <> ''))" .
        " OR " .
        "(([$enrollment_arm][" . $portalConfig->disableParticipantSMSField . "(1)] <> 1) and  ([$enrollment_arm][" . $portalConfig->phoneField . "] <> ''))"
        . ")"
        . ")";

    $filter = "[enrollment_arm_1][rsp_prt_config_id] = 'daily'";

    $module->emDebug($filter);
    $params = array(
        'return_format' => 'json',
        'fields' => array(
            \REDCap::getRecordIdField(),
            $portalConfig->emailField,
            $portalConfig->phoneField,
            $portalConfig->personalUrlField,
            $portalConfig->startDateField,
            $portalConfig->emailField,
            $portalConfig->disableParticipantEmailField,
            $portalConfig->phoneField,
            $portalConfig->disableParticipantSMSField,
            $portalConfig->personalHashField
        ),
        'events' => $portalConfig->mainConfigEventName,
        'filterLogic' => $filter
    );

    //$module->emDebug($params, "PARAMS");
    $q = \REDCap::getData($params);
    $result = json_decode($q, true);

    //there is a bug since 9.1 where the filter returns an empty array for every found array.
    //iterate over the returned result and delete the ones where redcap_repeat_instance is blank
    $not_empty = array();
    foreach ($result as $k => $v) {
        if (!empty($v['redcap_repeat_instance'])) {
            $not_empty[] = $v;
        }
    }

    $module->emDebug($params, $result, $not_empty, "Count of invitations to be sent:  ".count($result). " not empty". count($not_empty));
    //exit;

