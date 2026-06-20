<?php
/**
 * Example: Merge GUI-defined schema with code-defined logic.
 *
 * Prerequisites:
 *   1. Create a schema "merge-demo" in Schema Editor with fields:
 *      - name  (string, required, min 1, max 100)
 *      - email (string, required, format email)
 *      - type  (enum: personal / company, required)
 *   2. This example adds a conditional requirement (company_name) and
 *      a custom validator that the GUI cannot express.
 */

use SchemableValidator\SV;

add_filter('request', function ($qv) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['schv_action'] ?? '') === 'merge-schema') {
    unset($qv['name']);
  }
  return $qv;
});

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'merge-schema') {
    return;
  }
  if (!schv_csrf()->checkToken($_POST['schv_csrf_token'] ?? '', 'merge-schema')) {
    $GLOBALS['schv_ex_merge'] = ['_error' => __('Invalid or expired CSRF token.', 'schemable-validator')];
    return;
  }

  $gui = schv_stored_schema('merge-demo')->toJsonSchema();

  $schema = SV::object([
    'company_name' => SV::string()->min(1)->max(200)->optional(),
  ])->mergeJsonSchema($gui)
    ->when('type', SV::equal('company'), ['company_name']);

  $GLOBALS['schv_ex_merge'] = $schema->toValidator()->validate($_POST)->getResult();
});

add_shortcode('schv_example_merge_schema', function (): string {
  $r     = $GLOBALS['schv_ex_merge'] ?? [];
  $token = schv_csrf()->createToken('merge-schema');

  $gui      = schv_stored_schema('merge-demo')->toJsonSchema();
  $hasGui   = !empty($gui['properties']) && !($gui['properties'] instanceof \stdClass);

  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:600px">
    <h2><?php echo esc_html__('Example: Merge Schema', 'schemable-validator'); ?></h2>
    <p style="font-size:.85em;color:#666">
      <?php echo esc_html__('GUI-defined fields (name, email, type) are merged with code-defined logic (conditional company_name).', 'schemable-validator'); ?>
    </p>

    <?php if (!$hasGui): ?>
      <div style="padding:.75rem;background:#fff3cd;border-left:3px solid #ffc107;margin-bottom:1rem">
        <strong><?php echo esc_html__('Setup required:', 'schemable-validator'); ?></strong>
        <?php echo esc_html__('Create a schema "merge-demo" in the Schema Editor with fields: name (string, required), email (string, email, required), type (enum: personal/company, required).', 'schemable-validator'); ?>
      </div>
    <?php endif; ?>

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
      <input type="hidden" name="schv_action" value="merge-schema">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">

      <p><label>name<br>
        <input type="text" name="name" value="<?php echo esc_attr($r['name']['value'] ?? ''); ?>" style="width:100%">
      </label></p>

      <p><label>email<br>
        <input type="email" name="email" value="<?php echo esc_attr($r['email']['value'] ?? ''); ?>" style="width:100%">
      </label></p>

      <p><label>type<br>
        <select name="type" style="width:100%">
          <option value="">— select —</option>
          <option value="personal" <?php selected($r['type']['value'] ?? '', 'personal'); ?>>Personal</option>
          <option value="company" <?php selected($r['type']['value'] ?? '', 'company'); ?>>Company</option>
        </select>
      </label></p>

      <p><label>company_name <span style="font-size:.8em;color:#666">(<?php echo esc_html__('required when type = company', 'schemable-validator'); ?>)</span><br>
        <input type="text" name="company_name" value="<?php echo esc_attr($r['company_name']['value'] ?? ''); ?>" style="width:100%">
      </label></p>

      <button type="submit"><?php echo esc_html__('Validate', 'schemable-validator'); ?></button>
    </form>

    <?php if ($hasGui): ?>
      <details style="margin-top:1rem">
        <summary style="cursor:pointer;font-weight:600"><?php echo esc_html__('Stored JSON Schema (merge-demo)', 'schemable-validator'); ?></summary>
        <pre style="background:#f5f5f5;padding:1rem;overflow:auto;margin-top:.5rem;font-size:.8em"><?php
          echo esc_html(json_encode($gui, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        ?></pre>
      </details>
    <?php endif; ?>
  </div>
  <?php return ob_get_clean();
});
