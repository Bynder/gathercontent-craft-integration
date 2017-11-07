document.onload = assignApiValidate();
document.onload = apiValidate(false);

function assignApiValidate() {
    console.log('Assigned API Validation');
    $("#settings-username").change(function(e){
        apiValidate(true);
    });

    $("#settings-apiKey").change(function(e){
        apiValidate(true);
    });
}

function apiValidate(force)
{
    console.log("Starting to validate credentials");

    var emailElement = $('#settings-username');
    var keyElement = $('#settings-apiKey');

    var email = emailElement.val();
    var key = keyElement.val();

    console.log("Starting to validate email: " + email);
    console.log("Starting to validate key: " + key);
    console.log("Starting to validate force: " + force);

    if (force) {
        callApiValidation(key, email);
        return true;
    }

    if (email !== undefined && key !== undefined && email !== '' && key !== '') {
        callApiValidation(key, email);
    }
}

function callApiValidation(key, email) {

    var emailElement = $('#settings-username');
    var keyElement = $('#settings-apiKey');

    var postData = {
        'email': email,
        'key': key
    };

    postData[Craft.csrfTokenName] = window.csrfTokenValue;

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': validateApiCredentialsUrl,
        'dataType': 'json',
        'timeout': 50000000,
        data: postData
    }).done(function (data) {

        if (data.success === true) {
            console.log("Valid Credentials");

            emailElement.parent().removeClass('errors');
            keyElement.parent().removeClass('errors');

            emailElement.parent().parent().children('.errors').empty();
            keyElement.parent().parent().children('.errors').empty();

            emailElement.css('border-color', 'green');
            keyElement.css('border-color', 'green');
        } else {
            console.log("Invalid Credentials");

            emailElement.parent().addClass('errors');
            keyElement.parent().addClass('errors');

            emailElement.css('border-color', 'red');
            keyElement.css('border-color', 'red');
        }

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}