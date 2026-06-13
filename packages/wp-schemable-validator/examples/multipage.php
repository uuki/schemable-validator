<?php
use Respect\Validation\Validator as v;

function schv_multipage_schema(): array {
  return [
    'name'  => v::stringType()->length(1, 50),
    'email' => v::email(),
    'body'  => v::stringType()->length(1, 1000),
  ];
}

// ── Step 1: Input ──────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'form_input') {
    return;
  }
  $csrf = schv_validator([]);
  if (!$csrf->checkToken($_POST['schv_csrf_token'] ?? '', 'form_input')) {
    $GLOBALS['schv_ex_input_error'] = 'Invalid or expired CSRF token.';
    return;
  }
  $validator = schv_validator(schv_multipage_schema());
  $result    = $validator->validate($_POST)->getResult();
  $all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

  if ($all_valid) {
    schv_form()->save($result);
    wp_redirect(home_url('/schv-form-confirm/'));
    exit;
  }
  $GLOBALS['schv_ex_input'] = $result;
});

add_shortcode('schv_example_form_input', function (): string {
  $r     = $GLOBALS['schv_ex_input'] ?? [];
  $token = schv_validator([])->createToken('form_input');
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Step 1: Input</h2>
    <?php if (!empty($GLOBALS['schv_ex_input_error'])): ?>
      <p style="color:red"><?php echo esc_html($GLOBALS['schv_ex_input_error']); ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="schv_action" value="form_input">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">
      <?php foreach (['name', 'email'] as $f): ?>
        <p>
          <label><?php echo esc_html($f); ?><br>
          <input type="text" name="<?php echo esc_attr($f); ?>" value="<?php echo esc_attr($r[$f]['value'] ?? ''); ?>" style="width:100%"></label>
          <?php if (!empty($r[$f]['errors'])): ?><span style="color:red;font-size:.85em"><?php echo esc_html($r[$f]['errors']); ?></span><?php endif; ?>
        </p>
      <?php endforeach; ?>
      <p>
        <label>body<br>
        <textarea name="body" style="width:100%"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea></label>
        <?php if (!empty($r['body']['errors'])): ?><span style="color:red;font-size:.85em"><?php echo esc_html($r['body']['errors']); ?></span><?php endif; ?>
      </p>
      <button type="submit">Next →</button>
    </form>
  </div>
  <?php return ob_get_clean();
});

// ── Step 2: Confirm ────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'form_confirm') {
    return;
  }
  $csrf = schv_validator([]);
  if (!$csrf->checkToken($_POST['schv_csrf_token'] ?? '', 'form_confirm')) {
    $GLOBALS['schv_ex_confirm_error'] = 'Invalid or expired CSRF token.';
    return;
  }
  wp_redirect(home_url('/schv-form-complete/'));
  exit;
});

add_shortcode('schv_example_form_confirm', function (): string {
  $data = schv_form()->get();
  if (!$data) {
    return '<p>No data. <a href="' . esc_url(home_url('/schv-form-input/')) . '">Start over</a>.</p>';
  }
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Step 2: Confirm</h2>
    <dl>
      <?php foreach ($data as $field => $state): ?>
        <dt style="font-weight:bold"><?php echo esc_html($field); ?></dt>
        <dd style="margin-left:1em;white-space:pre-wrap"><?php echo esc_html($state['value']); ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php if (!empty($GLOBALS['schv_ex_confirm_error'])): ?>
      <p style="color:red"><?php echo esc_html($GLOBALS['schv_ex_confirm_error']); ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="schv_action" value="form_confirm">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr(schv_validator([])->createToken('form_confirm')); ?>">
      <a href="<?php echo esc_url(home_url('/schv-form-input/')); ?>">← Back</a>
      &nbsp;
      <button type="submit">Submit</button>
    </form>
  </div>
  <?php return ob_get_clean();
});

// ── Step 3: Complete ───────────────────────────────────────────────────────────

add_shortcode('schv_example_form_complete', function (): string {
  schv_form()->clear();
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Step 3: Complete</h2>
    <p>✓ Thank you! Your message has been received.</p>
    <a href="<?php echo esc_url(home_url('/schv-form-input/')); ?>">Try again</a>
  </div>
  <?php return ob_get_clean();
});
