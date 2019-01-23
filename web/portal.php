<?php
/**
 * Created by PhpStorm.
 * User: andy123
 * Date: 2019-01-23
 * Time: 10:00

How do we handle the content of hte landing page?
 *
 * What is encoded in the url for a landing page?
 *  - pid
 *  - record
 *  - which config (if multiple)
 *  - day number (optional) - from invitations

 * https://redcap.stanford.edu/api/?type=module&id=11&page=web%20Portal.php&pid=12067&record=1234&config=?      &day=x
 *
 *
 * Personal URL:
 *  https://redcap.stanford.edu/api/?type=module&id=11&page=web%20Portal.php&pid=12067 + h = XXXX
 * Issues - need to obfuscate record...
 *
 * 1) each url generated is unique, we store it in the em_logs with parameters....



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

 * 1. retrieve record for this hash
 *
 *
 * If Autostart:
 *
  *
 *  1) if day number is not set, then calculate day number from start date nad current date
 *
 *      IF day number is present, then use that day number
 *
 * 2) Validate current time window for daynumber
 *
 * 3) Create survey for date and redirect
 *
 * If Not Autostart, then Calendar (//todo: is there another option?
 *
 * 1) Configure all teh Calendar options
 *    (a) show-missing-day-buttons
 *    (b)
 *
 * 2) if date is selected, validate time window, create survey, redirect
 *
 *
 *
 *

 */

