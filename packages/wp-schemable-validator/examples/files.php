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
  $r     = $GLOBALS['schv_ex_files'] ?? [];
  $state = $r['attachment'][0] ?? null;
  $err   = ($state && !$state['is_valid']) ? $state['errors'] : '';

  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Example: File Validation</h2>
    <p class="schv-desc">Accepts JPEG and PNG only. Max 5 MB.</p>

    <?php if ($state && $state['is_valid']): ?>
      <div class="schv-success">✓ File is valid.</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="schv_action" value="files">

      <div class="schv-field">
        <label class="schv-label" for="schv-attachment">
          Attachment<span class="schv-req" aria-hidden="true">*</span>
          <span class="schv-hint">— JPEG / PNG</span>
        </label>
        <input type="file" id="schv-attachment" name="attachment"
          class="schv-input<?php echo $err ? ' is-error' : ''; ?>" accept="image/jpeg,image/png">
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">Upload</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});
