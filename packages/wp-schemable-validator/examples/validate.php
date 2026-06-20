<?php
use SchemableValidator\SV;

// WordPress uses $_REQUEST to build query vars, so a POST body with name=Alice
// would route to "find post with slug Alice" and return 404. Strip 'name' from
// query vars when we're handling a validate POST so WP routes by URL instead.
add_filter('request', function ($qv) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['schv_action'] ?? '') === 'validate') {
    unset($qv['name']);
  }
  return $qv;
});

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'validate') {
    return;
  }
  if (!schv_csrf()->checkToken($_POST['schv_csrf_token'] ?? '', 'validate')) {
    $GLOBALS['schv_ex_validate'] = ['_error' => 'Invalid or expired CSRF token.'];
    return;
  }
  $schema = SV::object([
    'name'  => SV::string()->min(1)->max(50),
    'email' => SV::string()->email(),
    'type'  => SV::enum(['general', 'support', 'sales', 'other']),
    'body'  => SV::string()->min(1)->max(1000),
  ]);
  $GLOBALS['schv_ex_validate'] = $schema->toValidator()->validate($_POST)->getResult();
});

add_shortcode('schv_example_validate', function (): string {
  $r     = $GLOBALS['schv_ex_validate'] ?? [];
  $token = schv_csrf()->createToken('validate');

  $all_valid = !empty($r) && empty($r['_error'])
    && array_reduce($r, fn($c, $s) => $c && $s['is_valid'], true);

  $type_options = [
    'general' => 'General Inquiry',
    'support' => 'Support',
    'sales'   => 'Sales',
    'other'   => 'Other',
  ];

  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Example: Validate</h2>
    <p class="schv-legend"><span class="schv-req" aria-hidden="true">*</span> Required</p>

    <?php if (!empty($r['_error'])): ?>
      <div class="schv-global-error"><?php echo esc_html($r['_error']); ?></div>
    <?php elseif ($all_valid): ?>
      <div class="schv-success">✓ All fields are valid.</div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="validate">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">

      <?php foreach (['name', 'email'] as $f):
        $err = (isset($r[$f]) && !$r[$f]['is_valid']) ? $r[$f]['errors'] : ''; ?>
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

      <?php $err = (isset($r['type']) && !$r['type']['is_valid']) ? $r['type']['errors'] : ''; ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-type">Type<span class="schv-req" aria-hidden="true">*</span></label>
        <select id="schv-type" name="type" class="schv-select<?php echo $err ? ' is-error' : ''; ?>">
          <option value="">— select —</option>
          <?php foreach ($type_options as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>"<?php selected($r['type']['value'] ?? '', $val); ?>>
              <?php echo esc_html($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <?php $err = (isset($r['body']) && !$r['body']['is_valid']) ? $r['body']['errors'] : ''; ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-body">Body<span class="schv-req" aria-hidden="true">*</span></label>
        <textarea id="schv-body" name="body" rows="4"
          class="schv-textarea<?php echo $err ? ' is-error' : ''; ?>"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea>
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">Validate</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});
