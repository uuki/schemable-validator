<?php
/**
 * Example: Template rendering
 * Run: php example/core/05-template.php
 */
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

use SchemableValidator\Orchestration\Template;

// Simulate validated session data (normally saved by FormController::save())
$_SESSION['sv_session_validated_data'] = [
  'contact_name'    => ['value' => 'Alice',              'is_valid' => true, 'errors' => null],
  'contact_email'   => ['value' => 'alice@example.com',  'is_valid' => true, 'errors' => null],
  'contact_message' => ['value' => "Hello,\nI have a question.", 'is_valid' => true, 'errors' => null],
];

$template = new Template([
  'aliases' => [
    'name'  => 'contact_name',
    'email' => 'contact_email',
    'body'  => 'contact_message',
  ],
  'templates' => [
    'user'  => "Dear {name},\nThank you for your inquiry.\n\n---\n{body}\n",
    'admin' => "New inquiry from {name} <{email}>\n\n---\n{body}\n",
  ],
]);

echo "=== User email ===\n" . $template->get('user') . "\n";
echo "=== Admin email ===\n" . $template->get('admin');
