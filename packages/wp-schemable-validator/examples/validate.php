<?php
use Respect\Validation\Validator as v;

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'validate') {
    return;
  }
  $schema = [
    'name'  => v::stringType()->length(1, 50),
    'email' => v::email(),
    'body'  => v::stringType()->length(1, 1000),
  ];
  $GLOBALS['schv_ex_validate'] = schv_validator($schema)->validate($_POST)->getResult();
});

add_shortcode('schv_example_validate', function (): string {
  $r = $GLOBALS['schv_ex_validate'] ?? [];
  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Example: Validate</h2>
    <?php if ($r): ?>
      <?php foreach ($r as $field => $state): ?>
        <p style="color:<?php echo $state['is_valid'] ? 'green' : 'red'; ?>">
          <strong><?php echo esc_html($field); ?></strong>:
          <?php echo $state['is_valid'] ? '✓ valid' : esc_html($state['errors']); ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="schv_action" value="validate">
      <?php foreach (['name', 'email'] as $f): ?>
        <p>
          <label><?php echo esc_html($f); ?><br>
          <input type="text" name="<?php echo esc_attr($f); ?>" value="<?php echo esc_attr($r[$f]['value'] ?? ''); ?>" style="width:100%"></label>
        </p>
      <?php endforeach; ?>
      <p>
        <label>body<br>
        <textarea name="body" style="width:100%"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea></label>
      </p>
      <button type="submit">Validate</button>
    </form>
  </div>
  <?php return ob_get_clean();
});
