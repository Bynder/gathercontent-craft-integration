document.onload = assignIndividualMigration();
document.onload = assignClearSearch();
document.onload = assignSwitchMapping();
document.onload = assignCheckAll();

if (isEdit) {
    document.onload = assignOnChangeEvenets();
    document.finished = disableFirstOptions();
} else {
    document.onload = setUpFirstRow();
    document.onload = setUpSecondRow();
    document.onload = assignOnChangeEvenets();
    document.onload = populateAccounts();
}

var mappingsList;

document.finished = set_accardion_triggers();

function set_accardion_triggers() {
    $('.accordion-section-title').click(function (e) {

        updateAccardionItem($(this));

        e.preventDefault();
    });
}

function updateAccardionItem(context) {

    // Grab current anchor value
    var currentAttrValue = context.attr('href');

    if (context.hasClass('active')) {
        console.log('Close ' + currentAttrValue);
        context.removeClass('active');
        $(currentAttrValue).slideUp(300).removeClass('open');
    } else {
        console.log('Open ' + currentAttrValue);

        // Add active class to section title
        context.addClass('active');
        // Open up the hidden content panel
        $(currentAttrValue).slideDown(300).addClass('open');
    }
}

function disableFirstOptions() {
    $("select option[value='']").attr('disabled',"disabled");
}

function assignIndividualMigration() {
    console.log('Assigned Unhide');
    $("#individual-migration").click(function(e){
        e.preventDefault();
        unhideLoader();
    });
}

function assignClearSearch() {
    console.log('Assigned Clear Search');
    $("#clear-search").click(function(e){
        e.preventDefault();
        clearSearch();
    });
}

function assignCheckAll() {
    console.log('Assigned Check All');
    $("#check-all-wrapper").click(function(e){
        checkAll();
    });
}

function assignSwitchMapping() {
    console.log('Switch Assigned');

    $(".lightswitch").on("click", function(event) {
        switchMapping($(this));
    } );
}

