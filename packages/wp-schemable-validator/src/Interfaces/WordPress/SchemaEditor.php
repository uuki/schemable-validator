<?php

namespace SchemableValidator\Interfaces\WordPress;

/**
 * ACF-like admin page for defining validation schemas via GUI.
 *
 * Stores a JSON Schema 2020-12 object in wp_options ("schv_schema_{$slug}").
 * At runtime, pair with StoredSchemaProvider to feed the schema into
 * Validator::fromJsonSchema() or schv_register_schema().
 */
final class SchemaEditor {
  private const OPTION_PREFIX = 'schv_schema_';
  private const SLUGS_OPTION  = 'schv_schema_slugs';

  /** @return array<string, string> */
  private static function fieldTypes(): array {
    return [
      'string'  => __('String', 'schemable-validator'),
      'integer' => __('Integer', 'schemable-validator'),
      'number'  => __('Number', 'schemable-validator'),
      'boolean' => __('Boolean', 'schemable-validator'),
      'enum'    => __('Enum', 'schemable-validator'),
    ];
  }

  /** @return array<string, string> */
  private static function stringFormats(): array {
    return [
      ''          => __('(none)', 'schemable-validator'),
      'email'     => 'Email',
      'uri'       => 'URL',
      'date'      => __('Date (YYYY-MM-DD)', 'schemable-validator'),
      'date-time' => __('DateTime (ISO 8601)', 'schemable-validator'),
      'time'      => __('Time (HH:MM:SS)', 'schemable-validator'),
      'uuid'      => 'UUID',
      'ipv4'      => 'IPv4',
      'ipv6'      => 'IPv6',
    ];
  }

  public static function register(): void {
    add_action('admin_menu', [self::class, 'addMenuPage']);
    add_action('admin_post_schv_save_schema', [self::class, 'handleSave']);
    add_action('admin_post_schv_delete_schema', [self::class, 'handleDelete']);
    add_action('admin_post_schv_export_schema', [self::class, 'handleExport']);
    add_action('admin_post_schv_import_schema', [self::class, 'handleImport']);
  }

  public static function addMenuPage(): void {
    add_submenu_page(
      'schv-settings',
      __('Schema Editor', 'schemable-validator'),
      __('Schema Editor', 'schemable-validator'),
      'manage_options',
      'schv-schema-editor',
      [self::class, 'renderPage']
    );
  }

  /** @return string[] */
  private static function getSlugs(): array {
    $slugs = get_option(self::SLUGS_OPTION, []);
    return is_array($slugs) ? $slugs : [];
  }

  public static function handleSave(): void {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Unauthorized', 'schemable-validator'));
    }
    check_admin_referer('schv_schema_editor');

    $slug = sanitize_key($_POST['schv_slug'] ?? '');
    if ($slug === '') {
      wp_die(esc_html__('Schema slug is required', 'schemable-validator'));
    }

    $jsonSchema = self::buildJsonSchemaFromPost();
    update_option(self::OPTION_PREFIX . $slug, $jsonSchema);

    $dir = get_stylesheet_directory() . '/schv-schemas';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    file_put_contents(
        $dir . '/' . $slug . '.json',
        json_encode($jsonSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $slugs = self::getSlugs();
    if (!in_array($slug, $slugs, true)) {
      $slugs[] = $slug;
      update_option(self::SLUGS_OPTION, $slugs);
    }

    wp_redirect(admin_url('admin.php?page=schv-schema-editor&slug=' . urlencode($slug) . '&saved=1'));
    exit;
  }

  public static function handleDelete(): void {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Unauthorized', 'schemable-validator'));
    }
    check_admin_referer('schv_delete_schema');

    $slug = sanitize_key($_POST['schv_slug'] ?? '');
    if ($slug === '') {
      wp_die(esc_html__('Schema slug is required', 'schemable-validator'));
    }

    delete_option(self::OPTION_PREFIX . $slug);

    $themeFile = get_stylesheet_directory() . '/schv-schemas/' . $slug . '.json';
    if (file_exists($themeFile)) {
        wp_delete_file($themeFile);
    }

    $slugs = self::getSlugs();
    $slugs = array_values(array_filter($slugs, function (string $s) use ($slug): bool {
      return $s !== $slug;
    }));
    update_option(self::SLUGS_OPTION, $slugs);

    wp_redirect(admin_url('admin.php?page=schv-schema-editor&deleted=1'));
    exit;
  }

