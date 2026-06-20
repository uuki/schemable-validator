<?php

namespace SchemableValidator\Interfaces\WordPress;

/**
 * WordPress admin page for defining validation schemas via GUI.
 *
 * The editor stores a JSON Schema 2020-12 object in wp_options under
 * the key "schv_schema_{$slug}".  At runtime, pair with StoredSchemaProvider
 * to feed the schema into Validator::fromJsonSchema() or schv_register_schema().
 *
 * Supported field types: string, integer, number, boolean, enum.
 * Each type exposes the constraints available in SchemaBuilder (min/max length,
 * pattern, format, numeric bounds, enum values).
 */
final class SchemaEditor {
  private const OPTION_PREFIX = 'schv_schema_';
  private const SLUGS_OPTION  = 'schv_schema_slugs';

  private const FIELD_TYPES = [
    'string'  => 'String',
    'integer' => 'Integer',
    'number'  => 'Number',
    'boolean' => 'Boolean',
    'enum'    => 'Enum',
  ];

  private const STRING_FORMATS = [
    ''          => '(none)',
    'email'     => 'Email',
    'uri'       => 'URL',
    'date'      => 'Date (YYYY-MM-DD)',
    'date-time' => 'DateTime (ISO 8601)',
    'time'      => 'Time (HH:MM:SS)',
    'uuid'      => 'UUID',
    'ipv4'      => 'IPv4',
    'ipv6'      => 'IPv6',
  ];

  public static function register(): void {
    add_action('admin_menu', [self::class, 'addMenuPage']);
    add_action('admin_post_schv_save_schema', [self::class, 'handleSave']);
    add_action('admin_post_schv_delete_schema', [self::class, 'handleDelete']);
  }

  public static function addMenuPage(): void {
    add_submenu_page(
      'schv-settings',
      'Schema Editor',
      'Schema Editor',
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
      wp_die('Unauthorized');
    }
    check_admin_referer('schv_schema_editor');

    $slug = sanitize_key($_POST['schv_slug'] ?? '');
    if ($slug === '') {
      wp_die('Schema slug is required');
    }

    $jsonSchema = self::buildJsonSchemaFromPost();
    update_option(self::OPTION_PREFIX . $slug, $jsonSchema);

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
      wp_die('Unauthorized');
    }
    check_admin_referer('schv_delete_schema');

    $slug = sanitize_key($_POST['schv_slug'] ?? '');
    if ($slug === '') {
      wp_die('Schema slug is required');
    }

    delete_option(self::OPTION_PREFIX . $slug);

    $slugs = self::getSlugs();
    $slugs = array_values(array_filter($slugs, function (string $s) use ($slug): bool {
      return $s !== $slug;
    }));
    update_option(self::SLUGS_OPTION, $slugs);

