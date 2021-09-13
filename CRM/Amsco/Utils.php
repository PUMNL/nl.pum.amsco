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
              'organization_name' => $line[6],
              'contact_source' => 'AMSCO',
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
              if(!empty($line[7]) && !empty($line[8]) && !empty($line[9])){
                $country = civicrm_api('Country', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Kenya'));
                $address = civicrm_api('Address', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'street_address' => $line[7], 'postal_code' => $line[9], 'city' => $line[8], 'country_id' => $country['id']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create address for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[10])){
                $email = civicrm_api('Email', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[10]));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create email for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[11])){
                $phone = civicrm_api('Phone', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[11], 'phone_type_id' => $phone_type_id['value']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create phone for contact: '.$contact['id']);
            }
            try {
              if(!empty($line[12])){
                $website = civicrm_api('Website', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'url' => $line[12], 'website_type_id' => $location_type_work['id']));
              }
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create website for contact: '.$contact['id']);
            }

            //Create authorised contact
            try {
              if(!empty($line[13]) && !empty($line[15])){
                $params_authorised_contact = array(
                  'version' => 3,
                  'sequential' => 1,
                  'contact_type' => 'Individual',
                  'first_name' => $line[13],
                  'middle_name' => $line[14],
                  'last_name' => $line[15],
                  'job_title' => $line[16],
                  'contact_source' => 'AMSCO'
                );
                $result_authorised_contact = civicrm_api('Contact', 'create', $params_authorised_contact);
              } else {
                CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create authorised contact for contact ID: '.$contact['id'].', first_name or last_name is empty');
              }

              if(!empty($result_authorised_contact['id'])) {
                //Create e-mail for authorised contact
                $params_email_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[17]);
                $email_authorised_contact = civicrm_api('Email', 'create', $params_email_authorised_contact);

                //Create phone for authorised contact
                $params_phone_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[18], 'phone_type_id' => $phone_type_id['value']);
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
              $gender_from_file = ucfirst(strtolower($line[39]));

              //Create additional data
              $sql_params = array(
                1 => array($line[37],'String'),
                2 => array($line[38],'String'),
                4 => array((int)$line[40], 'Integer')
              );

              $pum_conditions_met = $line[5];
              if($pum_conditions_met == 'Yes') {
                $conditions = 'I Agree';
              } else {
                $conditions = 'NULL';
              }
              if($gender_from_file == 'Male' || $gender_from_file == 'Female') {
                $sql_params[3] = array((int)$gender[$gender_from_file], 'Integer');
                $sql_params[5] = array($conditions, 'String');
                if($conditions == 'I Agree') {
                  $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`,`".$ad_custom_fields['Gentlemen_s_Agreement']."`) VALUES (".$contact['id'].", %1, %2, %3, %4, %5)";
                } else {
                  $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`,`".$ad_custom_fields['Gentlemen_s_Agreement']."`) VALUES (".$contact['id'].", %1, %2, %3, %4, NULL)";
                }

              } else {
                $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`) VALUES (".$contact['id'].", %1, %2, NULL, %4)";
              }

              CRM_Core_DAO::executeQuery($sql, $sql_params);
            } catch (Exception $e){
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create additional data for contact: '.$contact['id']);
            }

            //Create project
            $project_name = $line[6];
            $reason = $line[19];
            $work_description = $line[20];
            $expected_results = $line[21];
            //$projectplan = $line[16];
            //$projectplan2 = $line[17];
            $project_start_date = date('YmdHis');

            $project = civicrm_api('PumProject', 'create', array('version' => 3, 'sequential' => 1, 'title' => 'Project '.$project_name, 'customer_id' => $contact['id'], 'is_active' => 1, 'start_date' => $project_start_date, 'reason' => utf8_encode($reason), 'work_description' => utf8_encode($work_description),'expected_results' => utf8_encode($expected_results)));//, 'projectplan' => utf8_encode($projectplan).' '.utf8_encode($projectplan2)));

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
                1 => array((int)$line[22],'Integer'), //year
                2 => array((int)$line[23],'Integer'), //permanent employees
                3 => array((int)$line[24],'Integer'), //non-permanent employees
                4 => array((int)$line[25],'Integer'), //turnover
                5 => array((int)$line[26],'Integer'), //balance sheet total
              )
            );

            CRM_Core_DAO::executeQuery($sql_yearly_information,
              array(
                1 => array((int)$line[27],'Integer'), //year
                2 => array((int)$line[28],'Integer'), //permanent employees
                3 => array((int)$line[29],'Integer'), //non-permanent employees
                4 => array((int)$line[30],'Integer'), //turnover
                5 => array((int)$line[31],'Integer'), //balance sheet total
              )
            );

            CRM_Core_DAO::executeQuery($sql_yearly_information,
              array(
                1 => array((int)$line[32],'Integer'), //year
                2 => array((int)$line[33],'Integer'), //permanent employees
                3 => array((int)$line[34],'Integer'), //non-permanent employees
                4 => array((int)$line[35],'Integer'), //turnover
                5 => array((int)$line[36],'Integer'), //balance sheet total
              )
            );

            //Create Prof & CC Relationship on contact card
            //first get country contact
            $country_contact = civicrm_api('Contact', 'getsingle', array(
              'version' => 3,
              'sequential' => 1,
              'contact_type' => 'Organization',
              'contact_sub_type' => 'Country',
              'organization_name' => $country['name']
            ));

            $project_officer_id = self::getProjectOfficerForCountry($country_contact['id']);
            $country_coordinator_id = self::getCountryCoordinatorForCountry($country_contact['id']);

            $project_officer = civicrm_api('Contact', 'getsingle', array('version' => 3, 'sequential' => 1, 'id' => $project_officer_id));
            $country_coordinator = civicrm_api('Contact', 'getsingle', array('version' => 3, 'sequential' => 1, 'id' => $country_coordinator_id));

            $projectOfficerRelationshipTypeId = self::getProjectOfficerRelationshipTypeId();
            $countryCoordinatorRelationshipTypeId = self::getCountryCoordinatorRelationshipTypeId();

            //Create prof relationship on client contact
            if(!empty($project_officer['id'])){
              $params_create_prof_relationship = array(
                'version' => 3,
                'sequential' => 1,
                'contact_id_a' => $contact['id'],
                'contact_id_b' => $project_officer['id'],
                'relationship_type_id' => $projectOfficerRelationshipTypeId
              );
              $create_prof_relationship = civicrm_api('Relationship', 'create', $params_create_prof_relationship);
            }

            //Create cc relationship on client contact
            if(!empty($country_coordinator['id'])){
              $params_create_cc_relationship = array(
                'version' => 3,
                'sequential' => 1,
                'contact_id_a' => $contact['id'],
                'contact_id_b' => $country_coordinator['id'],
                'relationship_type_id' => $countryCoordinatorRelationshipTypeId
              );
              $create_cc_relationship = civicrm_api('Relationship', 'create', $params_create_cc_relationship);
            }

            //Send e-mail notification to Prof & CC
            global $base_url;
            $domain = civicrm_api('Domain', 'getsingle', array('version' => 3, 'sequential' => 1));
            $mail_from = $domain['from_email'];
            $email_contacts = array($project_officer);

            foreach($email_contacts as $key => $value) {
              //Send e-mail to inform them
              $mail_to = $value['email'];

              $email = "A new project for AMSCO has been created in ProCus. <br />";
              $email .= "See contact card: <a href=\"".CIVICRM_UF_BASEURL."civicrm/contact/view?reset=1&cid=".$contact['id']."\">".$line[6]."</a>";

              $mail_subject = 'New AMSCO project has been added: '.$line[6];

              $nl = "\r\n";
              $mail_headers = 'MIME-Version: 1.0' . $nl;
              $mail_headers .= 'Content-type: text/html; charset=iso-8859-1' . $nl;
              $mail_headers .= 'From: ' . $mail_from . $nl;
              $mail_headers .= 'Reply-To: ' . $mail_from . $nl;

              $mail_message = '<html>';
              $mail_message .= '<head>';
              $mail_message .= '<title>';
              $mail_message .= $mail_subject;
              $mail_message .= '</title>';
              $mail_message .= '</head>';
              $mail_message .= '<body>';
              $mail_message .= 'Dear '.$value['first_name'].',<br />';
              $mail_message .= '<br />';
              $mail_message .= 'A new project for AMSCO has been submitted, see details at: <a href="'.$base_url.'/civicrm/contact/view?reset=1&cid='.$contact['id'].'">'.$line[6].'</a><br />';
              $mail_message .= '<br />';
              $mail_message .= 'Kind regards,<br />';
              $mail_message .= '<br />';
              $mail_message .= 'PUM Netherlands senior experts';
              $mail_message .= '</body>';
              $mail_message .= '</html>';

              $mail_sent = mail($mail_to, $mail_subject, $mail_message, $mail_headers);

              if($mail_sent == FALSE){
                CRM_Core_Error::debug_log_message('Unable to send e-mail to: '.$mail_to.' (contact ID: '.$value['id'].') about new AMSCO application: '.CIVICRM_UF_BASEURL."civicrm/contact/view?reset=1&cid=".$contact['id']);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Method to find the project officer for a country
   *
   * @param int $countryId
   * @return bool|int
   */
  public static function getProjectOfficerForCountry($countryId) {
    if (empty($countryId)) {
      return FALSE;
    }

    try {
      $relationship_type_id = self::getProjectOfficerRelationshipTypeId();
      $relationships = civicrm_api3('Relationship', 'get', array(
        'relationship_type_id' => $relationship_type_id,
        'contact_id_a' => $countryId,
        'is_active' => 1,
        'end_date' => NULL,
      ));

      // take the one that is not on a case
      foreach ($relationships['values'] as $rel_id => $relationship) {
        if (!isset($relationship['case_id']) && $relationship['is_active'] == 1 && !isset($relationship['end_date'])) {
          return $relationship['contact_id_b'];
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to set the relationship type id of the project officer
   *
   * @throws Exception
   */
  private static function getProjectOfficerRelationshipTypeId() {
    try {
      $projectOfficerRelationshipTypeId = civicrm_api3('RelationshipType', 'getvalue', array(
        'name_a_b' => 'Project Officer for',
        'name_b_a' => 'Project Officer is',
        'return' => 'id'
      ));

      return $projectOfficerRelationshipTypeId;
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a relationship Project Officer in '.__METHOD__
        .', contact your system administrator. Error from API RelationshipType getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to find the country coordinator for a country
   *
   * @param int $countryId
   * @return bool|int
   */
  public static function getCountryCoordinatorForCountry($countryId) {
    if (empty($countryId)) {
      return FALSE;
    }

    try {
      $relationship_type_id = self::getCountryCoordinatorRelationshipTypeId();
      $relationships = civicrm_api3('Relationship', 'get', array(
        'relationship_type_id' => $relationship_type_id,
        'contact_id_a' => $countryId,
        'is_active' => 1,
        'end_date' => NULL
      ));

      // take the one that is not on a case
      foreach ($relationships['values'] as $rel_id => $relationship) {
        if (!isset($relationship['case_id']) && $relationship['is_active'] == 1 && !isset($relationship['end_date'])) {
          return $relationship['contact_id_b'];
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to set the relationship type id of the project officer
   *
   * @throws Exception
   */
  private static function getCountryCoordinatorRelationshipTypeId() {
    try {
      $countryCoordinatorRelationshipTypeId = civicrm_api3('RelationshipType', 'getvalue', array(
        'name_a_b' => 'Country Coordinator is',
        'name_b_a' => 'Country Coordinator for',
        'return' => 'id'
      ));

      return $countryCoordinatorRelationshipTypeId;
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a relationship Country Coordinator is in '.__METHOD__
        .', contact your system administrator. Error from API RelationshipType getvalue: '.$ex->getMessage());
    }
  }
}