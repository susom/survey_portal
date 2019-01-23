<?php

namespace Stanford\RepeatingSurveyPortal;


/**
 * Class RepeatingSurveyPortal
 * @package Stanford\RepeatingSurveyPortal
 *
 *
 * WEB
 *
 * Portal Landing Page
 *
 * web/portal.php   NOAUTH
 * web/forecast.php (tries to show what will happen based on certain dates)
 * web/cron.php     NOAUTH (landing page to instantiate cron)
 *  - load the project config, for each config, it will execute check to see if each record needs notification...
 *  -
 *
 *
 *
 */


class RepeatingSurveyPortal
{


    // CRON METHOD
    /**
     * 1) Determine projects that are using this EM
     * 2) Instantiate instance of EM for each project
     * 3)
     */

    // SAVE CONFIG HOOK
    // if config-id is null, then generate a config id for that the configs...



    // SAVE_RECORD HOOK
    // make portal objects and verify that current record has hash and personal url saved

    /**
     *
     *

    "redcap_every_page_before_render",
    "redcap_module_system_enable",
    "redcap_module_link_check_display",
    "redcap_module_save_configuration"

     */
}