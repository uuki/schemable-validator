<?php
use SchemableValidator\SV;

// WordPress maps $_REQUEST['name'] to a post-slug query var, which causes 404
// when name has a value. Strip it from query vars on contact POST.
add_filter('request', function ($qv) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['schv_action'] ?? '') === 'contact') {
    unset($qv['name']);
  }
  return $qv;
});

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['schv_action'] ?? '') !== 'contact') {
    return;
  }

  $schema = SV::object([
    // 2文字以上 50文字以内
    'name'    => SV::string()->min(2)->max(50),
    // RFC準拠のメールアドレス形式
    'email'   => SV::string()->email(),
    // 日本の電話番号: ハイフンなし 10〜11桁 または ハイフンあり形式
    'tel'     => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
    // お問い合わせ種別（定義済みの値のみ許可）
    'type'    => SV::enum(['general', 'support', 'sales', 'other']),
    // 10文字以上
    'body'    => SV::string()->min(10),
  ]);

  $GLOBALS['schv_ex_contact'] = $schema->toValidator()->validate($_POST)->getResult();
});

add_shortcode('schv_example_contact', function (): string {
  $r = $GLOBALS['schv_ex_contact'] ?? [];

  $all_valid = !empty($r) && array_reduce($r, fn($c, $s) => $c && $s['is_valid'], true);

  $type_options = [
    'general' => 'General Inquiry',
    'support' => 'Support',
    'sales'   => 'Sales',
    'other'   => 'Other',
  ];

  $fields = [
    'name'  => ['label' => 'name',  'type' => 'text',     'hint' => '2〜50文字',                          'required' => true],
    'email' => ['label' => 'email', 'type' => 'email',    'hint' => 'user@example.com',                   'required' => true],
    'tel'   => ['label' => 'tel',   'type' => 'tel',      'hint' => '09012345678 または 090-1234-5678',    'required' => false],
    'type'  => ['label' => 'type',  'type' => 'select',   'hint' => 'お問い合わせ種別',                    'required' => true,  'options' => $type_options],
    'body'  => ['label' => 'body',  'type' => 'textarea', 'hint' => '10文字以上',                          'required' => true],
  ];

  ob_start(); ?>
  <div class="schv-wrap">
    <h2>Example: Contact Form</h2>
    <p class="schv-desc">正規表現でバリデーションスキーマを定義した問い合わせフォームの例です。</p>
    <p class="schv-legend"><span class="schv-req" aria-hidden="true">*</span> は必須項目です。</p>

    <?php if ($all_valid): ?>
      <div class="schv-success">✓ 送信内容を確認しました。</div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="contact">

      <?php foreach ($fields as $key => $meta):
        $err = (isset($r[$key]) && !$r[$key]['is_valid']) ? $r[$key]['errors'] : '';
        $val = $r[$key]['value'] ?? '';
      ?>
        <div class="schv-field">
          <label class="schv-label" for="schv-<?php echo esc_attr($key); ?>">
            <?php echo esc_html($meta['label']); ?>
            <?php if ($meta['required']): ?>
              <span class="schv-req" aria-hidden="true">*</span>
            <?php else: ?>
              <span class="schv-opt">（任意）</span>
            <?php endif; ?>
            <span class="schv-hint">— <?php echo esc_html($meta['hint']); ?></span>
          </label>

          <?php if ($meta['type'] === 'select'): ?>
            <select id="schv-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>"
              class="schv-select<?php echo $err ? ' is-error' : ''; ?>">
              <option value="">— 選択してください —</option>
              <?php foreach ($meta['options'] as $v => $lbl): ?>
                <option value="<?php echo esc_attr($v); ?>"<?php selected($val, $v); ?>>
                  <?php echo esc_html($lbl); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($meta['type'] === 'textarea'): ?>
            <textarea id="schv-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" rows="4"
              class="schv-textarea<?php echo $err ? ' is-error' : ''; ?>"><?php echo esc_textarea($val); ?></textarea>
          <?php else: ?>
            <input type="<?php echo esc_attr($meta['type']); ?>"
              id="schv-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>"
              value="<?php echo esc_attr($val); ?>"
              class="schv-input<?php echo $err ? ' is-error' : ''; ?>">
          <?php endif; ?>

          <span class="schv-error" role="alert"><?php echo esc_html($err); ?></span>
        </div>
      <?php endforeach; ?>

      <div class="schv-actions">
        <button type="submit" class="schv-btn">送信</button>
      </div>
    </form>
  </div>
  <?php return ob_get_clean();
});
