<?php
/**
 * Class for processing amsco applications
 *
 */

class CRM_Amsco_Utils {

  public static function ImportData($fullFileName='') {
    //Open import file
    $file=fopen($fullFileName, 'r');

    if (!$file) {
      CRM_Core_Error::debug_log_message('AMSCO Download - Could not open file "'.$fullFileName.'"');
      CRM_Utils_System::setUFMessage('Could not open file: "' . $fullFileName . '"');
    } else {
      $i=0;

      //Read import file line by line
      while (($line = fgetcsv($file,0,';')) !== false) {
        $i++;

        //skip first header line
        if($i==1){
          continue;
        }

        if (!empty($line[$i])) {
          //Create contact
          try {
            $params_contact = array(
              'version' => 3,
              'sequential' => 1,
              'contact_type' => 'Organization',
              'contact_sub_type' => Chr(1).'Customer'.Chr(1),
              'organization_name' => $line[0],
              'contact_source' => $line[39],
            );
            $contact = civicrm_api('Contact', 'create', $params_contact);
          } catch (Exception $e){
            CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create contact:');
            CRM_Core_Error::debug_log_message(print_r($contact, TRUE));
          }

          //Create additional contact data
          $location_type_work = civicrm_api('LocationType', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Work'));
          $phone_type_id = civicrm_api('OptionValue', 'getsingle', array('version' => 3, 'sequential' => 1, 'option_group_name' => 'phone_type', 'name' => 'Phone'));

          if(!empty($contact['id'])){
            try {
              if(!empty($line[38]) && !empty($line[1]) && !empty($line[2]) && !empty($line[3])){
                $country = civicrm_api('Country', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => $line[38]));
                $address = civicrm_api('Address', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'street_address' => $line[1], 'postal_code' => $line[3], 'city' => $line[2], 'country_id' => $country['id']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create address for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[11])){
                $email = civicrm_api('Email', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[11]));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create email for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[5])){
                $phone = civicrm_api('Phone', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[5], 'phone_type_id' => $phone_type_id['value']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create phone for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[6])){
                $website = civicrm_api('Website', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'url' => $line[6], 'website_type_id' => $location_type_work['id']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create website for contact: '.$contact['id']);
            }

            //Create authorised contact
            try {
              if(!empty($line[7]) && !empty($line[9])){
                $params_authorised_contact = array(
                  'version' => 3,
                  'sequential' => 1,
                  'contact_type' => 'Individual',
                  'first_name' => $line[7],
                  'middle_name' => $line[8],
                  'last_name' => $line[9],
                  'job_title' => $line[10],
                  'contact_source' => $line[39]
                );
                $result_authorised_contact = civicrm_api('Contact', 'create', $params_authorised_contact);
              } else {
                CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create authorised contact, first_name or last_name is empty: '.$contact['id']);
              }

              if(!empty($result_authorised_contact['id'])) {
                //Create e-mail for authorised contact
                $params_email_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[11], 'job_title' => $line[10]);
                $email_authorised_contact = civicrm_api('Email', 'create', $params_email_authorised_contact);

                //Create phone for authorised contact
                $params_phone_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[12], 'phone_type_id' => $phone_type_id['value']);
                $phone_authorised_contact = civicrm_api('Phone', 'create', $params_phone_authorised_contact);

                //Create relationship between authorised contact and organisation
                $relationship_type_authorised_contact = civicrm_api('RelationshipType', 'getsingle', array('version' => 3, 'sequential' => 1, 'name_a_b' => 'Has authorised'));
                $params_create_relation_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id_a' => $contact['id'], 'contact_id_b' => $result_authorised_contact['id'],'relationship_type_id' => $relationship_type_authorised_contact['id']);
                $create_relation_authorised_contact = civicrm_api('Relationship', 'create', $params_create_relation_authorised_contact);
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create authorised contact: '.$contact['id']);
            }

            try {
              //Get custom data tab custom fields
              $cg_additional_data = civicrm_api('CustomGroup', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Customers_Data'));
              $cf_additional_data = civicrm_api('CustomField', 'get', array('version' => 3, 'sequential' => 1, 'custom_group_id' => 'Customers_Data', 'rowCount'=>0));

              $ad_custom_fields = array();
              foreach($cf_additional_data['values'] as $key => $value) {
                $ad_custom_fields[$value['name']] = $value['column_name'];
              }

              //Get gender option value
              $gender = array();
              $ov_gender = civicrm_api('OptionValue', 'get', array('version' => 3, 'sequential' => 1, 'option_group_name' => 'gender'));
              if(!empty($ov_gender['values']) && is_array($ov_gender['values'])){
                foreach($ov_gender['values'] as $key => $value) {
                  $gender[$value['name']] = $value['value'];
                }
              }
              $gender_from_file = ucfirst(strtolower($line[35]));

              //Create additional data
              $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`) VALUES (".$contact['id'].", %1, %2, %3, %4)";
              CRM_Core_DAO::executeQuery($sql,array(
                1 => array($line[33],'String'),
                2 => array($line[34],'String'),
                3 => array((int)$gender[$gender_from_file], 'Integer'),
                4 => array((int)$line[36], 'Integer')
              ));
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create additional data for contact: '.$contact['id']);
            }

            //Create project
            $project_name = $line[0];
            $reason = $line[13];
            $work_description = $line[14];
            $expected_results = $line[15];
            $projectplan = $line[16];
            $projectplan2 = $line[17];
            $project_start_date = date('YmdHis');

            $project = civicrm_api('PumProject', 'create', array('version' => 3, 'sequential' => 1, 'title' => 'Project '.$project_name, 'customer_id' => $contact['id'], 'is_active' => 1, 'start_date' => $project_start_date, 'reason' => utf8_encode($reason), 'work_description' => utf8_encode($work_description),'expected_results' => utf8_encode($expected_results), 'projectplan' => utf8_encode($projectplan).' '.utf8_encode($projectplan2)));

            //Create yearly information
            $cg_yearly_information = civicrm_api('CustomGroup', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Employee_Information'));
            $cf_yearly_information = civicrm_api('CustomField', 'get', array('version' => 3, 'sequential' => 1, 'custom_group_id' => 'Employee_Information'));
            $yi_custom_fields = array();
            foreach($cf_yearly_information['values'] as $key => $value) {
              $yi_custom_fields[$value['name']] = $value['column_name'];
            }

            $sql_yearly_information = "INSERT INTO `".$cg_yearly_information['table_name']."` (
              `entity_id`,
              `".$yi_custom_fields['Year']."`,
              `".$yi_custom_fields['Full_time_employees']."`,
              `".$yi_custom_fields['Part_time_employees']."`,
              `".$yi_custom_fields['Total_turnover']."`,
              `".$yi_custom_fields['Balance_sheet_total']."`
              )
              VALUES (
              ".$contact['id'].",
              %1,
              %2,
              %3,
              %4,
              %5
            )";

            CRM_Core_DAO::executeQuery($sql_yearly_information,
              array(
                1 => array((int)$line[18],'Integer'),
                2 => array((int)$line[19],'Integer'), //permanent employees
                3 => array((int)$line[20],'Integer'), //non-permanent employees
                4 => array((int)$line[21],'Integer'), //turnover
                5 => array((int)$line[22],'Integer'), //balance sheet total
              )
            );

            CRM_Core_DAO::executeQuery($sql_yearly_information,
              array(
                1 => array((int)$line[23],'Integer'),
                2 => array((int)$line[24],'Integer'), //permanent employees
                3 => array((int)$line[25],'Integer'), //non-permanent employees
                4 => array((int)$line[26],'Integer'), //turnover
                5 => array((int)$line[27],'Integer'), //balance sheet total
              )
            );

            CRM_Core_DAO::executeQuery($sql_yearly_information,
              array(
                1 => array((int)$line[28],'Integer'),
                2 => array((int)$line[29],'Integer'), //permanent employees
                3 => array((int)$line[30],'Integer'), //non-permanent employees
                4 => array((int)$line[31],'Integer'), //turnover
                5 => array((int)$line[32],'Integer'), //balance sheet total
              )
            );
          }
        }
      }
    }
  }
}