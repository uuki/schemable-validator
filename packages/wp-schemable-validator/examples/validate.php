<?php
use Respect\Validation\Validator as v;

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
  $validator = schv_validator([]);
  if (!$validator->checkToken($_POST['schv_csrf_token'] ?? '', 'validate')) {
    $GLOBALS['schv_ex_validate'] = ['_error' => 'Invalid or expired CSRF token.'];
    return;
  }
  $schema = [
    'name'  => v::stringType()->length(1, 50),
    'email' => v::email(),
    'type'  => v::in(['general', 'support', 'sales', 'other']),
    'body'  => v::stringType()->length(1, 1000),
  ];
  $GLOBALS['schv_ex_validate'] = schv_validator($schema)->validate($_POST)->getResult();
});

add_shortcode('schv_example_validate', function (): string {
  $r     = $GLOBALS['schv_ex_validate'] ?? [];
  $token = schv_validator([])->createToken('validate');
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Example: Validate</h2>
    <?php if (!empty($r['_error'])): ?>
      <p style="color:red"><?php echo esc_html($r['_error']); ?></p>
    <?php elseif ($r): ?>
      <?php foreach ($r as $field => $state): ?>
        <p style="color:<?php echo $state['is_valid'] ? 'green' : 'red'; ?>">
          <strong><?php echo esc_html($field); ?></strong>:
          <?php echo $state['is_valid'] ? '✓ valid' : esc_html($state['errors']); ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="validate">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">
      <?php foreach (['name', 'email'] as $f): ?>
        <p>
          <label><?php echo esc_html($f); ?><br>
          <input type="text" name="<?php echo esc_attr($f); ?>" value="<?php echo esc_attr($r[$f]['value'] ?? ''); ?>" style="width:100%"></label>
        </p>
      <?php endforeach; ?>
      <p>
        <label>type<br>
        <select name="type" style="width:100%">
          <option value="">— select —</option>
          <?php foreach (['general' => 'General Inquiry', 'support' => 'Support', 'sales' => 'Sales', 'other' => 'Other'] as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>"<?php selected($r['type']['value'] ?? '', $val); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select></label>
      </p>
      <p>
        <label>body<br>
        <textarea name="body" style="width:100%"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea></label>
      </p>
      <button type="submit">Validate</button>
    </form>
  </div>
  <?php return ob_get_clean();
});
