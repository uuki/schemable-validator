<?php
$vendor_path = __DIR__ . '/../../vendor'; // packages/core → project root

if (!is_dir($vendor_path)) {
  // installed as composer package: vendor/uuki/schemable-validator/packages/core
  $vendor_path = __DIR__ . '/../../../..'; // → plugin vendor dir
}

define('SV_VENDOR_DIR', $vendor_path);
define('SV_SESSION_VALIDATED_DATA', 'sv_session_validated_data');