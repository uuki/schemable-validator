<?php
add_shortcode('schv_example_template', function (): string {
  // Simulate validated session data for preview
  $form = schv_form();
  $data = $form->get() ?: [
    'name'  => ['value' => 'Alice',              'is_valid' => true, 'errors' => null],
    'email' => ['value' => 'alice@example.com',  'is_valid' => true, 'errors' => null],
    'body'  => ['value' => 'Hello, I have a question.', 'is_valid' => true, 'errors' => null],
  ];
  $form->save($data);

  $template = schv_template([
    'aliases'   => ['name' => 'name', 'email' => 'email', 'body' => 'body'],
    'templates' => ['user' => 'SCHV_REPLY_FORMAT_FOR_user', 'admin' => 'SCHV_REPLY_FORMAT_FOR_admin'],
  ]);

  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:600px">
    <h2>Example: Template</h2>
    <p style="font-size:0.85em;color:#666">
      Edit templates at <strong>WP Admin › Settings › Schemable Validator</strong>.
    </p>
    <h3>User email</h3>
    <pre style="background:#f5f5f5;padding:12px"><?php echo esc_html($template->get('user')); ?></pre>
    <h3>Admin email</h3>
    <pre style="background:#f5f5f5;padding:12px"><?php echo esc_html($template->get('admin')); ?></pre>
  </div>
  <?php return ob_get_clean();
});
