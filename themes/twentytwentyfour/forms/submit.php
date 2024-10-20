<?php
require_once "schema.php";
// require_once SCHEMABLE_VALIDATOR_PATH . '/Validator.php';
// require_once SCHEMABLE_VALIDATOR_PATH . '/Controllers/FormController.php';

use SchemableValidator\Validator as Validator;
use SchemableValidator\Controllers\FormController;

global $get_schema;

$forms_submit_handler = function () use ($get_schema) {

  if (!isset($_POST[SUBMIT_FORM_ACTION])) {
    return;
  }

  $validator = null;
  $form_controller = null;
  $result = null;

  try {
    $validator = new Validator($get_schema($_POST));
    $form_controller = new FormController();
  } catch(ReflectionException $exception) {
    return;
  }

  if (!($validator instanceof Validator) || !($form_controller instanceof FormController)) {
    error_log('SchemableValidator\Validator is not defined.');
    return;
  }

  $result = $validator->validate($_POST);
  $result = $validator->validateFiles($_FILES);

  if ($result) {
    $form_controller->save($result);

    wp_redirect(home_url('/confirm/'));
  }
};