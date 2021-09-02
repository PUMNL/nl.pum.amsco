<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return array (
  0 => 
  array (
    'name' => 'Cron:Amsco.Download',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Call Amsco.Download API',
      'description' => 'Call Amsco.Download API',
      'run_frequency' => 'Daily',
      'api_entity' => 'Amsco',
      'api_action' => 'Download',
      'parameters' => '',
    ),
  ),
);
