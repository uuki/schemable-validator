<?php
/**
 * WordPress example: Basic validation
 *
 * Usage in a theme's functions.php or a custom plugin.
 * Requires the Schemable Validator plugin to be active.
 */

use Respect\Validation\Validator as v;

// Handle form submission (call from 'template_redirect' action)
function my_handle_contact_form(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['my_contact_submit'])) {
    return;
  }

  $schema = [
    'name'  => v::stringType()->length(1, 50),
    'email' => v::email(),
    'body'  => v::stringType()->length(1, 1000),
  ];

  $validator = schv_validator($schema);
  $result    = $validator->validate($_POST)->getResult();

  $all_valid = array_reduce($result, fn($carry, $item) => $carry && $item['is_valid'], true);

  if ($all_valid) {
    schv_form()->save($result);
    wp_redirect(home_url('/contact-complete/'));
    exit;
  }

  // Store errors for display
  global $my_contact_result;
  $my_contact_result = $result;
}
add_action('template_redirect', 'my_handle_contact_form');

// Render form (use as shortcode or in template)
function my_render_contact_form(): string {
  global $my_contact_result;
  ob_start();
  ?>
  <form method="post">
    <?php foreach (['name', 'email', 'body'] as $field): ?>
      <?php $error = $my_contact_result[$field]['errors'] ?? null; ?>
      <div>
        <label><?php echo esc_html($field); ?></label>
        <?php if ($field === 'body'): ?>
          <textarea name="<?php echo esc_attr($field); ?>"><?php echo esc_textarea($my_contact_result[$field]['value'] ?? ''); ?></textarea>
        <?php else: ?>
          <input type="text" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($my_contact_result[$field]['value'] ?? ''); ?>">
        <?php endif; ?>
        <?php if ($error): ?><p class="error"><?php echo esc_html($error); ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" name="my_contact_submit">Submit</button>
  </form>
  <?php
  return ob_get_clean();
}
