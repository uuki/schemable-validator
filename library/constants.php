<?php
$vendor_path = __DIR__ . '/../vendor';

if (!is_dir($vendor_path)) {
  $vendor_path = __DIR__ . '/../../..';
}

define('SV_VENDOR_DIR', $vendor_path);
define('SV_SESSION_VALIDATED_DATA', 'sv_session_validated_data');