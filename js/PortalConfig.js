var PortalConfig = PortalConfig || {};


/**
 *
 */
PortalConfig.config = function() {
    var configureModal = $('#external-modules-configure-modal');
    var moduleDirectoryPrefix = configureModal.data('module');
    var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

    PortalConfig.url = app_path_webroot + "ExternalModules/?prefix=" + moduleDirectoryPrefix + "&page=src%2FConfigAjax&pid="+pid;

    console.log(PortalConfig.url);

    //clear out old one from before
    $('#config_status').remove();

    var alertWindow = $('<div></div>')
        .attr('id', 'config_status')
        .prependTo($('.modal-body', '#external-modules-configure-modal'));


    $('.external-modules-add-instance').on("click", function () {
        console.log("========SETTING TIMER");
        setTimeout(PortalConfig.setDefaults, 1000);
    });

    alertWindow
        .on('click', '.btn', function () {
            PortalConfig.doAction(this);
        });

    console.log("starting with this: ", this);

    //set the defaults for the current config
    PortalConfig.setDefaults();

    PortalConfig.getStatus();

    return;

}


/**
 * Passed in parameter from button in status banner
 * @param e
 */
PortalConfig.doAction = function (e) {

    const data = $(e).data();

    // Action MUST be defined or we won't do anything
    if (!data.action) {
        alert ("Invalid Button - missing action");
        return;
    }

    // Do the ajax call
    $.ajax({
        method: "POST",
        url: PortalConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            // Data should be in format of:
            // data.result   true/false
            // data.message  (optional)  message to display.
            // data.callback (function to call)
            // data.delay    (delay before callbackup in ms)
            const cls = data.result ? 'alert-success' : 'alert-danger';

            // Render message if we have one
            if (data.message) {
                var alert = $('<div></div>')
                    .addClass('alert')
                    .addClass(cls)
                    .css({"position": "fixed", "top": "5px", "left": "2%", "width": "96%", "display":"none"})
                    .html(data.message)
                    .prepend("<a href='#' class='close' data-dismiss='alert'>&times;</a>")
                    .appendTo('#external-modules-configure-modal')
                    .show(500);

                setTimeout(function(){
                    console.log('Hiding in 500', alert);
                    alert.hide(500);
                }, 5000);
            }

            if (data.callback) {
                const delay = data.delay ? data.delay : 0;

                //since configuration is set, set the defaults for the first configuration
                setTimeout(window[data.callback](), delay);
            }
        })
        .fail(function () {
            alert("error");
        })
        .always(function() {
            PortalConfig.getStatus();
        });


    //const event  = $(e).data('event');
    //const form   = $(e).data('form');

    //console.log(e, action, event, form);

    //switch (action) {
    //    case 'create_pi_form':
    //        PortalConfig.insertForm('pi');
    //        break;
    //    case 'create_md_form':
    //        PortalConfig.insertForm('md');
    //        break;
    //    case 'designate_event':
    //        PortalConfig.designateForm(form, event);
    //        break;
    //    default:
    //        alert ("Invalid action received from status button");
    //}

};


PortalConfig.designateForm = function(form, event) {
    console.log("DESIGNATING FORM TO EVENT");

    const data = {
        'action' : 'designateForm',
        'form'   : form,
        'event'  : event
    }

    var jqxhr = $.ajax({
        method: "POST",
        url: PortalConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            //if (data.result === 'success') {
                // all is good
                var configStatus = $('#config_status');
                configStatus.empty();
                console.log(configStatus);

                const status = data.isValid;

                const alerts = data.alerts;

            if (status) {
                $('<div></div>')
                    .addClass('alert alert-success')
                    .html("Your configuration appears valid")
                    .appendTo(configStatus);

                //since configuration is set, set the defaults for the first configuration
                setTimeout(PortalConfig.setDefaults, 1000);
            }
            $.each(alerts, function (i, alert) {
                $('<div></div>')
                    .addClass('alert')
                    .html(alert)
                    .appendTo(configStatus);
            })

        })
        .fail(function () {
            alert("error");
        })
        .always(function() {

        });
}

