<?php

use CRM_Amsco_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Amsco_Form_ImportAmscoApplication extends CRM_Core_Form {

  function preProcess() {
    CRM_Utils_System::setTitle('Import Amsco Application');
    parent::preProcess();
  }

  public function buildQuickForm() {

    // add form elements
    $this->add(
      'file', // field type
      'file', // field name
      'File', // field label
      '',
      TRUE // is required
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $runTime = time();

    //move uploaded import file
    $fileName = 'amsco_' . date("Ymd_His", $runTime) . '.csv';
    $fullFileName = variable_get('file_private_path', conf_path() . '/files/private').'/'.$fileName;
    foreach($_FILES as $value=>$file) {
      move_uploaded_file($_FILES[$value]['tmp_name'], $fullFileName);
      copy($fullFileName, '/var/OneDriveAmsco/processed/'.$fileName);
    }

    CRM_Amsco_Utils::ImportData($fullFileName);

    CRM_Utils_System::setUFMessage('Import successful');

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
