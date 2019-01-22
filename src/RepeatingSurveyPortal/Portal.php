<?php

namespace Stanford\RepeatingSurveyPortal;

class Portal
{

    /** @var bool Is portal enabled? */
    public $enablePortal;
    /** @var string Descriptive text for landing page */
    public $landingPageDesc;
    /** @var string Name of event where email and sms fields are stored */
    public $mainConfigEventName;
    public $emailField;
    public $phoneField;
    public $twilioSid;
    public $twilioToken;
    public $twilioNumber;
    public $startDateField;
    public $personalUrlField;
    public $surveyEventName;
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




     /**
     * Load config from REDCap External Module settings
     *
     * @param Setting $setting
     * @throws Exception
     */
    public function __construct($config)
    {
        // $this->setting = $setting;
        //
        // $settings = $setting->getMultipleSystem(SystemKey::values());
        //
        // if (strlen($settings[SystemKey::INITIALIZED]) &&
        //     strlen($settings[SystemKey::HMAC_KEY]) &&
        //     strlen($settings[SystemKey::METADATA_PROJECT_ID])
        // ) {
        //     $this->demoStudyCreated = $settings[SystemKey::DEMO_STUDY_CREATED] == '1' ? true : false;
        //     $this->disableSslPeerVerification = $settings[SystemKey::DISABLE_SSL_PEER_VERIFICATION] == '1' ? true : false;
        //     $this->forceHttpForInternalRequests = $settings[SystemKey::FORCE_HTTP_FOR_INTERNAL_REQUESTS] == '1' ? true : false;
        //     $this->googleFirebaseServerKey = $settings[SystemKey::GOOGLE_FIREBASE_SERVER_KEY];
        //     $this->googleMapsKey = $settings[SystemKey::GOOGLE_MAPS_KEY];
        //     $this->hmacKey = $settings[SystemKey::HMAC_KEY];
        //     $this->initialized = $settings[SystemKey::INITIALIZED] == '1' ? true : false;
        //     $this->metadataProjectId = (int)$settings[SystemKey::METADATA_PROJECT_ID];
        //     $this->metadataProjectToken = $settings[SystemKey::METADATA_PROJECT_TOKEN];
        //     $this->mycapApiBaseUrl = $settings[SystemKey::MYCAP_API_BASE_URL];
        //     if (array_key_exists(SystemKey::MYCAP_CENTRAL_API_URL, $settings)
        //         && !is_null($settings[SystemKey::MYCAP_CENTRAL_API_URL])) {
        //         $this->mycapCentralApiUrl = $settings[SystemKey::MYCAP_CENTRAL_API_URL];
        //     }
        //     $this->studyCreatorUserId = (int)$settings[SystemKey::STUDY_CREATOR_USER_ID];
        //     $this->version = (int)$settings[SystemKey::VERSION];
        // } else {
        //     $this->demoStudyCreated = false;
        //     $this->disableSslPeerVerification = false;
        //     $this->forceHttpForInternalRequests = false;
        //     $this->googleFirebaseServerKey = '';
        //     $this->googleMapsKey = '';
        //     $this->hmacKey = bin2hex($this->generateHmacKey());
        //     $this->initialized = false;
        //     $this->metadataProjectId = 0;
        //     $this->metadataProjectToken = '';
        //     $this->mycapApiBaseUrl = '';
        //     $this->studyCreatorUserId = 0;
        //     // TODO: Remove DEFAULT_VERSION because it can be determined programatically. Steps:
        //     // 1) Get all files in migrations directory,
        //     // 2) sort by date descending
        //     // 3) instantiate class (or better, change version property to static)
        //     // 4) set $this->version = max version
        //     $this->version = self::DEFAULT_VERSION;
        //     $this->save();
        // }
    }


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