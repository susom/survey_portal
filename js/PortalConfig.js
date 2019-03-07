var PortalConfig = PortalConfig || {};


/**
 *
 */
PortalConfig.config = function() {
    var configureModal = $('#external-modules-configure-modal');
    var moduleDirectoryPrefix = configureModal.data('module');
    var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

    PortalConfig.url = app_path_webroot + "ExternalModules/?prefix=" + moduleDirectoryPrefix + "&page=web%2FConfigAjax&pid="+pid;

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
    const action = $(e).data('action');
    const event  = $(e).data('event');
    const form   = $(e).data('form');

    console.log(e, action, event, form);

    switch (action) {
        case 'create_pi_form':
            PortalConfig.insertForm('pi');
            break;
        case 'create_md_form':
            PortalConfig.insertForm('md');
            break;
        case 'designate_event':
            PortalConfig.designateForm(form, event);
            break;
        default:
            alert ("Invalid action recevied from status button");
    }

}


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
            console.log("CONFIG STATUS",configStatus);
            var successStatus =  $('<div></div>')
                    .addClass('alert alert-success')
                    .html("Your configuration appears valid");

            const status = data.isValid;

            const alerts = data.alerts;

            if (status) {
                console.log('ADDING', successStatus);
                //$('<div></div>')
                 //   .empty()
                 //   .addClass('alert alert-success')
                 //   .html("Your configuration appears valid")
                successStatus
                    .appendTo(configStatus);
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

PortalConfig.defaultSettings = {
    'participant-disabled':'participant_disabled',
    'main-config-form-name':'participant_info',
    'start-date-field' : 'start_date',
    'personal-hash-field' : 'survey_portal_hash',
    'personal-url-field' : 'survey_portal_unique_url',
    'email-field' : 'survey_portal_email',
    'disable-participant-email-field' : 'disable_participant_email',
    'phone-field' : 'survey_portal_phone',
    'disable-participant-sms-field' : 'disable_participant_sms',
    'survey-config-field' : 'rsp_survey_config',
    'survey-day-number-field' : 'rsp_survey_day_number',
    'survey-date-field' : 'rsp_survey_date',
    'survey-launch-ts-field' : 'rsp_survey_launch_ts'
};

PortalConfig.setDefaults  = function() {
    console.log("========================SettingDefaults");

    for (var key in PortalConfig.defaultSettings) {
        var dropdowns = $('select[name^='+key+']');

        dropdowns.each(function() {
            if ($(this ).val() ==  "") {
                $(this).val(PortalConfig.defaultSettings[key]);
                console.log($(this ).val() );
            }

        });

    }

}