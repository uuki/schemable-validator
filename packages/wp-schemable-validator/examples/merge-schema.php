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

  $gui    = schv_stored_schema('merge-demo')->toJsonSchema();
  $hasGui = !empty($gui['properties']) && !($gui['properties'] instanceof \stdClass);

  $all_valid = !empty($r) && empty($r['_error'])
    && array_reduce($r, fn($c, $s) => $c && $s['is_valid'], true);

  ob_start(); ?>
  <div class="schv-wrap">
    <h2><?php echo esc_html__('Example: Merge Schema', 'schemable-validator'); ?></h2>
    <p class="schv-desc">
      <?php echo esc_html__('GUI-defined fields (name, email, type) are merged with code-defined logic (conditional company_name).', 'schemable-validator'); ?>
    </p>
    <p class="schv-legend"><span class="schv-req" aria-hidden="true">*</span> Required</p>

    <?php if (!$hasGui): ?>
      <div class="schv-notice">
        <strong><?php echo esc_html__('Setup required:', 'schemable-validator'); ?></strong>
        <?php echo esc_html__('Create a schema "merge-demo" in the Schema Editor with fields: name (string, required), email (string, email, required), type (enum: personal/company, required).', 'schemable-validator'); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($r['_error'])): ?>
      <div class="schv-global-error"><?php echo esc_html($r['_error']); ?></div>
    <?php elseif ($all_valid): ?>
      <div class="schv-success">✓ All fields are valid.</div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="merge-schema">
      <input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">

      <?php
      // type is first so the conditional company_name field below makes immediate sense
      $err = (isset($r['type']) && !$r['type']['is_valid']) ? $r['type']['errors'] : '';
      ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-type">
          <?php echo esc_html__('type', 'schemable-validator'); ?><span class="schv-req" aria-hidden="true">*</span>
        </label>
        <select id="schv-type" name="type" class="schv-select<?php echo $err ? ' is-error' : ''; ?>">
          <option value="">— select —</option>
          <option value="personal"<?php selected($r['type']['value'] ?? '', 'personal'); ?>>Personal</option>
          <option value="company"<?php selected($r['type']['value'] ?? '', 'company'); ?>>Company</option>
        </select>
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <?php foreach (['name' => 'text', 'email' => 'email'] as $key => $inputType):
        $err = (isset($r[$key]) && !$r[$key]['is_valid']) ? $r[$key]['errors'] : '';
      ?>
        <div class="schv-field">
          <label class="schv-label" for="schv-<?php echo esc_attr($key); ?>">
            <?php echo esc_html($key); ?><span class="schv-req" aria-hidden="true">*</span>
          </label>
          <input type="<?php echo esc_attr($inputType); ?>"
            id="schv-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>"
            value="<?php echo esc_attr($r[$key]['value'] ?? ''); ?>"
            class="schv-input<?php echo $err ? ' is-error' : ''; ?>">
          <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
        </div>
      <?php endforeach; ?>

      <?php $err = (isset($r['company_name']) && !$r['company_name']['is_valid']) ? $r['company_name']['errors'] : ''; ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-company-name">
          company_name
          <span class="schv-hint">— <?php echo esc_html__('required when type = company', 'schemable-validator'); ?></span>
        </label>
        <input type="text" id="schv-company-name" name="company_name"
          value="<?php echo esc_attr($r['company_name']['value'] ?? ''); ?>"
          class="schv-input<?php echo $err ? ' is-error' : ''; ?>">
        <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn"><?php echo esc_html__('Validate', 'schemable-validator'); ?></button>
      </div>
    </form>

    <?php if ($hasGui): ?>
      <details style="margin-top:1.5rem">
        <summary style="cursor:pointer;font-size:.875rem;font-weight:600;color:#374151">
          <?php echo esc_html__('Stored JSON Schema (merge-demo)', 'schemable-validator'); ?>
        </summary>
        <pre style="background:#f5f5f5;padding:.9rem;overflow:auto;margin-top:.5rem;font-size:.78rem;border-radius:4px;line-height:1.5"><?php
          echo esc_html(json_encode($gui, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        ?></pre>
      </details>
    <?php endif; ?>
  </div>
  <?php return ob_get_clean();
});
