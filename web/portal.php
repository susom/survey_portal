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



 * // METHOD
 * verify hash anbd personal url for record (called by save_record hook)


 * If Autostart:
 *  - if day number
 *
 *
 * // IF day number is present, then start that day
 *
 *

 */

