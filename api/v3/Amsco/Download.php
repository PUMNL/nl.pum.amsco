<?php
use CRM_Amsco_ExtensionUtil as E;

/**
 * Amsco.Download API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_amsco_download_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * Amsco.Download API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_amsco_download($params) {
  CRM_Core_Error::debug_log_message('AMSCO Download - Starting Amsco Applications Download');

  $returnValues = array();
  $dir = '/var/OneDriveAmsco';
  $items = array_diff(scandir($dir), array('..', '.'));

  foreach($items as $key => $item) {
    if(is_file($dir.'/'.$item) && file_exists($dir.'/'.$item)){
      $returnValues[$key] = $item;

      //Import data
      try {
        CRM_Core_Error::debug_log_message('AMSCO Download - Start importing: '.$item.' ('.$dir.'/'.$item.')');

        CRM_Amsco_Utils::ImportData($dir.'/'.$item);

        CRM_Core_Error::debug_log_message('AMSCO Download - '.$item.' imported succesfully');
      } catch (Exception $e){
        CRM_Core_Error::debug_log_message('AMSCO Download - Unable to import '.$item);
        CRM_Core_Error::debug_log_message('AMSCO Download - '.$e->getMessage());
      }

      //Move file to processed
      try {
        rename($dir.'/'.$item, $dir.'/processed/'.$item);
      } catch (Exception $e){
        CRM_Core_Error::debug_log_message('AMSCO Download - Unable to move '.$item.' to processed directory: '.$e->getMessage());
      }
    }
  }

  CRM_Core_Error::debug_log_message('AMSCO Download - Finished');

  return civicrm_api3_create_success($returnValues, $params, 'Amsco', 'Download');
}
