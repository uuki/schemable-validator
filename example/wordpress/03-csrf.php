<?php
/**
 * WordPress example: CSRF token protection
 */

use Respect\Validation\Validator as v;

function my_handle_csrf_form(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['my_csrf_submit'])) {
    return;
  }

  $validator = schv_validator(['message' => v::stringType()->notEmpty()]);

  if (!$validator->checkToken($_POST['csrf_token'] ?? '')) {
    wp_die('Invalid CSRF token.', 'Security Error', ['response' => 403]);
  }

  $result = $validator->validate($_POST)->getResult();

  global $my_csrf_result;
  $my_csrf_result = $result;
}
add_action('template_redirect', 'my_handle_csrf_form');

function my_render_csrf_form(): string {
  global $my_csrf_result;
  $validator = schv_validator([]);
  $token     = $validator->createToken();
  ob_start();
  ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo esc_attr($token); ?>">
    <div>
      <label>Message</label>
      <input type="text" name="message" value="<?php echo esc_attr($my_csrf_result['message']['value'] ?? ''); ?>">
      <?php if (!empty($my_csrf_result['message']['errors'])): ?>
        <p class="error"><?php echo esc_html($my_csrf_result['message']['errors']); ?></p>
      <?php endif; ?>
    </div>
    <button type="submit" name="my_csrf_submit">Submit</button>
  </form>
  <?php
  return ob_get_clean();
}
