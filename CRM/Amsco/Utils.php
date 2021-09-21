<?php
/**
 * Class for processing amsco applications
 *
 */

class CRM_Amsco_Utils {

  public static function ImportData($fullFileName='') {
    $errors = array();

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

        if (!empty($line[0])) {
          //Create contact
          try {
            if (!empty($line[6])){
              $params_contact = array(
                'version' => 3,
                'sequential' => 1,
                'contact_type' => 'Organization',
                'contact_sub_type' => Chr(1).'Customer'.Chr(1),
                'organization_name' => $line[6],
                'contact_source' => 'AMSCO',
              );
              $contact = civicrm_api('Contact', 'create', $params_contact);
            }
          } catch (CiviCRM_API3_Exception $e){
            $error_msg = 'AMSCO Download - Unable to create contact:';
            CRM_Core_Error::debug_log_message(print_r($contact, TRUE));
            CRM_Core_Error::debug_log_message($e->getMessage());
            $errors[] = $error_msg;
          }

          if(!empty($contact['id'])){
            //Create additional contact data
            $location_type_work = civicrm_api('LocationType', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Work'));
            $phone_type_id = civicrm_api('OptionValue', 'getsingle', array('version' => 3, 'sequential' => 1, 'option_group_name' => 'phone_type', 'name' => 'Phone'));

            if(!empty($line[7]) && !empty($line[8]) && !empty($line[9])){
              try {
                $country = civicrm_api('Country', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Kenya'));

                $address_params = array(
                  'version' => 3,
                  'sequential' => 1,
                  'contact_id' => $contact['id'],
                  'location_type_id' => $location_type_work['id'],
                  'street_address' => $line[7],
                  'city' => $line[8],
                  'country_id' => $country['id']
                );
                if(strlen($line[9]) > 12){
                  $error_msg = 'AMSCO Download - Unable to postal code for contact: '.$contact['id'].'. Postal code: '.$line[9].' is too long';
                  $errors[] = $error_msg;
                } else {
                  $address_params['postal_code'] = $line[9];
                }


                $address = civicrm_api('Address', 'create', $address_params);
              } catch(CiviCRM_API3_Exception $e) {
                $error_msg = 'AMSCO Download - Unable to create address for contact: '.$contact['id'];
                CRM_Core_Error::debug_log_message($e->getMessage());
                $errors[] = $error_msg;
              }
            } else {
              //No address details, but create an address so we know that this company is from Kenya (applications are always from Kenya)
              $country = civicrm_api('Country', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Kenya'));

              $address_params = array(
                'version' => 3,
                'sequential' => 1,
                'contact_id' => $contact['id'],
                'location_type_id' => $location_type_work['id'],
                'country_id' => $country['id']
              );

              $address = civicrm_api('Address', 'create', $address_params);
              CRM_Core_Error::debug_log_message('AMSCO Download - Unable to create address for contact: '.$contact['id'].'. Created empty address with country Kenya.');
            }

            try {
              if (!empty($line[10]) && strpos($line[10], '@') !== FALSE) {
                $email = civicrm_api('Email', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[10]));
              } else {
                if(!empty($line[10])){
                  $error_msg = 'AMSCO Download - Invalid e-mailaddress for contact, contact ID: '.$contact['id'].', email: '.$line[10];
                  $errors[] = $error_msg;
                } else {
                  $error_msg = 'AMSCO Download - No e-mailaddress found for contact, contact ID: '.$contact['id'];
                  $errors[] = $error_msg;
                }
              }
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to create email for contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }
            try {
              if(!empty($line[11])){
                if (preg_match("/^((\+[1-9]{1,4}[ \-]*)|(\([0-9]{2,3}\)[ \-]*)|([0-9]{2,4})[ \-]*)*?[0-9]{2,4}?[ \-]*[0-9]{2,4}?$/",$line[11])){
                  $phone = civicrm_api('Phone', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[11], 'phone_type_id' => $phone_type_id['value']));

                  if($phone['is_error'] == 1){
                    $error_msg = 'AMSCO Download - Unable to create phone number for contact ID: '.$contact['id'].', phone: '.$line[11];
                    $errors[] = $error_msg;
                  }
                } else {
                  $error_msg = 'AMSCO Download - No (valid) phone number found: '.$line[11].', for contact, contact ID: '.$contact['id'];
                  $errors[] = $error_msg;
                }
              } else {
                $error_msg = 'AMSCO Download - No (valid) phone number found: '.$line[11].', for contact, contact ID: '.$contact['id'];
                $errors[] = $error_msg;
              }
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - No (valid) phone number found: '.$line[11].', for contact, contact ID: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }
            try {
              if(!empty($line[12])){
                $website = civicrm_api('Website', 'create', array('version' => 3, 'sequential' => 1, 'contact_id' => $contact['id'], 'url' => $line[12], 'website_type_id' => $location_type_work['id']));
              }
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to create website for contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
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
                $error_msg = 'AMSCO Download - Unable to create authorised contact for contact ID: '.$contact['id'].', first_name or last_name is empty';
                $errors[] = $error_msg;
              }

              if(!empty($result_authorised_contact['id'])) {
                if (!empty($line[17]) && strpos($line[17], '@') !== FALSE) {
                  //Create e-mail for authorised contact
                  $params_email_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'email' => $line[17]);
                  $email_authorised_contact = civicrm_api('Email', 'create', $params_email_authorised_contact);
                } else {
                  if(!empty($line[17])){
                    $error_msg = 'AMSCO Download - Invalid e-mailaddress for authorised contact, contact ID: '.$contact['id'].', email: '.$line[17];
                    $errors[] = $error_msg;
                  } else {
                    $error_msg = 'AMSCO Download - No e-mailaddress found for authorised contact, contact ID: '.$contact['id'].', email: '.$line[17];
                    $errors[] = $error_msg;
                  }
                }

                //Create phone for authorised contact
                if (preg_match("/^((\+[1-9]{1,4}[ \-]*)|(\([0-9]{2,3}\)[ \-]*)|([0-9]{2,4})[ \-]*)*?[0-9]{2,4}?[ \-]*[0-9]{2,4}?$/",$line[18])){
                  $params_phone_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id' => $result_authorised_contact['id'], 'location_type_id' => $location_type_work['id'], 'phone' => $line[18], 'phone_type_id' => $phone_type_id['value']);
                  $phone_authorised_contact = civicrm_api('Phone', 'create', $params_phone_authorised_contact);

                  if($phone_authorised_contact['is_error'] == 1){
                    $error_msg = 'AMSCO Download - Unable to create phone number for authorised contact, contact ID: '.$result_authorised_contact['id'].', phone: '.$line[18];
                    $errors[] = $error_msg;
                  }
                } else {
                  $error_msg = 'AMSCO Download - No (valid) phone number found: '.$line[18].', for contact, contact ID: '.$result_authorised_contact['id'];
                  $errors[] = $error_msg;
                }

                //Create relationship between authorised contact and organisation
                $relationship_type_authorised_contact = civicrm_api('RelationshipType', 'getsingle', array('version' => 3, 'sequential' => 1, 'name_a_b' => 'Has authorised'));
                $params_create_relation_authorised_contact = array('version' => 3, 'sequential' => 1, 'contact_id_a' => $contact['id'], 'contact_id_b' => $result_authorised_contact['id'],'relationship_type_id' => $relationship_type_authorised_contact['id']);
                $create_relation_authorised_contact = civicrm_api('Relationship', 'create', $params_create_relation_authorised_contact);
              }
            } catch (Exception $e){
              $error_msg = 'AMSCO Download - Unable to create authorised contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
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

                if(!empty($sql_params[1]) && !empty($sql_params[2]) && !empty($sql_params[3]) && !empty($sql_params[4]) && !empty($sql_params[5])){
                  CRM_Core_DAO::executeQuery($sql, $sql_params);
                }
              } else {
                $sql_params[5] = array($conditions, 'String');
                if($conditions == 'I Agree') {
                  $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`,`".$ad_custom_fields['Gentlemen_s_Agreement']."`) VALUES (".$contact['id'].", %1, %2, NULL, %4, %5)";
                } else {
                  $sql = "INSERT INTO `".$cg_additional_data['table_name']."` (`entity_id`, `".$ad_custom_fields['Products_and_or_Services_offered']."`,`".$ad_custom_fields['Where_and_how_are_the_products_of_services_sold']."`,`".$ad_custom_fields['gender_entrepeneur']."`,`".$ad_custom_fields['birthyear_entrepeneur']."`,`".$ad_custom_fields['Gentlemen_s_Agreement']."`) VALUES (".$contact['id'].", %1, %2, NULL, %4, NULL)";
                }
                if(!empty($sql_params[1]) && !empty($sql_params[2]) && !empty($sql_params[4])){
                  CRM_Core_DAO::executeQuery($sql, $sql_params);
                }
              }


            } catch (Exception $e){
              $error_msg = 'AMSCO Download - Unable to create additional data for contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            //Create project
            $project_name = $line[6];
            $reason = $line[19];
            $work_description = $line[20];
            $expected_results = $line[21];
            //$projectplan = $line[16];
            //$projectplan2 = $line[17];
            $project_start_date = date('YmdHis');

            try {
              if(!empty($project_name)){
                $project = civicrm_api('PumProject', 'create', array('version' => 3, 'sequential' => 1, 'title' => 'Project '.$project_name, 'customer_id' => $contact['id'], 'is_active' => 1, 'start_date' => $project_start_date, 'reason' => utf8_encode($reason), 'work_description' => utf8_encode($work_description),'expected_results' => utf8_encode($expected_results)));//, 'projectplan' => utf8_encode($projectplan).' '.utf8_encode($projectplan2)));
              } else {
                $error_msg = 'AMSCO Download - Unable to create project for contact: '.$contact['id'].', project_name is empty';
                $errors[] = $error_msg;
              }
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to project for contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            //Create yearly information
            try {
              $cg_yearly_information = civicrm_api('CustomGroup', 'getsingle', array('version' => 3, 'sequential' => 1, 'name' => 'Employee_Information'));
              $cf_yearly_information = civicrm_api('CustomField', 'get', array('version' => 3, 'sequential' => 1, 'custom_group_id' => 'Employee_Information'));
              $yi_custom_fields = array();

              if(is_array($cf_yearly_information['values']) && !empty($cg_yearly_information['table_name'])){
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

                if( is_int((int)$line[22]) &&
                    is_int((int)$line[23]) &&
                    is_int((int)$line[24]) &&
                    is_int((int)$line[25]) &&
                    is_int((int)$line[26])) {
                  CRM_Core_DAO::executeQuery($sql_yearly_information,
                    array(
                      1 => array((int)$line[22],'Integer'), //year
                      2 => array((int)$line[23],'Integer'), //permanent employees
                      3 => array((int)$line[24],'Integer'), //non-permanent employees
                      4 => array((int)$line[25],'Integer'), //turnover
                      5 => array((int)$line[26],'Integer'), //balance sheet total
                    )
                  );
                } else {
                  $error_msg = 'AMSCO Download - Unable to create yearly information for year: '.$line[22].', for contact: '.$contact['id'].', one of the fields are empty.';
                  $errors[] = $error_msg;
                }

                if( is_int((int)$line[27]) &&
                    is_int((int)$line[28]) &&
                    is_int((int)$line[29]) &&
                    is_int((int)$line[30]) &&
                    is_int((int)$line[31])) {
                  CRM_Core_DAO::executeQuery($sql_yearly_information,
                    array(
                      1 => array((int)$line[27],'Integer'), //year
                      2 => array((int)$line[28],'Integer'), //permanent employees
                      3 => array((int)$line[29],'Integer'), //non-permanent employees
                      4 => array((int)$line[30],'Integer'), //turnover
                      5 => array((int)$line[31],'Integer'), //balance sheet total
                    )
                  );
                } else {
                  $error_msg = 'AMSCO Download - Unable to create yearly information for year: '.$line[27].', for contact: '.$contact['id'].', one of the fields are empty.';
                  $errors[] = $error_msg;
                }

                if( is_int((int)$line[32]) &&
                    is_int((int)$line[33]) &&
                    is_int((int)$line[34]) &&
                    is_int((int)$line[35]) &&
                    is_int((int)$line[36])) {
                  CRM_Core_DAO::executeQuery($sql_yearly_information,
                    array(
                      1 => array((int)$line[32],'Integer'), //year
                      2 => array((int)$line[33],'Integer'), //permanent employees
                      3 => array((int)$line[34],'Integer'), //non-permanent employees
                      4 => array((int)$line[35],'Integer'), //turnover
                      5 => array((int)$line[36],'Integer'), //balance sheet total
                    )
                  );
                } else {
                  $error_msg = 'AMSCO Download - Unable to create yearly information for year: '.$line[32].', for contact: '.$contact['id'].', one of the fields are empty.';
                  $errors[] = $error_msg;
                }
              }
            } catch (Exception $e){
              $error_msg = 'AMSCO Download - Unable to create yearly information for contact: '.$contact['id'];
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            //Create Prof & CC Relationship on contact card
            //first get country contact
            try {
              $country_contact = civicrm_api('Contact', 'getsingle', array(
                'version' => 3,
                'sequential' => 1,
                'contact_type' => 'Organization',
                'contact_sub_type' => 'Country',
                'organization_name' => $country['name']
              ));
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to get country contact for: '.$contact['id'].' (Country Name: '.$country['name'].')';
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            try {
              $project_officer_id = self::getProjectOfficerForCountry($country_contact['id']);
              $project_officer = civicrm_api('Contact', 'getsingle', array('version' => 3, 'sequential' => 1, 'id' => $project_officer_id));
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to get project officer for contact: '.$contact['id'].' (Project Officer ID: '.$project_officer_id.')';
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            try {
              $country_coordinator_id = self::getCountryCoordinatorForCountry($country_contact['id']);
              $country_coordinator = civicrm_api('Contact', 'getsingle', array('version' => 3, 'sequential' => 1, 'id' => $country_coordinator_id));
            } catch (CiviCRM_API3_Exception $e){
              $error_msg = 'AMSCO Download - Unable to get country coordinator for contact: '.$contact['id'].' (Country Coordinator ID: '.$country_coordinator_id.')';
              CRM_Core_Error::debug_log_message($e->getMessage());
              $errors[] = $error_msg;
            }

            $projectOfficerRelationshipTypeId = self::getProjectOfficerRelationshipTypeId();
            $countryCoordinatorRelationshipTypeId = self::getCountryCoordinatorRelationshipTypeId();

            //Create prof relationship on client contact
            if(!empty($project_officer['id'])){
              try {
                $params_create_prof_relationship = array(
                  'version' => 3,
                  'sequential' => 1,
                  'contact_id_a' => $contact['id'],
                  'contact_id_b' => $project_officer['id'],
                  'relationship_type_id' => $projectOfficerRelationshipTypeId
                );
                $create_prof_relationship = civicrm_api('Relationship', 'create', $params_create_prof_relationship);
              } catch (CiviCRM_API3_Exception $e){
                $error_msg = 'AMSCO Download - Unable to create project officer relationship for contact: '.$contact['id'].' (Project Officer ID: '.$project_officer['id'].')';
                CRM_Core_Error::debug_log_message($e->getMessage());
                $errors[] = $error_msg;
              }
            }

            //Create cc relationship on client contact
            if(!empty($country_coordinator['id'])){
              try {
                $params_create_cc_relationship = array(
                  'version' => 3,
                  'sequential' => 1,
                  'contact_id_a' => $contact['id'],
                  'contact_id_b' => $country_coordinator['id'],
                  'relationship_type_id' => $countryCoordinatorRelationshipTypeId
                );
                $create_cc_relationship = civicrm_api('Relationship', 'create', $params_create_cc_relationship);
              } catch (CiviCRM_API3_Exception $e){
                $error_msg = 'AMSCO Download - Unable to create country coordinator relationship for contact: '.$contact['id'].' (Country Coordinator ID: '.$country_coordinator['id'].')';
                CRM_Core_Error::debug_log_message($e->getMessage());
                $errors[] = $error_msg;
              }
            }

            //Send e-mails
            global $base_url;
            $domain = civicrm_api('Domain', 'getsingle', array('version' => 3, 'sequential' => 1));
            $mail_from = $domain['from_email'];

            //Send error messages to helpdesk
            $helpdesk_uf_contact = civicrm_api('UFMatch', 'getsingle', array('version' => 3, 'sequential' => 1, 'uf_id' => 1));
            if(!empty($helpdesk_uf_contact['contact_id'])) {
              $params_helpdesk_contact = array(
                'version' => 3,
                'sequential' => 1,
                'id' => $helpdesk_uf_contact['contact_id'],
              );
              $helpdesk_contact = civicrm_api('Contact', 'getsingle', $params_helpdesk_contact);
            }

            if(count($errors) > 0){
              if(!empty($helpdesk_contact['email'])){

                $mail_to_helpdesk = $helpdesk_contact['email'];
                $mail_subject_helpdesk = "Some errors occurred while importing AMSCO Applications";
                $nl = "\r\n";
                $mail_headers_helpdesk = 'MIME-Version: 1.0' . $nl;
                $mail_headers_helpdesk .= 'Content-type: text/html; charset=iso-8859-1' . $nl;
                $mail_headers_helpdesk .= 'From: ' . $mail_from . $nl;
                $mail_headers_helpdesk .= 'Reply-To: ' . $mail_from . $nl;

                $mail_message_helpdesk = '<html>';
                $mail_message_helpdesk .= '<head>';
                $mail_message_helpdesk .= '<title>';
                $mail_message_helpdesk .= $mail_subject;
                $mail_message_helpdesk .= '</title>';
                $mail_message_helpdesk .= '</head>';
                $mail_message_helpdesk .= '<body>';
                $mail_message_helpdesk .= 'The following errors occurred while importing AMSCO Applications: <br /><br />';

                foreach($errors as $error_msg){
                  //Put to log
                  CRM_Core_Error::debug_log_message($error_msg);
                  //and mail e-mail to helpdesk
                  $mail_message_helpdesk .= $error_msg.'<br />';
                }

                $mail_message_helpdesk .= '<br />';
                $mail_message_helpdesk .= 'Kind regards,<br />';
                $mail_message_helpdesk .= '<br />';
                $mail_message_helpdesk .= 'PUM Netherlands senior experts';
                $mail_message_helpdesk .= '</body>';
                $mail_message_helpdesk .= '</html>';

                $mail_sent_helpdesk = mail($mail_to_helpdesk, $mail_subject_helpdesk, $mail_message_helpdesk, $mail_headers_helpdesk);

                if($mail_sent_helpdesk == FALSE){
                  CRM_Core_Error::debug_log_message('Unable to send error message e-mail to: '.$mail_to_helpdesk.', please check log instead');
                }
              }
            }

            //Send e-mail notification about new application to Prof & CC
            $email_contacts = array($project_officer);

            if (is_array($email_contacts) && count($email_contacts) > 0){
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