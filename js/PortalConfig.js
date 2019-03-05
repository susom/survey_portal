var PortalConfig = PortalConfig || {};

PortalConfig.config = function(foo) {

    console.log("starting with this: ", this, foo);
    var configureModal = $('#external-modules-configure-modal');
    var moduleDirectoryPrefix = configureModal.data('module');
    var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

    //ExternalModules/?prefix=survey_portal&page=web%2Fforecast&pid=201

    var location =window.location.hostname; //current url

    var url = app_path_webroot + "ExternalModules/?prefix=" + moduleDirectoryPrefix + "&page=web%2FConfigAjax&pid="+pid;
    console.log("URL:",url);
    console.log(configureModal, moduleDirectoryPrefix, version);

    var configIDs = $('inputcd[name^=config-id]');
    var ids = [];

    configIDs.each(function() {
        ids.push($(this ).val());
    });

    console.log(ids);

    let data = {
        "action" : "test",
        "config_field"  : $("[name='participant-config-id-field']").val(),
        "config_id"   : ids
    }

    //ajax call to the url
        // Post back saved to config.php page
    var jqxhr = $.ajax({
        method: "POST",
        url: url,
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

    PortalConfig.setDefaults();

    // Remove two fields that don't apply in config for this module
    /**
     $('tr[field="enabled"]').addClass('hidden');
     $('tr[field="discoverable-in-project"]').addClass('hidden');


     // Display configuration errors a little differently
     let errors_tr = $('tr[field="configuration-validation-errors"]');
     let errors = JSON.parse($('input', errors_tr).val());

     errors_tr.hide();

     $.each(errors, function(i, e) {
        errors_tr.after(
            $('<tr/>').append(
                $('<td colspan="3">').append(
                    $('<div/>').addClass('alert alert-danger text-center').html(e)
                )
            )
        );
        console.log(i,e);
    });



     */
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
    console.log("SettingDefaults");

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