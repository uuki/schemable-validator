<?php
use SchemableValidator\SV;

function schv_multipage_schema() {
  return SV::object([
    'name'  => SV::string()->min(1)->max(50),
    'email' => SV::string()->email(),
    'body'  => SV::string()->min(1)->max(1000),
  ]);
}

// ── Step 1: Input ──────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'form_input') {
    return;
  }
  if (!schv_csrf()->checkToken($_POST['schv_csrf_token'] ?? '', 'form_input')) {
    $GLOBALS['schv_ex_input_error'] = 'Invalid or expired CSRF token.';
    return;
  }
  $validator = schv_multipage_schema()->toValidator();
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
  $token = schv_csrf()->createToken('form_input');

  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Step 1: Input</h2>
    <p class="schv-legend"><span class="schv-req" aria-hidden="true">*</span> Required</p>

    <?php if (!empty($GLOBALS['schv_ex_input_error'])): ?>
      <div class="schv-global-error"><?php echo esc_html($GLOBALS['schv_ex_input_error']); ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="form_input">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">

      <?php foreach (['name', 'email'] as $f):
        $err = (isset($r[$f]) && !$r[$f]['is_valid']) ? $r[$f]['errors'] : '';
      ?>
        <div class="schv-field">
          <label class="schv-label" for="schv-<?php echo esc_attr($f); ?>">
            <?php echo esc_html(ucfirst($f)); ?><span class="schv-req" aria-hidden="true">*</span>
          </label>
          <input type="text" id="schv-<?php echo esc_attr($f); ?>" name="<?php echo esc_attr($f); ?>"
            value="<?php echo esc_attr($r[$f]['value'] ?? ''); ?>"
            class="schv-input<?php echo $err ? ' is-error' : ''; ?>">
          <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
        </div>
      <?php endforeach; ?>

      <?php $err = (isset($r['body']) && !$r['body']['is_valid']) ? $r['body']['errors'] : ''; ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-body">
          Body<span class="schv-req" aria-hidden="true">*</span>
        </label>
        <textarea id="schv-body" name="body" rows="4"
          class="schv-textarea<?php echo $err ? ' is-error' : ''; ?>"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea>
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">Next →</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});

// ── Step 2: Confirm ────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'form_confirm') {
    return;
  }
  if (!schv_csrf()->checkToken($_POST['schv_csrf_token'] ?? '', 'form_confirm')) {
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
  <div class="schv-wrap">
    <h2>Step 2: Confirm</h2>

    <?php if (!empty($GLOBALS['schv_ex_confirm_error'])): ?>
      <div class="schv-global-error"><?php echo esc_html($GLOBALS['schv_ex_confirm_error']); ?></div>
    <?php endif; ?>

    <dl class="schv-dl" style="margin:0 0 1.25rem">
      <?php foreach ($data as $field => $state): ?>
        <dt><?php echo esc_html($field); ?></dt>
        <dd><?php echo esc_html($state['value']); ?></dd>
      <?php endforeach; ?>
    </dl>

    <form method="post">
      <input type="hidden" name="schv_action" value="form_confirm">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr(schv_csrf()->createToken('form_confirm')); ?>">
      <div class="schv-actions">
        <a href="<?php echo esc_url(home_url('/schv-form-input/')); ?>" class="schv-back">← Back</a>
        <button type="submit" class="schv-btn">Submit</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});

// ── Step 3: Complete ───────────────────────────────────────────────────────────

add_shortcode('schv_example_form_complete', function (): string {
  schv_form()->clear();
  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Step 3: Complete</h2>
    <div class="schv-success">✓ Thank you! Your message has been received.</div>
    <a href="<?php echo esc_url(home_url('/schv-form-input/')); ?>" class="schv-back">Try again</a>
  </div>
  <?php return ob_get_clean();
});
