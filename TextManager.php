<?php

namespace Stanford\RepeatingSurveyPortal;

/** @var \Stanford\RepeatingSurveyPortal\RepeatingSurveyPortal $module */

use \Services_Twilio as Services_Twilio;
use \Exception as Exception;

/**
 * Class textManager
 *
 * A poor-man's helper class for doing custom SMS messages in plugins and external modules
 *
 * Usage is pretty simple - just create the object passing in the necessary information then use sendSms to deliver a message
 *
 */
class TextManager
{
    public $twilio_account_sid;  // This is your twilio SID
    public $twilio_auth_token;   // This is your twilio auth token
    public $twilio_from_number;  // This is your twilio account number
    public $deleteSmsFromLog;    // Set to true to delete the SMS log (warning - this can cause things to slow down as it waits up to 30 sec for the message to be delivered)
    public $client;              // This is the twilio client!

    // Initialize Twilio classes and settings (using REDCap ones since they also use the proxy for outgoing communication)
    public static function init()
    {
        global $rc_autoload_function;
        // Call Twilio classes
        require_once APP_PATH_DOCROOT . "/Libraries/Twilio/Services/Twilio.php";
        // Reset the class autoload function because Twilio's classes changed it
        spl_autoload_register($rc_autoload_function);
    }

    /**
     * TextManager constructor.
     * @param $twilio_account_sid
     * @param $twilio_auth_token
     * @param $twilio_from_number
     * @param bool $deleteSmsFromLog Warning that this can take up to 30 seconds if the delivery is delayed
     */
    public function __construct($twilio_account_sid, $twilio_auth_token, $twilio_from_number, $deleteSmsFromLog = false)
    {

        // Initialize the twilio library if needed
        if (!class_exists("Services_Twilio")) self::init();

        $this->twilio_account_sid = $twilio_account_sid;
        $this->twilio_auth_token = $twilio_auth_token;
        $this->twilio_from_number = $twilio_from_number;
        $this->deleteSmsFromLog = $deleteSmsFromLog;

        $this->client = new Services_Twilio($this->twilio_account_sid, $this->twilio_auth_token);
    }

    /**
     * @param $destination_number
     * @param $text
     * @return bool|string        If not true, then the contents will be an error message from Twilio such as poor number format, etc..
     */
    public function sendSms($destination_number, $text)
    {
        // print "<br><br>TEXTMANAGER: " . $destination_number . " : " . $text;
        try {
            $sms = $this->client->account->messages->sendMessage(
                self::formatNumber($this->twilio_from_number),
                self::formatNumber($destination_number),
                $text
            );

            // Wait till the SMS sends completely and then remove it from the Twilio logs
            if ($this->deleteSmsFromLog) {
                sleep(2);
                $result = $this->deleteLogForSMS($sms->sid);
                print "<pre>RESULT:" . (int)$result . "</pre>";
            }

            // Successful, so return true
            return true;
        } catch (Exception $e) {
            // On failure, return error message
            return $e->getMessage();
        }
    }


    /**
     * Delete the Twilio back-end and front-end log of a given SMS (will try every second for up to 30 seconds)
     * @param $sid
     * @return bool
     */
    public function deleteLogForSMS($sid)
    {
        // Delete the log of this SMS (try every second for up to 30 seconds)
        for ($i = 0; $i < 30; $i++) {
            // Pause for 1 second to allow SMS to get delivered to carrier
            if ($i > 0) sleep(1);
            // Has it been delivered yet? If not, wait another second.
            $log = $this->client->account->sms_messages->get($sid);

            print "<pre>Log $i: " . print_r($log, true) . "</pre>";
            if ($log->status != 'delivered') continue;
            // Yes, it was delivered, so delete the log of it being sent.
            $this->client->account->messages->delete($sid);
            return true;
        }
        // Failed
        return false;
    }


    /**
     * Convert phone nubmer to E.164 format before handing off to Twilio
     * @param $phoneNumber
     * @return mixed|string
     */
    public static function formatNumber($phoneNumber)
    {
        // If number contains an extension (denoted by a comma between the number and extension), then separate here and add later
        $phoneExtension = "";
        if (strpos($phoneNumber, ",") !== false) {
            list ($phoneNumber, $phoneExtension) = explode(",", $phoneNumber, 2);
        }
        // Remove all non-numerals
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        // Prepend number with + for international use cases
        $phoneNumber = (isPhoneUS($phoneNumber) ? "+1" : "+") . $phoneNumber;
        // If has an extension, re-add it
        if ($phoneExtension != "") $phoneNumber .= ",$phoneExtension";
        // Return formatted number
        return $phoneNumber;
    }

    /**
     * The filter in the REDCap::getData expects the phone number to be in
     * this format (###) ###-####
     *
     * @param $number
     * @return
     */
    public static function formatToREDCapNumber($number)
    {
        $formatted = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $number);
        return trim($formatted);
    }

}
