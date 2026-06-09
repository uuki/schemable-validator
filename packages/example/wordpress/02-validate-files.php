<?php
/**
 * WordPress example: File upload validation
 */

use Respect\Validation\Validator as v;

function my_handle_upload_form(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['my_upload_submit'])) {
    return;
  }

  $schema = [
    'attachment' => v::key('error', v::equals(UPLOAD_ERR_OK))
      ->key('name', v::oneOf(v::extension('jpg'), v::extension('png'))),
  ];

  $result = schv_validator($schema)->validateFiles($_FILES)->getResult();

  global $my_upload_result;
  $my_upload_result = $result;

  $all_valid = !empty($result['attachment']) && $result['attachment'][0]['is_valid'];
  if ($all_valid) {
    // move_uploaded_file(...) here
    wp_redirect(home_url('/upload-complete/'));
    exit;
  }
}
add_action('template_redirect', 'my_handle_upload_form');

function my_render_upload_form(): string {
  global $my_upload_result;
  $error = $my_upload_result['attachment'][0]['errors'] ?? null;
  ob_start();
  ?>
  <form method="post" enctype="multipart/form-data">
    <div>
      <label>Attachment (jpg / png)</label>
      <input type="file" name="attachment">
      <?php if ($error): ?><p class="error"><?php echo esc_html($error); ?></p><?php endif; ?>
    </div>
    <button type="submit" name="my_upload_submit">Upload</button>
  </form>
  <?php
  return ob_get_clean();
}
