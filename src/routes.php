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
    'switch-mapping/<mappingId:\d+>' => 'gathercontent/integrate/switch-mapping/',
    'integrate-items-individually/<templateId:\d+>' => 'gathercontent/mapping/integrate-items',
    'validate-credentials' => 'gathercontent/integrate/validate',
    'integrate/<templateId:\d+>' => 'gathercontent/integrate/run',
    'integrate/<templateId:\d+>/<migrationId:\d+>' => 'gathercontent/integrate/run',
    'integrate-items/<templateId:\d+>' => 'gathercontent/integrate/run-items',
    'integrate-items/<templateId:\d+>/<migrationId:\d+>' => 'gathercontent/integrate/run-items',
    'get-projects/<accountId:\w+>' => 'gathercontent/integrate/get-projects',
    'get-sections' => 'gathercontent/integrate/get-sections',
    'get-accounts' => 'gathercontent/integrate/get-accounts',
    'get-templates/<projectId:\w+>' => 'gathercontent/integrate/get-templates',
    'get-all-mappings' => 'gathercontent/integrate/get-all-mappings',
    'get-elements/<templateId:\w+>/<entryTypeId:\w+>' => 'gathercontent/integrate/get-elements',
    'get-fields/<entryTypeId:\w+>/<templateId:\w+>/<elementName:\w+>' => 'gathercontent/integrate/get-fields',
    'get-entry-types/<sectionId:\w+>' => 'gathercontent/integrate/get-entry-types',
    'migration-finished/<migrationId:\w+>' => 'gathercontent/mapping/migration-finished',
    'validate-element-type/<elementName:\w+>/<fieldHandle:\w+>/<templateId:\w+>' => 'gathercontent/integrate/validate-element-type',
];