PortalConfig.insertForm = function(form) {
    console.log("INSERT FORM");

    const data = {
        'action' : 'insertForm',
        'form'   : form
    }

    var jqxhr = $.ajax({
        method: "POST",
        url: PortalConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            //if (data.result === 'success') {
                // all is good
                var configStatus = $('#config_status');
                configStatus.empty();
                console.log(configStatus);

                const status = data.isValid;

                const alerts = data.alerts;

            if (status) {
                $('<div></div>')
                    .addClass('alert alert-success')
                    .html("Your configuration appears valid")
                    .appendTo(configStatus);

                //since configuration is set, set the defaults for the first configuration
                setTimeout(PortalConfig.setDefaults, 1000);
            }
            $.each(alerts, function (i, alert) {
                $('<div></div>')
                    .addClass('alert')
                    .html(alert)
                    .appendTo(configStatus);
            })

        })
        .fail(function () {
            alert("error");
        })
        .always(function() {

        });
}


PortalConfig.getStatus = function () {
    console.log("GET STATUS");
    const data = {
        'action' : 'getStatus'
    }

    var jqxhr = $.ajax({
        method: "POST",
        url: PortalConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            //if (data.result === 'success') {
            // all is good
            var configStatus = $('#config_status');
            configStatus.empty();
            configStatus.html('');

            const cls = data.result ? 'alert-success': 'alert-danger';

            $.each(data.message, function (i, alert) {
                $('<div></div>')
                    .addClass('alert')
                    .addClass(cls)
                    .html(alert)
                    .appendTo(configStatus);
            })

        })
        .fail(function () {
            alert("error");
        })
        .always(function() {

        });


};

PortalConfig.checkForms = function () {
    var data = {
        "action" : "checkForms"
    }


    var jqxhr = $.ajax({
        method: "POST",
        url: PortalConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            if (data.result === 'success') {
                // all is good
            } else {
                // an error occurred
                simpleDialog("Unable to Save<br><br>" + data.message, "ERROR - SAVE FAILURE" );
            }

        })
        .fail(function () {
            alert("error");
        })
        .always(function() {

        });


}

PortalConfig.defaultSelectSettings = {
    'participant-disabled'  :'rsp_prt_part_disabled',
    'main-config-form-name' :'rsp_participant_info',
    'start-date-field'      : 'rsp_prt_start_date',
    'personal-hash-field'   : 'rsp_prt_portal_hash',
    'personal-url-field'    : 'rsp_prt_portal_url',
    'email-field'           : 'rsp_prt_portal_email',
    'disable-participant-email-field' : 'rsp_prt_disable_email',
    'phone-field'           : 'rsp_prt_portal_phone',
    'disable-participant-sms-field' : 'rsp_prt_disable_sms',
    'survey-config-field'   : 'rsp_survey_config',
    'survey-day-number-field' : 'rsp_survey_day_number',
    'survey-date-field'     : 'rsp_survey_date',
    'survey-launch-ts-field': 'rsp_survey_launch_ts'
};

PortalConfig.defaultTextSettings = {
    'portal-invite-subject' :  'Survey Portal URL',
    'portal-invite-from'    : 'no-reply@stanford.edu'
};

PortalConfig.defaultTextAreaSettings = {
    'portal-invite-email'   : 'Dear participant,\n<br>\nBelow is the private link to your daily diary. Please bookmark it for your convenience.\n<br>\n[portal-url]'
};

PortalConfig.setDefaults  = function() {
    console.log("========================Setting Dropdown Defaults");

    for (var key in PortalConfig.defaultSelectSettings) {
        var dropdowns = $('select[name^='+key+']');

        dropdowns.each(function() {
            if ($(this ).val() ==  "") {
                $(this).val(PortalConfig.defaultSelectSettings[key]);
                console.log($(this ).val() );
            }

        });

    }

    console.log("========================Setting Text Defaults");
    for (var key in PortalConfig.defaultTextSettings) {
        var textfields = $('input[name^='+key+']');

        textfields.each(function() {
            if ($(this ).val() ==  "") {
                $(this).val(PortalConfig.defaultTextSettings[key]);
                console.log($(this ).val() );
            }

        });

    }

        console.log("========================Setting TextArea Defaults");
    for (var key in PortalConfig.defaultTextAreaSettings) {
        var textfields = $('textarea[name^='+key+']');

        textfields.each(function() {
            if ($(this ).val() ==  "") {
                $(this).val(PortalConfig.defaultTextAreaSettings[key]);
                console.log($(this ).val() );
            }

        });

    }

}