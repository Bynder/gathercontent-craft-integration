<?php

return [
    // Testig
    'gathercontent/do-something' => 'gathercontent/test/do-something',
    
    // MappingModel
    'gathercontent' => 'gathercontent/mapping/index',
    'gatherContent' => 'gathercontent/mapping/index',
    'gatherContent/mapping/index' => 'gathercontent/mapping/index',
    'gatherContent/mapping/template/<templateId:\w+>' => 'gathercontent/mapping/template',
    'gatherContent/mapping/new' => 'gathercontent/mapping/edit',
    'gatherContent/mapping/edit/<mappingId:\d+>' => 'gathercontent/mapping/edit',
    'gatherContent/mapping/delete/<mappingId:\d+>' => 'gathercontent/mapping/delete',
    'gatherContent/mapping/activate/<mappingId:\d+>' => 'gathercontent/mapping/activate',
    'gatherContent/mapping/migrate-one/<templateId:\d+>' => 'gathercontent/integrate/run-one',
    'gatherContent/mapping/<id:\d+>' => 'gathercontent/mapping/purge',
    'gatherContent/mapping/save' => 'gathercontent/mapping/save',

    // Ajax
    'gatherContent/api/switch-mapping/<mappingId:\d+>' => 'gathercontent/integrate/switch-mapping/',
    'gatherContent/api/integrate-items-individually/<templateId:\d+>' => 'gathercontent/mapping/integrate-items',
    'gatherContent/api/validate-credentials' => 'gathercontent/integrate/validate',
    'gatherContent/api/integrate/<templateId:\d+>' => 'gathercontent/integrate/run',
    'gatherContent/api/integrate/<templateId:\d+>/<migrationId:\d+>' => 'gathercontent/integrate/run',
    'gatherContent/api/integrate-items/<templateId:\d+>' => 'gathercontent/integrate/run-items',
    'gatherContent/api/integrate-items/<templateId:\d+>/<migrationId:\d+>' => 'gathercontent/integrate/run-items',
    'gatherContent/api/get-projects/<accountId:\w+>' => 'gathercontent/integrate/get-projects',
    'gatherContent/api/get-sections' => 'gathercontent/integrate/get-sections',
    'gatherContent/api/get-accounts' => 'gathercontent/integrate/get-accounts',
    'gatherContent/api/get-templates/<projectId:\w+>' => 'gathercontent/integrate/get-templates',
    'gatherContent/api/get-all-mappings' => 'gathercontent/integrate/get-all-mappings',
    'gatherContent/api/get-elements/<templateId:\w+>/<entryTypeId:\w+>' => 'gathercontent/integrate/get-elements',
    'gatherContent/api/get-fields/<entryTypeId:\w+>/<templateId:\w+>/<elementName:\w+>' => 'gathercontent/integrate/get-fields',
    'gatherContent/api/get-entry-types/<sectionId:\w+>' => 'gathercontent/integrate/get-entry-types',
    'gatherContent/api/migration-finished/<migrationId:\w+>' => 'gathercontent/mapping/migration-finished',
    'gatherContent/api/validate-element-type/<elementName:\w+>/<fieldHandle:\w+>/<templateId:\w+>' => 'gathercontent/integrate/validate-element-type',
];