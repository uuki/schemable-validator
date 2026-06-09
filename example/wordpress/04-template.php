<?php
/**
 * WordPress example: Email template rendering
 *
 * Requires the Schemable Validator plugin active.
 * Template body is editable from WP Admin > Settings > Schemable Validator.
 */

function my_send_confirmation_emails(): void {
  $form = schv_form();
  $data = $form->get();

  if (!$data) {
    return;
  }

  // $option_keys comes from Plugin::keysAll() — see 02-feature-guide.md for details.
  // Here we use hardcoded keys matching the Plugin constructor defaults.
  $template = schv_template([
    'aliases' => [
      'name'  => 'name',
      'email' => 'email',
      'body'  => 'body',
    ],
    'templates' => [
      'user'  => get_option('SCHV_REPLY_FORMAT_FOR_user',  ''),
      'admin' => get_option('SCHV_REPLY_FORMAT_FOR_admin', ''),
    ],
  ]);

  $to_user  = $data['email']['value'] ?? '';
  $to_admin = get_option('admin_email');

  if ($to_user) {
    wp_mail($to_user,  'Thank you for your inquiry',  $template->get('user'));
  }
  wp_mail($to_admin, 'New inquiry received', $template->get('admin'));
}
