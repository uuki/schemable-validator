<?php
use Respect\Validation\Validator as v;

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'csrf') {
    return;
  }
  $validator = schv_validator(['message' => v::stringType()->notEmpty()]);

  if (!$validator->checkToken($_POST['schv_csrf_token'] ?? '')) {
    $GLOBALS['schv_ex_csrf'] = ['error' => 'Invalid CSRF token.'];
    return;
  }
  $GLOBALS['schv_ex_csrf'] = $validator->validate($_POST)->getResult();
});

add_shortcode('schv_example_csrf', function (): string {
  $r     = $GLOBALS['schv_ex_csrf'] ?? [];
  $token = schv_validator([])->createToken();
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Example: CSRF Token</h2>
    <p style="font-size:0.85em;color:#666">Token: <code><?php echo esc_html($token); ?></code></p>
    <?php if (!empty($r['error'])): ?>
      <p style="color:red"><?php echo esc_html($r['error']); ?></p>
    <?php elseif (!empty($r['message'])): ?>
      <p style="color:<?php echo $r['message']['is_valid'] ? 'green' : 'red'; ?>">
        message: <?php echo $r['message']['is_valid'] ? '✓ valid' : esc_html($r['message']['errors']); ?>
      </p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="schv_action" value="csrf">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">
      <p>
        <label>Message<br>
        <input type="text" name="message" value="<?php echo esc_attr($r['message']['value'] ?? ''); ?>" style="width:100%"></label>
      </p>
      <button type="submit">Submit</button>
    </form>
  </div>
  <?php return ob_get_clean();
});