  public static function handleExport(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'schemable-validator'));
    }
    $slug = sanitize_key($_GET['slug'] ?? '');
    if ($slug === '') {
        wp_die(esc_html__('Schema slug is required', 'schemable-validator'));
    }
    check_admin_referer('schv_export_' . $slug);

    $schema = get_option(self::OPTION_PREFIX . $slug, []);
    $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
  }

  public static function handleImport(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'schemable-validator'));
    }
    check_admin_referer('schv_import_schema');

    if (empty($_FILES['schv_import_file']['tmp_name'])) {
        wp_die(esc_html__('No file uploaded', 'schemable-validator'));
    }

    $json = file_get_contents($_FILES['schv_import_file']['tmp_name']);
    $schema = json_decode($json, true);
    if (!is_array($schema) || !isset($schema['properties'])) {
        wp_die(esc_html__('Invalid schema file', 'schemable-validator'));
    }

    $slug = sanitize_key($_POST['schv_import_slug'] ?? '');
    if ($slug === '') {
        wp_die(esc_html__('Schema slug is required', 'schemable-validator'));
    }

    update_option(self::OPTION_PREFIX . $slug, $schema);

    $slugs = self::getSlugs();
    if (!in_array($slug, $slugs, true)) {
        $slugs[] = $slug;
        update_option(self::SLUGS_OPTION, $slugs);
    }

    // Also write to theme directory
    $dir = get_stylesheet_directory() . '/schv-schemas';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    file_put_contents(
        $dir . '/' . $slug . '.json',
        json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    wp_redirect(admin_url('admin.php?page=schv-schema-editor&slug=' . urlencode($slug) . '&imported=1'));
    exit;
  }

  private static function buildJsonSchemaFromPost(): array {
    $fields = $_POST['schv_fields'] ?? [];
    if (!is_array($fields)) {
      $fields = [];
    }

    $properties = [];
    $required   = [];

    foreach ($fields as $field) {
      $name = sanitize_key($field['name'] ?? '');
      if ($name === '') {
        continue;
      }

      $type = $field['type'] ?? 'string';
      $prop = [];

      if ($type === 'enum') {
        $raw = $field['enum_items'] ?? [];
        $values = is_array($raw)
          ? array_values(array_filter(array_map('trim', $raw)))
          : [];
        $prop['type'] = 'string';
        $prop['enum'] = $values;
      } elseif ($type === 'boolean') {
        $prop['type'] = 'boolean';
      } elseif ($type === 'integer' || $type === 'number') {
        $prop['type'] = $type;
        if (($field['minimum'] ?? '') !== '') {
          $prop['minimum'] = $type === 'integer' ? (int) $field['minimum'] : (float) $field['minimum'];
        }
        if (($field['maximum'] ?? '') !== '') {
          $prop['maximum'] = $type === 'integer' ? (int) $field['maximum'] : (float) $field['maximum'];
        }
      } else {
        $prop['type'] = 'string';
        if (($field['minLength'] ?? '') !== '') {
          $prop['minLength'] = (int) $field['minLength'];
        }
        if (($field['maxLength'] ?? '') !== '') {
          $prop['maxLength'] = (int) $field['maxLength'];
        }
        if (!empty($field['format'])) {
          $prop['format'] = sanitize_key($field['format']);
        }
        if (!empty($field['pattern'])) {
          $prop['pattern'] = $field['pattern'];
        }
      }

      $properties[$name] = $prop;

      if (!empty($field['required'])) {
        $required[] = $name;
      }
    }

    $schema = [
      '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
      'type'       => 'object',
      'properties' => empty($properties) ? (object) [] : $properties,
    ];
    if (!empty($required)) {
      $schema['required'] = $required;
    }
    return $schema;
  }

  // ── Render ────────────────────────────────────────────────────

  public static function renderPage(): void {
    $slugs       = self::getSlugs();
    $editSlug    = sanitize_key($_GET['slug'] ?? '');
    $editSchema  = $editSlug !== '' ? get_option(self::OPTION_PREFIX . $editSlug, null) : null;
    $fields      = [];

    if (is_array($editSchema) && !empty($editSchema['properties'])) {
      $requiredList = $editSchema['required'] ?? [];
      foreach ($editSchema['properties'] as $name => $prop) {
        $type = $prop['type'] ?? 'string';
        if (isset($prop['enum'])) {
          $type = 'enum';
        }
        $fields[] = [
          'name'       => $name,
          'type'       => $type,
          'required'   => in_array($name, $requiredList, true),
          'minLength'  => $prop['minLength'] ?? '',
          'maxLength'  => $prop['maxLength'] ?? '',
          'format'     => $prop['format'] ?? '',
          'pattern'    => $prop['pattern'] ?? '',
          'minimum'    => $prop['minimum'] ?? '',
          'maximum'    => $prop['maximum'] ?? '',
          'enum_items' => isset($prop['enum']) ? $prop['enum'] : [],
        ];
      }
    }

    $saved   = !empty($_GET['saved']);
    $deleted = !empty($_GET['deleted']);
    $imported = !empty($_GET['imported']);

    $codeFields = get_option('schv_code_fields', []);
    $conflicts  = [];
    if (isset($codeFields[$editSlug]) && !empty($fields)) {
        $guiFieldNames = array_column($fields, 'name');
        $conflicts = array_intersect($codeFields[$editSlug], $guiFieldNames);
    }

    self::renderStyles();
    ?>
    <div class="wrap schv-editor">
      <h1><?php echo esc_html__('Schema Editor', 'schemable-validator'); ?></h1>

      <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Schema saved.', 'schemable-validator'); ?></p></div>
      <?php endif; ?>
      <?php if ($deleted): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Schema deleted.', 'schemable-validator'); ?></p></div>
      <?php endif; ?>
      <?php if ($imported): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Schema imported.', 'schemable-validator'); ?></p></div>
      <?php endif; ?>
      <?php if (!empty($conflicts)): ?>
        <div class="notice notice-warning">
          <p>
            <?php echo esc_html__('The following fields are also defined in code via SchemaBuilder. On merge, the code-side definition takes precedence:', 'schemable-validator'); ?>
            <strong><?php echo esc_html(implode(', ', $conflicts)); ?></strong>
          </p>
        </div>
      <?php endif; ?>

      <?php if (!empty($slugs)): ?>
        <h2><?php echo esc_html__('Saved schemas', 'schemable-validator'); ?></h2>
        <table class="widefat fixed striped" style="max-width:640px;margin-bottom:2rem">
          <thead><tr>
            <th><?php echo esc_html__('Slug', 'schemable-validator'); ?></th>
            <th><?php echo esc_html__('Fields', 'schemable-validator'); ?></th>
            <th style="width:80px"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($slugs as $s):
            $sch = get_option(self::OPTION_PREFIX . $s, []);
            $cnt = is_array($sch) && isset($sch['properties']) ? count((array) $sch['properties']) : 0;
          ?>
            <tr>
              <td><a href="<?php echo esc_url(admin_url('admin.php?page=schv-schema-editor&slug=' . urlencode($s))); ?>"><?php echo esc_html($s); ?></a></td>
              <td><?php echo esc_html(sprintf(_n('%d field', '%d fields', $cnt, 'schemable-validator'), $cnt)); ?></td>
              <td>
                <a href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin-post.php?action=schv_export_schema&slug=' . urlencode($s)),
                    'schv_export_' . $s
                )); ?>" class="button" style="margin-right:4px"><?php echo esc_html__('Export', 'schemable-validator'); ?></a>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                  <?php wp_nonce_field('schv_delete_schema'); ?>
                  <input type="hidden" name="action" value="schv_delete_schema">
                  <input type="hidden" name="schv_slug" value="<?php echo esc_attr($s); ?>">
                  <button type="submit" class="button button-link-delete" onclick="return confirm(<?php
                    echo esc_attr(wp_json_encode(sprintf(__("Delete schema '%s'?", 'schemable-validator'), $s)));
                  ?>)"><?php echo esc_html__('Delete', 'schemable-validator'); ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h3><?php echo esc_html__('Import schema', 'schemable-validator'); ?></h3>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-bottom:2rem">
        <?php wp_nonce_field('schv_import_schema'); ?>
        <input type="hidden" name="action" value="schv_import_schema">
        <table class="form-table" style="max-width:640px">
          <tr>
            <th><label for="schv_import_slug"><?php echo esc_html__('Schema slug', 'schemable-validator'); ?></label></th>
            <td><input type="text" id="schv_import_slug" name="schv_import_slug" pattern="[a-z0-9\-]+" required placeholder="e.g. contact" class="regular-text"></td>
          </tr>
          <tr>
            <th><label for="schv_import_file"><?php echo esc_html__('JSON file', 'schemable-validator'); ?></label></th>
            <td><input type="file" id="schv_import_file" name="schv_import_file" accept=".json" required></td>
          </tr>
        </table>
        <?php submit_button(esc_html__('Import', 'schemable-validator'), 'secondary'); ?>
      </form>

      <h2><?php
        if ($editSlug !== '') {
          echo esc_html(sprintf(__('Edit: %s', 'schemable-validator'), $editSlug));
        } else {
          echo esc_html__('New schema', 'schemable-validator');
        }
      ?></h2>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="schv-editor-form">
        <?php wp_nonce_field('schv_schema_editor'); ?>
        <input type="hidden" name="action" value="schv_save_schema">

        <table class="form-table">
          <tr>
            <th><label for="schv_slug"><?php echo esc_html__('Schema slug', 'schemable-validator'); ?></label></th>
            <td>
              <input type="text" id="schv_slug" name="schv_slug"
                value="<?php echo esc_attr($editSlug); ?>"
                pattern="[a-z0-9\-]+" required
                placeholder="e.g. contact"
                <?php echo $editSlug !== '' ? 'readonly' : ''; ?>
                class="regular-text">
              <p class="description"><?php echo esc_html__('Lowercase letters, numbers, hyphens only.', 'schemable-validator'); ?></p>
            </td>
          </tr>
        </table>

        <h3><?php echo esc_html__('Fields', 'schemable-validator'); ?></h3>
        <div id="schv-fields-container">
          <?php if (empty($fields)): ?>
            <p class="description" id="schv-no-fields"><?php echo esc_html__('No fields defined. Click "Add field" to start.', 'schemable-validator'); ?></p>
          <?php else: ?>
            <?php foreach ($fields as $i => $f): ?>
              <?php self::renderFieldRow($i, $f); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <p style="margin-top:12px">
          <button type="button" class="button button-primary" id="schv-add-field">+ <?php echo esc_html__('Add field', 'schemable-validator'); ?></button>
        </p>

        <?php submit_button(esc_html__('Save schema', 'schemable-validator')); ?>
      </form>

      <?php if ($editSlug !== '' && is_array($editSchema)): ?>
        <h3><?php echo esc_html__('Usage', 'schemable-validator'); ?></h3>
        <pre class="schv-code-block"><code><?php
          echo esc_html(
            "use SchemableValidator\\Interfaces\\WordPress\\StoredSchemaProvider;\n"
            . "use SchemableValidator\\Orchestration\\Validator;\n\n"
            . "// REST endpoint\n"
            . "schv_register_schema('/{$editSlug}', new StoredSchemaProvider('{$editSlug}'));\n\n"
            . "// Server-side validation\n"
            . "\$provider = new StoredSchemaProvider('{$editSlug}');\n"
            . "\$result   = Validator::fromJsonSchema(\$provider->toJsonSchema())\n"
            . "    ->validate(\$_POST)->getResult();\n"
          );
        ?></code></pre>

        <details style="margin-top:1rem">
          <summary style="cursor:pointer;font-weight:600"><?php echo esc_html__('Stored JSON Schema', 'schemable-validator'); ?></summary>
          <pre class="schv-code-block" style="margin-top:.5rem"><?php
            echo esc_html(json_encode($editSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
          ?></pre>
        </details>
      <?php endif; ?>
    </div>

    <template id="schv-field-template">
      <?php self::renderFieldRow('__INDEX__', [
        'name' => '', 'type' => 'string', 'required' => false,
        'minLength' => '', 'maxLength' => '', 'format' => '', 'pattern' => '',
        'minimum' => '', 'maximum' => '', 'enum_items' => [],
      ]); ?>
    </template>

    <template id="schv-enum-item-template">
      <div class="schv-enum-item">
        <input type="text" name="__ENUM_NAME__" value="" placeholder="<?php echo esc_attr__('Value', 'schemable-validator'); ?>" class="regular-text" style="width:240px">
        <button type="button" class="button schv-enum-remove" title="<?php echo esc_attr__('Remove', 'schemable-validator'); ?>">&times;</button>
      </div>
    </template>

    <?php self::renderScript(); ?>
    <?php
  }

  /**
   * @param int|string $index
   * @param array      $field
   */
  private static function renderFieldRow($index, array $field): void {
    $prefix      = "schv_fields[{$index}]";
    $fieldTypes  = self::fieldTypes();
    $formats     = self::stringFormats();
    $displayName = $field['name'] !== '' ? $field['name'] : __('(new field)', 'schemable-validator');
    $typeBadge   = $fieldTypes[$field['type']] ?? $field['type'];
    ?>
    <div class="schv-field-row">
      <div class="schv-field-header" data-schv-toggle>
        <span class="schv-field-label"><?php echo esc_html($displayName); ?></span>
        <span class="schv-field-badge"><?php echo esc_html($typeBadge); ?></span>
        <?php if (!empty($field['required'])): ?>
          <span class="schv-field-badge schv-badge-required"><?php echo esc_html__('Required', 'schemable-validator'); ?></span>
        <?php endif; ?>
        <span class="schv-field-toggle dashicons dashicons-arrow-down-alt2"></span>
      </div>

      <div class="schv-field-body">
        <table class="schv-settings-table">
          <tr>
            <td class="schv-label"><?php echo esc_html__('Field name', 'schemable-validator'); ?></td>
            <td>
              <input type="text" name="<?php echo esc_attr($prefix); ?>[name]"
                value="<?php echo esc_attr($field['name']); ?>"
                placeholder="e.g. email" class="regular-text schv-name-input" required>
            </td>
          </tr>
          <tr>
            <td class="schv-label"><?php echo esc_html__('Field type', 'schemable-validator'); ?></td>
            <td>
              <select name="<?php echo esc_attr($prefix); ?>[type]" class="schv-type-select">
                <?php foreach ($fieldTypes as $val => $label): ?>
                  <option value="<?php echo esc_attr($val); ?>" <?php selected($field['type'], $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td class="schv-label"><?php echo esc_html__('Required', 'schemable-validator'); ?></td>
            <td>
              <label class="schv-toggle-label">
                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[required]" value="1"
                  <?php checked(!empty($field['required'])); ?>>
                <?php echo esc_html__('This field is required', 'schemable-validator'); ?>
              </label>
            </td>
          </tr>
        </table>

        <!-- String constraints -->
        <div class="schv-string-opts schv-constraint-group" style="display:<?php echo $field['type'] === 'string' ? '' : 'none'; ?>">
          <table class="schv-settings-table">
            <tr>
              <td class="schv-label"><?php echo esc_html__('Min length', 'schemable-validator'); ?></td>
              <td><input type="number" name="<?php echo esc_attr($prefix); ?>[minLength]" value="<?php echo esc_attr($field['minLength']); ?>" min="0" style="width:100px"></td>
            </tr>
            <tr>
              <td class="schv-label"><?php echo esc_html__('Max length', 'schemable-validator'); ?></td>
              <td><input type="number" name="<?php echo esc_attr($prefix); ?>[maxLength]" value="<?php echo esc_attr($field['maxLength']); ?>" min="0" style="width:100px"></td>
            </tr>
            <tr>
              <td class="schv-label"><?php echo esc_html__('Format', 'schemable-validator'); ?></td>
              <td>
                <select name="<?php echo esc_attr($prefix); ?>[format]">
                  <?php foreach ($formats as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($field['format'], $val); ?>><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td class="schv-label"><?php echo esc_html__('Pattern', 'schemable-validator'); ?></td>
              <td><input type="text" name="<?php echo esc_attr($prefix); ?>[pattern]" value="<?php echo esc_attr($field['pattern']); ?>" placeholder="^[a-z]+$" class="regular-text"></td>
            </tr>
          </table>
        </div>

        <!-- Numeric constraints -->
        <div class="schv-numeric-opts schv-constraint-group" style="display:<?php echo ($field['type'] === 'integer' || $field['type'] === 'number') ? '' : 'none'; ?>">
          <table class="schv-settings-table">
            <tr>
              <td class="schv-label"><?php echo esc_html__('Minimum', 'schemable-validator'); ?></td>
              <td><input type="number" name="<?php echo esc_attr($prefix); ?>[minimum]" value="<?php echo esc_attr($field['minimum']); ?>" step="any" style="width:120px"></td>
            </tr>
            <tr>
              <td class="schv-label"><?php echo esc_html__('Maximum', 'schemable-validator'); ?></td>
              <td><input type="number" name="<?php echo esc_attr($prefix); ?>[maximum]" value="<?php echo esc_attr($field['maximum']); ?>" step="any" style="width:120px"></td>
            </tr>
          </table>
        </div>

        <!-- Boolean description -->
        <div class="schv-boolean-opts schv-constraint-group" style="display:<?php echo $field['type'] === 'boolean' ? '' : 'none'; ?>">
          <p class="description" style="margin:0"><?php echo esc_html__('Accepts true or false. No additional constraints.', 'schemable-validator'); ?></p>
        </div>

        <!-- Enum repeater -->
        <div class="schv-enum-opts schv-constraint-group" style="display:<?php echo $field['type'] === 'enum' ? '' : 'none'; ?>">
          <label class="schv-label" style="display:block;margin-bottom:6px"><?php echo esc_html__('Allowed values', 'schemable-validator'); ?></label>
          <div class="schv-enum-list">
            <?php
            $items = is_array($field['enum_items']) ? $field['enum_items'] : [];
            foreach ($items as $ei => $val): ?>
              <div class="schv-enum-item">
                <input type="text" name="<?php echo esc_attr($prefix); ?>[enum_items][]" value="<?php echo esc_attr($val); ?>" placeholder="<?php echo esc_attr__('Value', 'schemable-validator'); ?>" class="regular-text" style="width:240px">
                <button type="button" class="button schv-enum-remove" title="<?php echo esc_attr__('Remove', 'schemable-validator'); ?>">&times;</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button schv-enum-add" data-prefix="<?php echo esc_attr($prefix); ?>">+ <?php echo esc_html__('Add value', 'schemable-validator'); ?></button>
        </div>

        <div class="schv-field-actions">
          <button type="button" class="button button-link-delete schv-remove-field"><?php echo esc_html__('Remove field', 'schemable-validator'); ?></button>
        </div>
      </div>
    </div>
    <?php
  }

  private static function renderStyles(): void {
    ?>
    <style>
      .schv-editor { max-width: 800px; }
      .schv-code-block { background:#f5f5f5; padding:1rem; overflow:auto; max-width:700px; font-size:.85em; }

      .schv-field-row {
        border: 1px solid #ccd0d4;
        background: #fff;
        margin-bottom: 0;
        border-bottom: 0;
      }
      .schv-field-row:last-child { border-bottom: 1px solid #ccd0d4; }

      .schv-field-header {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        background: #f9f9f9;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        user-select: none;
        gap: 6px;
      }
      .schv-field-header:hover { background: #f0f0f1; }

      .schv-field-label {
        font-weight: 600;
        flex: 1;
        font-size: 13px;
      }
      .schv-field-badge {
        font-size: 11px;
        background: #e0e0e0;
        color: #50575e;
        padding: 2px 8px;
        border-radius: 3px;
        white-space: nowrap;
      }
      .schv-badge-required {
        background: #d63638;
        color: #fff;
      }
      .schv-field-toggle {
        color: #787c82;
        transition: transform .15s;
      }
      .schv-field-row.schv-collapsed .schv-field-body { display: none; }
      .schv-field-row.schv-collapsed .schv-field-toggle { transform: rotate(-90deg); }

      .schv-field-body { padding: 12px 12px 0; }

      .schv-settings-table { width: 100%; border-collapse: collapse; }
      .schv-settings-table td { padding: 6px 0; vertical-align: middle; }
      .schv-settings-table .schv-label {
        width: 140px;
        font-weight: 500;
        font-size: 13px;
        color: #1d2327;
      }
      .schv-toggle-label { font-size: 13px; color: #50575e; }

      .schv-constraint-group {
        border-top: 1px solid #f0f0f1;
        padding-top: 10px;
        margin-top: 6px;
      }

      .schv-field-actions {
        border-top: 1px solid #f0f0f1;
        padding: 8px 0;
        margin-top: 8px;
        text-align: right;
      }

      .schv-enum-item {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 4px;
      }
      .schv-enum-remove { color: #b32d2e !important; min-width: 28px; }
      .schv-enum-add { margin-top: 6px; }
    </style>
    <?php
  }

  private static function renderScript(): void {
    ?>
    <script>
    (() => {
      const container   = document.getElementById('schv-fields-container');
      const addBtn      = document.getElementById('schv-add-field');
      const fieldTpl    = document.getElementById('schv-field-template');
      const enumItemTpl = document.getElementById('schv-enum-item-template');
      let   counter     = container.querySelectorAll('.schv-field-row').length;

      const PANEL_MAP = {
        string:  '.schv-string-opts',
        integer: '.schv-numeric-opts',
        number:  '.schv-numeric-opts',
        boolean: '.schv-boolean-opts',
        enum:    '.schv-enum-opts',
      };

      const toggleConstraints = (row, type) => {
        for (const sel of Object.values(PANEL_MAP)) {
          row.querySelector(sel)?.style.setProperty('display', 'none');
        }
        const active = PANEL_MAP[type];
        if (active) row.querySelector(active)?.style.removeProperty('display');
      };

      const updateBadges = (row) => {
        const typeSelect = row.querySelector('.schv-type-select');
        const typeBadge  = row.querySelector('.schv-field-badge');
        if (typeBadge) typeBadge.textContent = typeSelect.selectedOptions[0]?.text ?? '';

        const reqCheck = row.querySelector('input[name$="[required]"]');
        const reqBadge = row.querySelector('.schv-badge-required');
        if (reqCheck.checked && !reqBadge) {
          const span = document.createElement('span');
          span.className = 'schv-field-badge schv-badge-required';
          span.textContent = <?php echo wp_json_encode(__('Required', 'schemable-validator')); ?>;
          typeBadge.after(span);
        } else if (!reqCheck.checked && reqBadge) {
          reqBadge.remove();
        }
      };

      const bindEnumItem = (item) => {
        item.querySelector('.schv-enum-remove').addEventListener('click', () => item.remove());
      };

      const bindRow = (row) => {
        row.querySelector('[data-schv-toggle]').addEventListener('click', () => {
          row.classList.toggle('schv-collapsed');
        });

        row.querySelector('.schv-remove-field').addEventListener('click', () => row.remove());

        const typeSelect = row.querySelector('.schv-type-select');
        typeSelect.addEventListener('change', () => {
          toggleConstraints(row, typeSelect.value);
          updateBadges(row);
        });
        toggleConstraints(row, typeSelect.value);

        const nameInput = row.querySelector('.schv-name-input');
        nameInput.addEventListener('input', () => {
          row.querySelector('.schv-field-label').textContent =
            nameInput.value || <?php echo wp_json_encode(__('(new field)', 'schemable-validator')); ?>;
        });

        row.querySelector('input[name$="[required]"]').addEventListener('change', () => updateBadges(row));

        const enumAddBtn = row.querySelector('.schv-enum-add');
        enumAddBtn?.addEventListener('click', () => {
          const prefix = enumAddBtn.dataset.prefix;
          const list   = row.querySelector('.schv-enum-list');
          const wrapper = document.createElement('div');
          wrapper.innerHTML = enumItemTpl.innerHTML.replaceAll('__ENUM_NAME__', `${prefix}[enum_items][]`);
          const item = wrapper.firstElementChild;
          list.appendChild(item);
          bindEnumItem(item);
          item.querySelector('input').focus();
        });

        for (const item of row.querySelectorAll('.schv-enum-item')) {
          bindEnumItem(item);
        }
      };

      addBtn.addEventListener('click', () => {
        document.getElementById('schv-no-fields')?.remove();
        const wrapper = document.createElement('div');
        wrapper.innerHTML = fieldTpl.innerHTML.replaceAll('__INDEX__', String(counter));
        const row = wrapper.firstElementChild;
        container.appendChild(row);
        bindRow(row);
        counter++;
      });

      for (const row of container.querySelectorAll('.schv-field-row')) {
        bindRow(row);
        row.classList.add('schv-collapsed');
      }
    })();
    </script>
    <?php
  }
}
