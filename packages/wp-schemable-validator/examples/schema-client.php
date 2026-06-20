<?php
// Example: SchemaBuilder + REST → Zod (real-time client validation)
//
// PHP side  — defines constraints once with SV::object()
// JS side   — fetches the JSON Schema, converts to Zod, validates on blur
//             with dirty-field tracking (no errors until a field is touched)

use SchemableValidator\SV;

// 1. Single source of truth for constraints.
$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'sales', 'other']),
  'body'  => SV::string()->min(10),
]);

// 2. Expose as JSON Schema  GET /wp-json/schv/v1/schema/contact
schv_register_schema('/schema/contact', $schema);

// Store for shortcode display
add_action('init', function () use ($schema) {
  $GLOBALS['schv_registered_schema_client'] = $schema;
});

// 3. Server-side POST handler (same schema — no duplication)
add_action('template_redirect', function () use ($schema) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'schema-client') {
    return;
  }
  $GLOBALS['schv_ex_schema_client'] = $schema->toValidator()->validate($_POST)->getResult();
});

add_shortcode('schv_example_schema_client', function (): string {
  $r          = $GLOBALS['schv_ex_schema_client'] ?? [];
  $schema_url = schv_schema_url('/schema/contact');

  $type_options = ['general' => 'General', 'support' => 'Support', 'sales' => 'Sales', 'other' => 'Other'];

  // Fields in display order: type first, then name / email / tel / body
  $text_fields = [
    'name'  => ['label' => 'Name',    'type' => 'text',  'required' => true],
    'email' => ['label' => 'Email',   'type' => 'email', 'required' => true],
    'tel'   => ['label' => 'Tel',     'type' => 'tel',   'required' => false, 'hint' => '09012345678 or 090-1234-5678'],
  ];

  ob_start(); ?>
  <div class="schv-wrap schv-wide">
    <h2>Example: SchemaBuilder + Zod</h2>
    <p class="schv-desc">
      PHP で定義した制約を REST 経由で取得し、Zod に変換してフィールド blur 時にリアルタイム検証する例。
      フォーカスを当てる前のフィールドにはエラーを表示しない。
    </p>
    <p class="schv-legend"><span class="schv-req" aria-hidden="true">*</span> Required</p>

    <details style="margin-bottom:1.1rem">
      <summary style="cursor:pointer;font-size:.875rem;font-weight:600;color:#374151">
        JSON Schema (<code><?php echo esc_html($schema_url); ?></code>)
      </summary>
      <pre style="background:#f5f5f5;padding:.9rem;overflow:auto;font-size:.78rem;border-radius:4px;margin-top:.5rem;line-height:1.5"><?php
        $json = $GLOBALS['schv_registered_schema_client']->toJson();
        echo esc_html(json_encode(
          json_decode($json),
          JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
      ?></pre>
    </details>

    <form id="schv-client-form" method="post" novalidate>
      <input type="hidden" name="schv_action" value="schema-client">

      <?php
      // type — first field
      $type_err = (isset($r['type']) && !$r['type']['is_valid']) ? $r['type']['errors'] : '';
      ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-type">
          Type<span class="schv-req" aria-hidden="true">*</span>
        </label>
        <select id="schv-type" name="type"
          class="schv-select<?php echo $type_err ? ' is-error' : ''; ?>"
          data-field="type" aria-required="true">
          <option value="">— select —</option>
          <?php foreach ($type_options as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>"<?php selected($r['type']['value'] ?? '', $val); ?>>
              <?php echo esc_html($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span id="err-type" class="schv-error" role="alert"><?php echo esc_html($type_err); ?></span>
      </div>

      <?php foreach ($text_fields as $key => $meta):
        $err = (isset($r[$key]) && !$r[$key]['is_valid']) ? $r[$key]['errors'] : '';
      ?>
        <div class="schv-field">
          <label class="schv-label" for="schv-<?php echo esc_attr($key); ?>">
            <?php echo esc_html($meta['label']); ?>
            <?php if ($meta['required']): ?>
              <span class="schv-req" aria-hidden="true">*</span>
            <?php else: ?>
              <span class="schv-opt">（任意）</span>
            <?php endif; ?>
            <?php if (!empty($meta['hint'])): ?>
              <span class="schv-hint">— <?php echo esc_html($meta['hint']); ?></span>
            <?php endif; ?>
          </label>
          <input type="<?php echo esc_attr($meta['type']); ?>"
            id="schv-<?php echo esc_attr($key); ?>"
            name="<?php echo esc_attr($key); ?>"
            value="<?php echo esc_attr($r[$key]['value'] ?? ''); ?>"
            class="schv-input<?php echo $err ? ' is-error' : ''; ?>"
            data-field="<?php echo esc_attr($key); ?>"
            <?php if ($meta['required']): ?>aria-required="true"<?php endif; ?>>
          <span id="err-<?php echo esc_attr($key); ?>" class="schv-error" role="alert">
            <?php echo esc_html($err); ?>
          </span>
        </div>
      <?php endforeach; ?>

      <?php $body_err = (isset($r['body']) && !$r['body']['is_valid']) ? $r['body']['errors'] : ''; ?>
      <div class="schv-field">
        <label class="schv-label" for="schv-body">
          Message<span class="schv-req" aria-hidden="true">*</span>
        </label>
        <textarea id="schv-body" name="body" rows="4"
          class="schv-textarea<?php echo $body_err ? ' is-error' : ''; ?>"
          data-field="body" aria-required="true"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea>
        <span id="err-body" class="schv-error" role="alert"><?php echo esc_html($body_err); ?></span>
      </div>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">送信 (client + server)</button>
      </div>
    </form>
  </div>

  <script type="module">
    import { z } from 'https://esm.sh/zod@3'

    const SCHEMA_URL = <?php echo wp_json_encode($schema_url); ?>

    // ── JSON Schema → Zod ──────────────────────────────────────────────────

    function propertyToZod(prop) {
      const types    = Array.isArray(prop.type) ? prop.type : [prop.type]
      const nullable = types.includes('null')
      const primary  = types.find(t => t !== 'null') ?? 'string'

      let zType

      if (primary === 'integer') {
        zType = z.coerce.number().int()
        if (prop.minimum !== undefined) zType = zType.gte(prop.minimum)
        if (prop.maximum !== undefined) zType = zType.lte(prop.maximum)
      } else if (primary === 'number') {
        zType = z.coerce.number()
        if (prop.minimum !== undefined) zType = zType.gte(prop.minimum)
        if (prop.maximum !== undefined) zType = zType.lte(prop.maximum)
      } else if (primary === 'boolean') {
        zType = z.boolean()
      } else {
        // string
        if (prop.enum) {
          const [first, ...rest] = prop.enum
          zType = z.enum([first, ...rest])
        } else {
          zType = z.string()
          if (prop.minLength !== undefined) zType = zType.min(prop.minLength, { message: `${prop.minLength}文字以上で入力してください` })
          if (prop.maxLength !== undefined) zType = zType.max(prop.maxLength, { message: `${prop.maxLength}文字以内で入力してください` })
          if (prop.format === 'email')      zType = zType.email({ message: '有効なメールアドレスを入力してください' })
          if (prop.format === 'uri')        zType = zType.url({ message: '有効なURLを入力してください' })
          if (prop.pattern)                 zType = zType.regex(new RegExp(prop.pattern, 'u'), { message: '形式が正しくありません' })
        }
      }

      return nullable ? zType.nullable() : zType
    }

    function buildZodSchema(jsonSchema) {
      const required = jsonSchema.required ?? []
      const shape    = {}

      for (const [name, prop] of Object.entries(jsonSchema.properties)) {
        const isRequired = required.includes(name)
        let zType = propertyToZod(prop)

        if (!isRequired) {
          // Optional fields: empty string treated as "not provided" — skip validation
          zType = z.preprocess(v => (v === '' ? undefined : v), zType.optional())
        }

        shape[name] = zType
      }

      return z.object(shape)
    }

    // ── Dirty-field tracking ───────────────────────────────────────────────
    // A field is "dirty" once it has received and lost focus at least once.
    // Errors are only shown for dirty fields so the form starts clean.

    const dirty    = new Set()
    const form     = document.getElementById('schv-client-form')
    let zodSchema  = null
    let jsonSchema = null

    async function ensureSchema() {
      if (zodSchema) return zodSchema
      const res = await fetch(SCHEMA_URL)
      if (!res.ok) throw new Error(`schema fetch failed: ${res.status}`)
      jsonSchema = await res.json()
      zodSchema  = buildZodSchema(jsonSchema)
      return zodSchema
    }

    function setFieldError(name, message) {
      const errEl   = document.getElementById(`err-${name}`)
      const inputEl = form.querySelector(`[data-field="${name}"]`)
      if (errEl)   errEl.textContent = message ?? ''
      if (inputEl) inputEl.classList.toggle('is-error', !!message)
    }

    function validateField(name, value) {
      if (!zodSchema) return
      const fieldSchema = zodSchema.shape[name]
      if (!fieldSchema) return

      // Optional + empty = valid; skip Zod parse entirely
      const required = jsonSchema.required ?? []
      if (!required.includes(name) && value === '') {
        setFieldError(name, null)
        return
      }

      const result = fieldSchema.safeParse(value)
      setFieldError(name, result.success ? null : result.error.errors[0]?.message ?? 'Invalid')
    }

    // ── Event wiring ───────────────────────────────────────────────────────

    async function initListeners() {
      await ensureSchema()

      for (const el of form.querySelectorAll('[data-field]')) {
        const name = el.dataset.field

        // blur: mark dirty and validate immediately
        el.addEventListener('blur', () => {
          dirty.add(name)
          validateField(name, el.value)
        })

        // input: re-validate only if the field has already been touched
        el.addEventListener('input', () => {
          if (dirty.has(name)) validateField(name, el.value)
        })
      }
    }

    form.addEventListener('submit', async e => {
      e.preventDefault()
      const schema = await ensureSchema()

      // Mark every field as dirty so all errors become visible on submit
      for (const name of Object.keys(jsonSchema.properties)) dirty.add(name)

      const data   = Object.fromEntries(
        [...new FormData(form).entries()].flatMap(([k, v]) =>
          typeof v === 'string' ? [[k, v]] : []
        )
      )
      const result = schema.safeParse(data)

      if (result.success) {
        form.submit()
        return
      }

      // Show all field errors
      const fieldErrors = {}
      for (const issue of result.error.errors) {
        const name = String(issue.path[0])
        if (!fieldErrors[name]) fieldErrors[name] = issue.message
      }
      for (const name of Object.keys(jsonSchema.properties)) {
        setFieldError(name, fieldErrors[name] ?? null)
      }
    })

    initListeners()
  </script>
  <?php return ob_get_clean();
});
