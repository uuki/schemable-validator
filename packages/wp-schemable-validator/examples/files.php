<?php
use SchemableValidator\SV;

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'files') {
    return;
  }
  $schema = SV::object([
    'attachment' => SV::file(['image/jpeg', 'image/png']),
  ]);
  $GLOBALS['schv_ex_files'] = $schema->toValidator()->validateFiles($_FILES)->getResult();
});

add_shortcode('schv_example_files', function (): string {
  $r = $GLOBALS['schv_ex_files'] ?? [];
  $state = $r['attachment'][0] ?? null;
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Example: File Validation</h2>
    <?php if ($state): ?>
      <p style="color:<?php echo $state['is_valid'] ? 'green' : 'red'; ?>">
        attachment: <?php echo $state['is_valid'] ? '✓ valid' : esc_html($state['errors']); ?>
      </p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="schv_action" value="files">
      <p>
        <label>Attachment (jpg / png)<br>
        <input type="file" name="attachment"></label>
      </p>
      <button type="submit">Upload</button>
    </form>
  </div>
  <?php return ob_get_clean();
});
