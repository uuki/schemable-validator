<?php
require_once "schema.php";

require_once get_template_directory() . '/vendor/autoload.php';

use SchemableValidator\Validator;
use SchemableValidator\Controllers\FormController;

$a = new Validator();

exit(var_dump($a));

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