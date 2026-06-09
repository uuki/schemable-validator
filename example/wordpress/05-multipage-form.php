<?php
/**
 * WordPress example: Multi-page form (input → confirm → complete)
 *
 * Create three pages with slugs: my-form-input, my-form-confirm, my-form-complete
 * Add shortcodes [my_form_input], [my_form_confirm], [my_form_complete] to each page.
 */

use Respect\Validation\Validator as v;

// ── Schema ────────────────────────────────────────────────────────────────────

function my_form_schema(): array {
  return [
    'name'  => v::stringType()->length(1, 50),
    'email' => v::email(),
    'body'  => v::stringType()->length(1, 1000),
  ];
}

// ── Step 1: Input ─────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['my_form_step'] ?? '') !== 'input') {
    return;
  }

  $validator = schv_validator(my_form_schema());
  $result    = $validator->validate($_POST)->getResult();
  $all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

  if ($all_valid) {
    schv_form()->save($result);
    wp_redirect(home_url('/my-form-confirm/'));
    exit;
  }

  global $my_form_input_result;
  $my_form_input_result = $result;
});

add_shortcode('my_form_input', function (): string {
  global $my_form_input_result;
  $r = $my_form_input_result ?? [];
  ob_start(); ?>
  <form method="post">
    <input type="hidden" name="my_form_step" value="input">
    <?php foreach (['name', 'email'] as $f): ?>
      <p>
        <label><?php echo esc_html($f); ?></label>
        <input type="text" name="<?php echo esc_attr($f); ?>" value="<?php echo esc_attr($r[$f]['value'] ?? ''); ?>">
        <?php if (!empty($r[$f]['errors'])): ?><span><?php echo esc_html($r[$f]['errors']); ?></span><?php endif; ?>
      </p>
    <?php endforeach; ?>
    <p>
      <label>body</label>
      <textarea name="body"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea>
      <?php if (!empty($r['body']['errors'])): ?><span><?php echo esc_html($r['body']['errors']); ?></span><?php endif; ?>
    </p>
    <button type="submit">Next →</button>
  </form>
  <?php return ob_get_clean();
});

// ── Step 2: Confirm ───────────────────────────────────────────────────────────

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['my_form_step'] ?? '') !== 'confirm') {
    return;
  }
  wp_redirect(home_url('/my-form-complete/'));
  exit;
});

add_shortcode('my_form_confirm', function (): string {
  $data = schv_form()->get();
  if (!$data) {
    return '<p>No data. Please <a href="' . esc_url(home_url('/my-form-input/')) . '">start over</a>.</p>';
  }
  ob_start(); ?>
  <dl>
    <?php foreach ($data as $field => $state): ?>
      <dt><?php echo esc_html($field); ?></dt>
      <dd><?php echo esc_html($state['value']); ?></dd>
    <?php endforeach; ?>
  </dl>
  <form method="post">
    <input type="hidden" name="my_form_step" value="confirm">
    <a href="<?php echo esc_url(home_url('/my-form-input/')); ?>">← Back</a>
    <button type="submit">Submit</button>
  </form>
  <?php return ob_get_clean();
});

// ── Step 3: Complete ──────────────────────────────────────────────────────────

add_shortcode('my_form_complete', function (): string {
  schv_form()->clear();
  return '<p>Thank you! Your message has been received.</p>';
});
