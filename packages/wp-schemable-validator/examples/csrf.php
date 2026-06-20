<?php
use SchemableValidator\SV;

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'csrf') {
    return;
  }
  if (!schv_csrf()->checkToken($_POST['schv_csrf_token'] ?? '')) {
    $GLOBALS['schv_ex_csrf'] = ['error' => 'Invalid CSRF token.'];
    return;
  }
  $schema = SV::object(['message' => SV::string()->min(1)]);
  $GLOBALS['schv_ex_csrf'] = $schema->toValidator()->validate($_POST)->getResult();
});

add_shortcode('schv_example_csrf', function (): string {
  $r     = $GLOBALS['schv_ex_csrf'] ?? [];
  $token = schv_csrf()->createToken();

  $submitted = !empty($r) && empty($r['error']);
  $err       = ($submitted && !$r['message']['is_valid']) ? $r['message']['errors'] : '';
  $success   = $submitted && $r['message']['is_valid'];

  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Example: CSRF Token</h2>
    <p class="schv-desc">Token: <code><?php echo esc_html($token); ?></code></p>

    <?php if (!empty($r['error'])): ?>
      <div class="schv-global-error"><?php echo esc_html($r['error']); ?></div>
    <?php elseif ($success): ?>
      <div class="schv-success">✓ CSRF token verified. Message is valid.</div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="csrf">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">

      <div class="schv-field">
        <label class="schv-label" for="schv-message">Message<span class="schv-req" aria-hidden="true">*</span></label>
        <input type="text" id="schv-message" name="message"
          value="<?php echo esc_attr($r['message']['value'] ?? ''); ?>"
          class="schv-input<?php echo $err ? ' is-error' : ''; ?>">
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">Submit</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});