    wp_redirect(admin_url('admin.php?page=schv-schema-editor&deleted=1'));
    exit;
  }

  private static function buildJsonSchemaFromPost(): array {
    $fields   = $_POST['schv_fields'] ?? [];
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
        $values = array_filter(array_map('trim', explode("\n", $field['enum_values'] ?? '')));
        $prop['type'] = 'string';
        $prop['enum'] = array_values($values);
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
          'name'        => $name,
          'type'        => $type,
          'required'    => in_array($name, $requiredList, true),
          'minLength'   => $prop['minLength'] ?? '',
          'maxLength'   => $prop['maxLength'] ?? '',
          'format'      => $prop['format'] ?? '',
          'pattern'     => $prop['pattern'] ?? '',
          'minimum'     => $prop['minimum'] ?? '',
          'maximum'     => $prop['maximum'] ?? '',
          'enum_values' => isset($prop['enum']) ? implode("\n", $prop['enum']) : '',
        ];
      }
    }

    $saved   = !empty($_GET['saved']);
    $deleted = !empty($_GET['deleted']);
    ?>
    <div class="wrap">
      <h1>Schema Editor</h1>

      <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p>Schema saved.</p></div>
      <?php endif; ?>
      <?php if ($deleted): ?>
        <div class="notice notice-success is-dismissible"><p>Schema deleted.</p></div>
      <?php endif; ?>

      <?php if (!empty($slugs)): ?>
        <h2>Saved schemas</h2>
        <table class="widefat fixed striped" style="max-width:600px;margin-bottom:2rem">
          <thead><tr><th>Slug</th><th>Fields</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($slugs as $s):
            $sch = get_option(self::OPTION_PREFIX . $s, []);
            $cnt = is_array($sch) && isset($sch['properties']) ? count((array) $sch['properties']) : 0;
          ?>
            <tr>
              <td><a href="<?php echo esc_url(admin_url('admin.php?page=schv-schema-editor&slug=' . urlencode($s))); ?>"><?php echo esc_html($s); ?></a></td>
              <td><?php echo esc_html($cnt); ?> fields</td>
              <td>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                  <?php wp_nonce_field('schv_delete_schema'); ?>
                  <input type="hidden" name="action" value="schv_delete_schema">
                  <input type="hidden" name="schv_slug" value="<?php echo esc_attr($s); ?>">
                  <button type="submit" class="button button-link-delete" onclick="return confirm('Delete schema \'<?php echo esc_js($s); ?>\'?')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h2><?php echo $editSlug !== '' ? 'Edit: ' . esc_html($editSlug) : 'New schema'; ?></h2>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="schv-editor-form">
        <?php wp_nonce_field('schv_schema_editor'); ?>
        <input type="hidden" name="action" value="schv_save_schema">

        <table class="form-table">
          <tr>
            <th><label for="schv_slug">Schema slug</label></th>
            <td>
              <input type="text" id="schv_slug" name="schv_slug"
                value="<?php echo esc_attr($editSlug); ?>"
                pattern="[a-z0-9\-]+" required
                placeholder="e.g. contact"
                <?php echo $editSlug !== '' ? 'readonly' : ''; ?>
                class="regular-text">
              <p class="description">Lowercase letters, numbers, hyphens only.</p>
            </td>
          </tr>
        </table>

        <h3>Fields</h3>
        <div id="schv-fields-container">
          <?php if (empty($fields)): ?>
            <p class="description" id="schv-no-fields">No fields defined. Click "Add field" to start.</p>
          <?php else: ?>
            <?php foreach ($fields as $i => $f): ?>
              <?php self::renderFieldRow($i, $f); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <p>
          <button type="button" class="button" id="schv-add-field">Add field</button>
        </p>

        <?php submit_button('Save schema'); ?>
      </form>

      <?php if ($editSlug !== '' && is_array($editSchema)): ?>
        <h3>Usage</h3>
        <pre style="background:#f5f5f5;padding:1rem;overflow:auto;max-width:700px"><code><?php
          echo esc_html(
            "// functions.php or plugin code\n"
            . "use SchemableValidator\\Interfaces\\WordPress\\StoredSchemaProvider;\n"
            . "use SchemableValidator\\Orchestration\\Validator;\n\n"
            . "// REST endpoint (client-side consumption)\n"
            . "schv_register_schema('/{$editSlug}', new StoredSchemaProvider('{$editSlug}'));\n\n"
            . "// Server-side validation\n"
            . "\$provider = new StoredSchemaProvider('{$editSlug}');\n"
            . "\$result   = Validator::fromJsonSchema(\$provider->toJsonSchema())\n"
            . "    ->validate(\$_POST)\n"
            . "    ->getResult();\n"
          );
        ?></code></pre>

        <details style="margin-top:1rem">
          <summary style="cursor:pointer;font-weight:600">Stored JSON Schema</summary>
          <pre style="background:#f5f5f5;padding:1rem;overflow:auto;max-width:700px;margin-top:.5rem"><?php
            echo esc_html(json_encode($editSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
          ?></pre>
        </details>
      <?php endif; ?>
    </div>

    <template id="schv-field-template">
      <?php self::renderFieldRow('__INDEX__', [
        'name' => '', 'type' => 'string', 'required' => false,
        'minLength' => '', 'maxLength' => '', 'format' => '', 'pattern' => '',
        'minimum' => '', 'maximum' => '', 'enum_values' => '',
      ]); ?>
    </template>

    <script>
    (function() {
      var container = document.getElementById('schv-fields-container');
      var addBtn    = document.getElementById('schv-add-field');
      var template  = document.getElementById('schv-field-template');
      var counter   = container.querySelectorAll('.schv-field-row').length;

      addBtn.addEventListener('click', function() {
        var noFields = document.getElementById('schv-no-fields');
        if (noFields) noFields.remove();

        var html = template.innerHTML.replace(/__INDEX__/g, String(counter));
        var div  = document.createElement('div');
        div.innerHTML = html;
        var row = div.firstElementChild;
        container.appendChild(row);
        bindRow(row);
        counter++;
      });

      function bindRow(row) {
        row.querySelector('.schv-remove-field').addEventListener('click', function() {
          row.remove();
        });
        var typeSelect = row.querySelector('.schv-type-select');
        typeSelect.addEventListener('change', function() {
          toggleConstraints(row, this.value);
        });
        toggleConstraints(row, typeSelect.value);
      }

      function toggleConstraints(row, type) {
        row.querySelector('.schv-string-opts').style.display  = type === 'string'  ? '' : 'none';
        row.querySelector('.schv-numeric-opts').style.display  = (type === 'integer' || type === 'number') ? '' : 'none';
        row.querySelector('.schv-enum-opts').style.display     = type === 'enum'    ? '' : 'none';
      }

      container.querySelectorAll('.schv-field-row').forEach(function(row) {
        bindRow(row);
      });
    })();
    </script>
    <?php
  }

  /**
   * @param int|string $index
   * @param array      $field
   */
  private static function renderFieldRow($index, array $field): void {
    $prefix = "schv_fields[{$index}]";
    ?>
    <div class="schv-field-row" style="border:1px solid #ccd0d4;padding:12px;margin-bottom:8px;background:#fff">
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <input type="text" name="<?php echo esc_attr($prefix); ?>[name]"
          value="<?php echo esc_attr($field['name']); ?>"
          placeholder="Field name" class="regular-text" required
          style="flex:1">

        <select name="<?php echo esc_attr($prefix); ?>[type]" class="schv-type-select">
          <?php foreach (self::FIELD_TYPES as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected($field['type'], $val); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>

        <label style="white-space:nowrap">
          <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[required]" value="1"
            <?php checked(!empty($field['required'])); ?>>
          Required
        </label>

        <button type="button" class="button schv-remove-field" title="Remove">&times;</button>
      </div>

      <div class="schv-string-opts" style="display:<?php echo $field['type'] === 'string' ? '' : 'none'; ?>">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label>Min length <input type="number" name="<?php echo esc_attr($prefix); ?>[minLength]" value="<?php echo esc_attr($field['minLength']); ?>" min="0" style="width:80px"></label>
          <label>Max length <input type="number" name="<?php echo esc_attr($prefix); ?>[maxLength]" value="<?php echo esc_attr($field['maxLength']); ?>" min="0" style="width:80px"></label>
          <label>Format
            <select name="<?php echo esc_attr($prefix); ?>[format]">
              <?php foreach (self::STRING_FORMATS as $val => $label): ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($field['format'], $val); ?>><?php echo esc_html($label); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Pattern <input type="text" name="<?php echo esc_attr($prefix); ?>[pattern]" value="<?php echo esc_attr($field['pattern']); ?>" placeholder="^[a-z]+$" style="width:200px"></label>
        </div>
      </div>

      <div class="schv-numeric-opts" style="display:<?php echo ($field['type'] === 'integer' || $field['type'] === 'number') ? '' : 'none'; ?>">
        <div style="display:flex;gap:8px">
          <label>Minimum <input type="number" name="<?php echo esc_attr($prefix); ?>[minimum]" value="<?php echo esc_attr($field['minimum']); ?>" step="any" style="width:100px"></label>
          <label>Maximum <input type="number" name="<?php echo esc_attr($prefix); ?>[maximum]" value="<?php echo esc_attr($field['maximum']); ?>" step="any" style="width:100px"></label>
        </div>
      </div>

      <div class="schv-enum-opts" style="display:<?php echo $field['type'] === 'enum' ? '' : 'none'; ?>">
        <label>Values (one per line)<br>
          <textarea name="<?php echo esc_attr($prefix); ?>[enum_values]" rows="3" cols="30"><?php echo esc_textarea($field['enum_values']); ?></textarea>
        </label>
      </div>
    </div>
    <?php
  }
}
