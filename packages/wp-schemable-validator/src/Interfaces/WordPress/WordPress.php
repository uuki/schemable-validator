<?php
namespace SchemableValidator\Interfaces;

require_once __DIR__ . '/../AbstractInterface.php';

final class WordPress extends AbstractInterface {

  function __construct($options = []) {
    $templates = [];
    foreach ($options as $key => $value) {
      $templates[$key] = get_option($value);
    }
    parent::__construct($templates);
  }
}