var PortalConfig = PortalConfig || {};

PortalConfig.config = function(foo) {

    console.log("starting with this: ", this, foo);
    var configureModal = $('#external-modules-configure-modal');
    var moduleDirectoryPrefix = configureModal.data('module');
    var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

    //ExternalModules/?prefix=survey_portal&page=web%2Fforecast&pid=201

    var location =window.location.hostname; //current url

    console.log("starting with thisxx: ", this, foo);
    console.log(" THIS IS THE MODAL" , configureModal);

    var alpha = 'participant-config-id-field';
    var beta = 'participant_disabled';
    //var foobar =  $('[name="participant-config-id-field"]');
    //foobar.val('participant_disabled');

    //var foobar =  $('[name="' + alpha + '"]');
    //foobar.val(beta);

    //$('[name="' + alpha + '"]').val(beta);


    //console.log("This is the selector", foobar);

    $('[name="participant-disabled____0"]').prop('participant_disabled');
    $( "[name='participant-disabled____0']" ).val('participant_disabled');

    //$('.external-modules-input-element').val('record_id');
    //ExternalModules/manager/project.php?pid=201

    var url = app_path_webroot + "ExternalModules/?prefix=" + moduleDirectoryPrefix + "&page=web%2FConfigAjax&pid="+pid;
    console.log("URL:",url);
    console.log("DOC:",document);
    console.log(configureModal, moduleDirectoryPrefix, version);


    let data = {
        "action" : "test",
        "config_field"  : $("[name='participant-config-id-field']").val(),
        "config_id"   : $("[name='config-id____0']").val()
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
    'participant-disabled____0':'participant_disabled',
    'main-config-form-name____0':'participant_info'
};
PortalConfig.setDefaults  = function() {
    console.log("SettingDefaults");

    var alpha = 'participant-config-id-field';
    var beta = 'participant_disabled';
    $('[name="' + alpha + '"]').val(beta);

    $('input[name="enable-project-debug-logging"]').prop("checked", true);

    $('[name="config-description____0"]').val("foo bar bamr");

    var alpha1 = 'participant-disabled____1';
    var beta1 = 'participant_disabled';
    $('[name="' + alpha1 + '"]').val(beta1);

    $('[name="participant-disabled____1"]').val('participant_disabled');

    //var foobar =  $('[name="participant-config-id-field"]');
    //foobar.val('participant_disabled');

    //var foobar =  $('[name="' + alpha + '"]');
    //foobar.val(beta);



    for (var key in PortalConfig.defaultSettings) {
        console.log("key " + key + " has value " + PortalConfig.defaultSettings[key]);
        //$('[name="' + key + '"]').val(PortalConfig.defaultSettings[key]);

        //$('select[name="' + key + '"] option:selected').val('participant_disabled');
        //   $("[name='"+key+"']").val(PortalConfig.defaultSettings[key]);
        // $("[name='"+key+"']").val('participant_disabled');

    }
    $( "[name='participant-disabled____0']" ).val('participant_disabled');
}