function switchMapping(element) {
    console.log(element);

    var mappingId = element.attr("data-value");

    console.log("Triggered on change mapping switch " + mappingId);

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': switchMappingUrl+ '/' + mappingId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        if (data.success == true) {
            if (data.deactive == true) {
                console.log("Switching to dactive");
                $("#mapping-row-" + mappingId).attr('class', 'deactive-tr');
                $("#migrating-migrate-button-" + mappingId).hide();

            } else {
                console.log("Switching to active");
                $("#mapping-row-" + mappingId).attr('class', 'active-tr');
                $("#migrating-migrate-button-" + mappingId).show();
            }
        }

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function assignOnChangeEvenets()
{
    $("#gatherContentAccountId").on("change", function(event) {

        var accountElement = document.getElementById("gatherContentAccountId");
        console.log("accountElement " + accountElement);
        var accountId = accountElement.options[accountElement.selectedIndex].value;
        console.log("accountId " + accountId);

        if (accountId === '') {
            console.log("Empty Account Id");
            clearEverything();
        } else {
            populateProjects();
            populateSections();
            setElementEnabledById('gatherContentProjectId');
            setElementEnabledById('craftSectionId');
            clearElements();
        }
    } );

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

function clearEverything() {
    addToProjects([], function (success) {});
    addToSections([]);
    addToEntryTypes([], function (success) {});
    addToTemplates([], function (success) {});
    clearElements();
}

function setUpSecondRow() {
    var templateOption = $('<option disabled selected value>Select GatherContent Project</option>');
    $('#gatherContentTemplateId').append(templateOption).prop('disabled', true).addClass('disabled');
    var entryTypeOption = $('<option disabled selected value>Select Craft Section/option>');
    $('#craftEntryTypeId').prop('disabled', true).append(entryTypeOption).addClass('disabled');
}

function setUpFirstRow() {
    var templateOptionOne = $('<option disabled selected value>Select GatherContent Account</option>');
    var templateOptionTwo = $('<option disabled selected value>Select GatherContent Account</option>');
    $('#gatherContentProjectId').append(templateOptionOne).prop('disabled', true).addClass('disabled');
    $('#craftSectionId').append(templateOptionTwo).prop('disabled', true).addClass('disabled');

}

function clearElements() {
    setElementInvisibleById('elements-wrapper');
    clearAllOptionsByClass("elements-select");
    clearAllElementsByClass("elements-field");
    clearAllElementsByClass("tab-wrapper");
}

function populateEntryTypes(cb)
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

        addToEntryTypes(data['entryTypes'], function (success) {
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToEntryTypes(entryTypes, cb) {

    clearAllOptionsById("craftEntryTypeId");

    $select = $("#craftEntryTypeId");

    var $newOption = $('<option disabled selected value>Select Craft Entry Type</option>');
    $select.append($newOption);

    if (entryTypes.length > 0) {
        for(var i=0; i < entryTypes.length; i++) {

            if (entryTypes[i]['used']) {
                var $newOption = $("<option disabled value='"+entryTypes[i]['id']+"'>"+ entryTypes[i]['name'] +" (Already mapped)</option>");
            } else {
                var $newOption = $("<option value='"+entryTypes[i]['id']+"'>"+ entryTypes[i]['name'] +"</option>");
            }

            $select.append($newOption);
        }
    }

    return cb(true);
}

function populateFields(entryTypeId, templateId, elementName, cb)
{
    // console.log("Entry Type Id " + entryTypeId);
    //
    // $.ajax({
    //     'type': 'post',
    //     'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
    //     'cache': false,
    //     'url': fieldsUrl + '/' + entryTypeId + '/' + templateId + '/' + elementName,
    //     'dataType': 'json',
    //     'timeout': 50000000,
    //     data: {
    //         'CRAFT_CSRF_TOKEN': window.csrfTokenValue
    //     }
    // }).done(function (data) {
    //
    //     clearAllOptionsByClass("elements-select");
    //     addToFields(data['fields'], elementName, function (done) {
    //         return cb(done);
    //     });
    //
    // }).error(function (jqXHR, textStatus, errorThrown) {
    //     console.log(jQuery.parseJSON(jqXHR.responseText));
    //     alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    // });
}

function addToFields(fields, elementName, cb) {

    // console.log("Add to fields " + fields);
    //
    // $("#" + elementName).each(function( index ) {
    //
    //     var $newOption = $('<option value="">Dont Map</option>');
    //     $(this).append($newOption);
    //
    //     for(var i=0; i < fields.length; i++) {
    //         var $newOption = $("<option value='"+fields[i].handle+"'>"+ fields[i].name +"</option>");
    //         $(this).append($newOption);
    //     }});
    //
    // return cb(true);
}

function populateElementsAndFields()
{
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
        populateElements(templateId, entryTypeId, function (done) {
            setElementVisibleById('elements-wrapper');
        });
    } else {
        clearElements();
    }
}

function populateElements(templateId, entryTypeId, cb)
{
    console.log("Starting to populate Elements");

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': elementsUrl + '/' + templateId + '/' + entryTypeId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        clearAllElementsByClass("tab-wrapper");
        addToElements(data['tabs'], function () {
            return cb(true);
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToElements(tabs, cb) {

    for(var t=0; t < tabs.length; t++) {
        var elements = tabs[t]['elements'];

        var $tabWrapper = $('<div class="tab-wrapper" id="tab-'+tabs[t]['id']+'"></div>');

        if (t === 0) {
            var $tabTitle = $('<a class="accordion-section-title active" href="#accordion-'+tabs[t]['id']+'">'+tabs[t]['title']+'</a>');
            var $accardionContent = $('<div id="accordion-'+tabs[t]['id']+'" class="accordion-section-content open" style="display: block">');
        } else {
            var $tabTitle = $('<a class="accordion-section-title" href="#accordion-'+tabs[t]['id']+'">'+tabs[t]['title']+'</a>');
            var $accardionContent = $('<div id="accordion-'+tabs[t]['id']+'" class="accordion-section-content">');
        }

        $('#tabs').append($tabWrapper);
        $tabWrapper.append($tabTitle);
        $tabWrapper.append($accardionContent);

        for(var i=0; i < elements.length; i++) {

            var fields = elements[i].fields;
            console.log("Add to fields " + fields);

            var $wrapper = $('<div class="field first elements-field clearfix" id="elements-field-'+elements[i].name+'"></div>');
            var $heading = $('<div class="element-heading"></div>');
            var $label = $('<label class="element-label" id="elements-label" for="elements['+elements[i].id+']">'+elements[i].label+' <a class="seperator">&rarr;</a></label>');
            var $input = $('<div class="input ltr"></div>');
            var $selectWrapper = $('<div class="select"></div>');
            var $errorWrapper = $('<div class="error" id="element-error-'+elements[i].name+'"></div>');
            var $select = $('<select class="elements-select" id="select-'+elements[i].name+'" name="tabs['+tabs[t]['id']+'][elements]['+elements[i].name+']"></select>');

            var $newOption = $('<option value="">Dont Map</option>');
            $select.append($newOption);

            var elementName = elements[i].name;

            for(var f=0; f < fields.length; f++) {
                console.log('append field: ' +  fields[f].name);
                var $newOption = $("<option value='"+fields[f].handle+"'>"+ fields[f].name +"</option>");
                $select.append($newOption);
            }

            $heading.append($label);
            $wrapper.append($heading);
            $selectWrapper.append($select);
            $input.append($selectWrapper);
            $input.append($errorWrapper);
            $wrapper.append($input);

            $accardionContent.append($wrapper);

        }

        $tabTitle.click(function () {
            // Grab current anchor value
            var currentAttrValue = $(this).attr('href');

            if ($(this).hasClass('active')) {
                console.log('Close ' + currentAttrValue);
                $(this).removeClass('active');
                $(currentAttrValue).slideUp(300).removeClass('open');
            } else {
                console.log('Open ' + currentAttrValue);

                // Add active class to section title
                $(this).addClass('active');
                // Open up the hidden content panel
                $(currentAttrValue).slideDown(300).addClass('open');
            }
        });
    }

    return cb(true);
}

function populateFieldsElement(fields, elementName) {

    console.log('Fields: ' + fields);
    console.log('elementName: ' + elementName);

    $select = $("#select-"+elementName);

    console.log('$select: ' + $select);

    for(var f=0; f < fields.length; f++) {
        console.log('append field: ' +  fields[f].name);
        var $newOption = $("<option value='"+fields[f].handle+"'>"+ fields[f].name +"</option>");
        $select.append($newOption);
    }
}

function elementTypeValidate(elementContext) {
    element = elementContext.context;
    elementName = element.id;
    fieldHandle = element.options[element.selectedIndex].value;

    var temapltesElement = document.getElementById("gatherContentTemplateId");
    var templateId = temapltesElement.options[temapltesElement.selectedIndex].value;

    console.log("Triggered field type validation");

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': validateElementUrl +'/' + elementName + '/' + fieldHandle + '/' + templateId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        var elementErrorId = 'element-error-' + elementName;

        console.log("Received field type validation");

        if (data['success'] !== true) {
            console.log("Validation Error");
            $("#"+elementErrorId).text(data['error']);
        } else {
            console.log("Everything is good");
            $("#"+elementErrorId).text('');
        }
    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function populateTemplates(cb)
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

        addToTemplates(data['templates'], function (success) {
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function populateSections(cb)
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

        addToSections(data['sections']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToTemplates(templates, cb) {

    clearAllOptionsById("gatherContentTemplateId");

    $select = $("#gatherContentTemplateId");

    var $newOption = $('<option disabled selected value>Select GatherContent Template</option>');
    $select.append($newOption);

    if (templates.length > 0) {
        for(var i=0; i < templates.length; i++) {

            if (templates[i]['used']) {
                var $newOption = $("<option disabled value='"+templates[i]['id']+"'>"+ templates[i]['name'] +" (Already mapped)</option>");
            } else {
                var $newOption = $("<option value='"+templates[i]['id']+"'>"+ templates[i]['name'] +"</option>");
            }

            $select.append($newOption);
        }
    }
    
    return cb(true);
}

function addToSections(sections) {

    clearAllOptionsById("craftSectionId");

    $select = $("#craftSectionId");

    var $newOption = $('<option disabled selected value>Select Craft Section</option>');
    $select.append($newOption);

    if (sections.length > 0) {
        for(var i=0; i < sections.length; i++) {
            var $newOption = $("<option value='"+sections[i].id+"'>"+ sections[i].name +"</option>");
            $select.append($newOption);
        }
    }
}

function populateAccounts()
{
    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': accountsUrl,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {

        addToAccounts(data['accounts']);

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToAccounts(accounts) {
    $select = $("#gatherContentAccountId");

    if (accounts.length > 1) {

        if (isEdit == false) {
            console.log('Edit mode activated');
            var $newOption = $('<option disabled selected value> Select an Account</option>');
            $select.append($newOption);
        }

        for(var i=0; i < accounts.length; i++) {
            var $newOption = $("<option value='"+accounts[i].id+"'>"+ accounts[i].name +"</option>");
            $select.append($newOption);
        }
    } else {
        var $newOption = $("<option value='"+accounts[0].id+"'>"+ accounts[0].name +"</option>");
        $select.append($newOption);

        populateProjects();
        populateSections();
        setElementEnabledById('gatherContentProjectId');
        setElementEnabledById('craftSectionId');
    }
}

function populateProjects(cb)
{
    var accountElement = document.getElementById("gatherContentAccountId");
    console.log("accountElement " + accountElement);
    var accountId = accountElement.options[accountElement.selectedIndex].value;
    console.log("accountId " + accountId);

    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': projectsUrl +'/'+accountId,
        'dataType': 'json',
        'timeout': 50000000,
        data: {
            'CRAFT_CSRF_TOKEN': window.csrfTokenValue
        }
    }).done(function (data) {
        addToProjects(data['projects'], function (success) {
        });

    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function addToProjects(projects, cb) {
    clearAllOptionsById("gatherContentProjectId");
    $select = $("#gatherContentProjectId");

    var $newOption = $('<option disabled selected value> Select a Project </option>');
    $select.append($newOption);

    if (projects.length > 0) {
        for(var i=0; i < projects.length; i++) {
            var $newOption = $("<option value='"+projects[i].id+"'>"+ projects[i].name +"</option>");
            $select.append($newOption);
        }
    }

    return cb(true);
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

function clearSearch() {
    url = clearSearchRoute + '/' + templateId;
    redirect(url);
}

function checkAll() {
    $element = $("#check-all");
    var elementsClass = $element.attr('class');

    if (elementsClass === 'checkbox') {
        $("input:checkbox").prop('checked',true);
        $element.addClass('checked');
    } else {
        $("input:checkbox").prop('checked',false);
        $element.removeClass('checked');
    }

}

function unhideLoader() {
    console.log('Unhide loader');
    $('#migration-loader').removeClass('hidden');

    var form = $('#container');

    // Migrate All Templates
    $.ajax({
        'type': 'post',
        'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
        'cache': false,
        'url': integrateItemsUrl + '/' + templateId,
        'dataType': 'json',
        'timeout': 50000000,
        data: form.serialize()
    }).done(function (data) {

        if (data.redirect == false) {
            $('#migration-loader').addClass('hidden');
        } else {
            url = migrationFinishedUrl + '/' + data['migrationId'];
            $("input:checkbox").prop('checked',false);
            redirect(url);
        }
    }).error(function (jqXHR, textStatus, errorThrown) {
        console.log(jQuery.parseJSON(jqXHR.responseText));
        alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
    });
}

function setElementEnabledById (elementId) {
    $('#'+elementId).prop('disabled', false).removeClass('disabled');
}

function integrate(templateId)
{
    $('#migration-loader').removeClass('hidden');

    if (templateId === null) {

        // Migrate All Templates
        $.ajax({
            'type': 'post',
            'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
            'cache': false,
            'url': allMappingsUrl,
            'dataType': 'json',
            'timeout': 50000000,
            data: {
                'CRAFT_CSRF_TOKEN': window.csrfTokenValue
            }
        }).done(function (mappingsData) {

            mappingsList = mappingsData['mappings'];

            var finishedBatches = 0;
            var finishedMappingsCount = 0;
            var allMappingsCount = mappingsList.length;

            var firstMapping = mappingsList.pop();

            console.log('mappingsData: ' + mappingsData);
            console.log('mappingsList: ' + mappingsList);
            console.log('firstMapping: ' + firstMapping);

            if (firstMapping !== undefined) {
                updateMigratingBar(0, function (success) {
                    updateMigratingBarBatches(templateId, finishedBatches, function () {
                        integrateMapping(firstMapping, finishedMappingsCount, allMappingsCount, finishedBatches, null);
                        setElementVisibleById('migrating-bar');
                    });
                });
            }

        }).error(function (jqXHR, textStatus, errorThrown) {
            console.log(jQuery.parseJSON(jqXHR.responseText));
            alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
        });
    } else {

        mappingsList = [templateId];
        var finishedBatches = 0;
        var finishedMappingsCount = 0;
        var allMappingsCount = mappingsList.length;

        var firstMapping = mappingsList.pop();

        updateMigratingBar(0, function (success) {
            updateMigratingBarBatches(templateId, finishedBatches, function () {
                integrateMapping(firstMapping, finishedMappingsCount, allMappingsCount, finishedBatches, null);
                setElementVisibleById('migrating-bar');
            });
        });
    }
}

function integrateMapping (templateId, finishedMappingsCount, allMappingsCount, finishedBatches, migrationId) {

    var integrateFullUrl = integrateUrl +'/'+ templateId;

    if (migrationId !== null) {
        integrateFullUrl = integrateUrl +'/'+ templateId + '/' + migrationId;
    }

    onMigratingStatus(templateId, function () {
        $.ajax({
            'type': 'post',
            'contentType': 'application/x-www-form-urlencoded; charset=UTF-8',
            'cache': false,
            'url': integrateFullUrl,
            'dataType': 'json',
            'timeout': 50000000,
            data: {
                'CRAFT_CSRF_TOKEN': window.csrfTokenValue
            }
        }).done(function (integrateData) {

            migrationId = integrateData.migrationId;

            finishedBatches = finishedBatches + 1;

            updateMigratingBarBatches(templateId, finishedBatches, function () {
                if (integrateData.finished === false) {
                    console.log('Not finished');
                    integrateMapping(templateId, finishedMappingsCount, allMappingsCount, finishedBatches, migrationId);
                } else {
                    finishedMappingsCount = finishedMappingsCount + 1;

                    getMigratingBarPercent(finishedMappingsCount, allMappingsCount, function (percent) {
                        console.log('returned percent: ' + percent);
                        updateMigratingBar(percent, function (success) {
                            nextMapping = mappingsList.pop();
                            finishedBatches = 0;

                            if (nextMapping !== undefined) {
                                offMigratingStatus(templateId, function () {
                                    integrateMapping(nextMapping, finishedMappingsCount, allMappingsCount, finishedBatches, migrationId);
                                });
                            } else {
                                offMigratingStatus(templateId, function () {
                                    updateMigratingBar('finished', function (success) {
                                        // window.location.replace(migrationFinishedUrl + '/' + migrationId);
                                        $('#migration-loader').addClass('hidden');
                                        redirect(migrationFinishedUrl + '/' + migrationId)
                                    });
                                });
                            }
                        })
                    });
                }
            });

        }).error(function (jqXHR, textStatus, errorThrown) {
            console.log(jQuery.parseJSON(jqXHR.responseText));
            alert("Something went wrong: " + jQuery.parseJSON(jqXHR.responseText)['error']['message']);
        });
    });
}

function getMigratingBarPercent(finishedMappingsCount, allMappingsCount, cp) {

    var percent = finishedMappingsCount / allMappingsCount * 100;

    console.log('finishedMappingsCount: ' + finishedMappingsCount);
    console.log('allMappingsCount: ' + allMappingsCount);
    console.log('percent: ' + percent);
    return cp(percent);
}

function updateMigratingBar(percent, cp) {
    if (percent === 'finished') {
        $("#migrating-bar-value").text('Finished');
        $("#myBar").width('100%');
    } else {
        console.log('received percent: ' + percent);
        $("#migrating-bar-value").text(percent + '%');
        $("#myBar").width(percent + '%');
    }

    return cp(true)
}

function updateMigratingBarBatches(templateId, batches, cp) {
    $("#migrating-batches-"+templateId).text(' (' + batches + ')');
    $("#migrating-migrate-button").text('Migrating').prop('disabled', true).prop('onclick',null).off('click').addClass('disabled');
    return cp(true)
}

function onMigratingStatus(templateId, cp) {
    console.log('Turn on status: ' + templateId);
    $("#migrating-status-"+templateId).attr('class', 'status live');
    return cp(true)
}

function offMigratingStatus(templateId, cp) {
    console.log('Turn off status: ' + templateId);
    $("#migrating-status-"+templateId).attr('class', 'status light');
    return cp(true)
}

function redirect (url) {
    window.location.href = url;

}