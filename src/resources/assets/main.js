document.onload = populateProjects();
document.onload = populateSections();
document.onload = assignOnChangeEvenets();
document.onload = setUpSecondRow();


function assignOnChangeEvenets()
{
    $("#gatherContentProjectId").on("change", function(event) {
        populateTemplates();
        setElementEnabledById('gatherContentTemplateId');
        clearElements();
    } );

    $("#craftSectionId").on("change", function(event) {
        populateEntryTypes();
        setElementEnabledById('craftEntryTypeId');
        clearElements();
    } );

    $("#gatherContentTemplateId").on("change", function(event) {
        populateElementsAndFields();
    } );

    $("#craftEntryTypeId").on("change", function(event) {
        populateElementsAndFields();
    } );
}

function setUpSecondRow() {
    var templateOption = $('<option disabled selected value>Select GatherContent Project</option>');
    $('#gatherContentTemplateId').append(templateOption).prop('disabled', true).addClass('disabled');
    var entryTypeOption = $('<option disabled selected value>Select Craft Section/option>');
    $('#craftEntryTypeId').prop('disabled', true).append(entryTypeOption).addClass('disabled');
}

function integrate()
{
    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': integrateUrl,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        location.reload();

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function clearElements() {
    setElementInvisibleById('elements-wrapper');
    clearAllOptionsByClass("elements-select");
    clearAllElementsByClass("elements-field");
}

function populateEntryTypes()
{
    console.log("Triggered on change");

    var sectionsElement = document.getElementById("craftSectionId");
    console.log("sectionsElement " + sectionsElement );
    var sectionId = sectionsElement.options[sectionsElement.selectedIndex].value;
    console.log("sectionId " + sectionId);

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': entryTypesUrl+'/' + sectionId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllOptionsById("craftEntryTypeId");
        addToEntryTypes(data['entryTypes']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToEntryTypes(entryTypes) {
    $select = $("#craftEntryTypeId");

    var $newOption = $('<option disabled selected value>Select Craft Entry Type</option>');
    $select.append($newOption);

    for(var i=0; i < entryTypes.length; i++) {
        var $newOption = $("<option value='"+entryTypes[i].id+"'>"+ entryTypes[i].name +"</option>");
        $select.append($newOption);
    }
}

function populateFields(entryTypeId, cb)
{
    console.log("Starting to populate Fields");

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': fieldsUrl+'/' + entryTypeId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllOptionsByClass("elements-select");
        addToFields(data['fields'], function (done) {
            return cb(done);
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToFields(fields, cb) {
    $(".elements-select").each(function( index ) {

        var $newOption = $('<option value="">Select Craft Field</option>');
        $(this).append($newOption);

        for(var i=0; i < fields.length; i++) {
            var $newOption = $("<option value='"+fields[i].handle+"'>"+ fields[i].name +"</option>");
            $(this).append($newOption);
        }});

    return cb(true);
}

function populateElementsAndFields()
{
    console.log("Triggered on change");

    var temapltesElement = document.getElementById("gatherContentTemplateId");
    console.log("temapltesElement " + temapltesElement);
    var templateId = temapltesElement.options[temapltesElement.selectedIndex].value;
    console.log("templateId " + templateId);

    var sectionsElement = document.getElementById("craftEntryTypeId");
    console.log("sectionsElement " + sectionsElement );
    var entryTypeId = sectionsElement.options[sectionsElement.selectedIndex].value;
    console.log("entryTypeId " + entryTypeId);

    if (templateId && entryTypeId) {
        console.log("Starting to populate elements and fields");
        populateElements(templateId, function (done) {
            populateFields(entryTypeId, function (done) {
                setElementVisibleById('elements-wrapper');
            });
        });
    }
}

function populateElements(templateId, cb)
{
    console.log("Starting to populate Elements");

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': elementsUrl+'/' + templateId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllElementsByClass("elements-field");
        addToElements(data['elements'], function () {
            return cb(true);
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToElements(elements, cb) {

    for(var i=0; i < elements.length; i++) {

        var $wrapper = $('<div class="field first elements-field clearfix" id="elements-field"></div>');
        var $heading = $('<div class="heading"></div>');
        var $label = $('<label class="element-label" id="elements-label" for="elements['+elements[i].id+']">'+elements[i].label+' <a class="seperator">&rarr;</a></label>');
        var $input = $('<div class="input ltr"></div>');
        var $selectWrapper = $('<div class="select"></div>');
        var $select = $('<select class="elements-select" id="elements'+elements[i].name+'" name="elements['+elements[i].name+']"></select>');
        var $option = $('<option value="0">Select Cafts Section</option>');

        $heading.append($label);
        $wrapper.append($heading);
        $select.append($option);
        $selectWrapper.append($select);
        $input.append($selectWrapper);
        $wrapper.append($input);

        $('#elements').append($wrapper);
    }

    return cb(true);
}

function populateTemplates()
{
    console.log("Triggered on change");

    var projectsElement = document.getElementById("gatherContentProjectId");
    console.log("projectsElement " + projectsElement);
    var projectId = projectsElement.options[projectsElement.selectedIndex].value;
    console.log("projectId " + projectId);

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': templatesUrl+'/' + projectId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllOptionsById("gatherContentTemplateId");
        addToTemplates(data['templates']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function populateSections()
{
    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': sectionsUrl,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllOptionsById("craftSectionId");
        addToSections(data['sections']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToTemplates(templates) {
    $select = $("#gatherContentTemplateId");

    var $newOption = $('<option disabled selected value>Select GatherContent Template</option>');
    $select.append($newOption);

    for(var i=0; i < templates.length; i++) {
        var $newOption = $("<option value='"+templates[i].id+"'>"+ templates[i].name +"</option>");
        $select.append($newOption);
    }
}

function addToSections(sections) {
    $select = $("#craftSectionId");

    var $newOption = $('<option disabled selected value>Select Craft Section</option>');
    $select.append($newOption);

    for(var i=0; i < sections.length; i++) {
        var $newOption = $("<option value='"+sections[i].id+"'>"+ sections[i].name +"</option>");
        $select.append($newOption);
    }
}

function populateProjects()
{
    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': projectsUrl,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        addToProjects(data['projects']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToProjects(projects) {
    $select = $("#gatherContentProjectId");

    var $newOption = $('<option disabled selected value> Select a Project </option>');
    $select.append($newOption);

    for(var i=0; i < projects.length; i++) {
        var $newOption = $("<option value='"+projects[i].id+"'>"+ projects[i].name +"</option>");
        $select.append($newOption);
    }

}

function clearAllOptionsById(selectId) {
    document.getElementById(selectId).options.length = 0;
}

function clearAllOptionsByClass(selectClass) {
    $('.'+selectClass).empty();
}

function clearAllElementsById(elementId) {
    $( "#"+elementId ).remove();
}

function clearAllElementsByClass(elementClass) {
    $( "."+elementClass ).remove();
}

function setElementVisibleById (elementId) {
    $('#'+elementId).show();
}

function setElementInvisibleById (elementId) {
    $('#'+elementId).hide();
}

function setElementEnabledById (elementId) {
    $('#'+elementId).prop('disabled', false).removeClass('disabled');
}
