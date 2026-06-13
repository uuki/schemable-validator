<?php
/**
 * Example: CSRF token
 * Run: php example/core/04-csrf-token.php
 */
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

use SchemableValidator\Validator;

$validator = new Validator([]);

// Step 1: Generate token (form render)
$token = $validator->createToken();
echo "Token generated: {$token}\n";
echo "Stored in session: " . ($_SESSION['schv_csrf_token'] === $token ? 'yes' : 'no') . "\n";

// Step 2: Verify with correct token (form submit)
echo "\nCorrect token: " . ($validator->checkToken($token) ? 'valid' : 'invalid') . "\n";

// Step 3: Verify with wrong token
echo "Wrong token:   " . ($validator->checkToken('tampered') ? 'valid' : 'invalid') . "\n";
