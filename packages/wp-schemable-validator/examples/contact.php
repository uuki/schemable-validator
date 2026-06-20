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

  $type_options = [
    'general' => 'General Inquiry',
    'support' => 'Support',
    'sales'   => 'Sales',
    'other'   => 'Other',
  ];
  $fields = [
    'name'  => ['label' => 'name',  'type' => 'text',     'hint' => '2〜50文字'],
    'email' => ['label' => 'email', 'type' => 'email',    'hint' => 'user@example.com'],
    'tel'   => ['label' => 'tel',   'type' => 'tel',      'hint' => '09012345678 または 090-1234-5678'],
  ];

  ob_start(); ?>
  <div style="font-family:sans-serif;max-width:500px">
    <h2>Example: Contact Form</h2>
    <p style="font-size:.85em;color:#666">正規表現でバリデーションスキーマを定義した問い合わせフォームの例です。</p>

    <?php if ($r): ?>
      <?php foreach ($r as $key => $state): ?>
        <p style="color:<?php echo $state['is_valid'] ? 'green' : 'red'; ?>">
          <strong><?php echo esc_html($key); ?></strong>:
          <?php echo $state['is_valid'] ? '✓ valid' : esc_html($state['errors']); ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="schv_action" value="contact">

      <?php foreach ($fields as $key => $meta): ?>
        <p>
          <label>
            <?php echo esc_html($meta['label']); ?>
            <span style="font-size:.8em;color:#999"> — <?php echo esc_html($meta['hint']); ?></span><br>
            <input type="<?php echo esc_attr($meta['type']); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($r[$key]['value'] ?? ''); ?>" style="width:100%">
          </label>
        </p>
      <?php endforeach; ?>

      <p>
        <label>
          type — お問い合わせ種別<br>
          <select name="type" style="width:100%">
            <option value="">— 選択してください —</option>
            <?php foreach ($type_options as $val => $label): ?>
              <option value="<?php echo esc_attr($val); ?>"<?php selected($r['type']['value'] ?? '', $val); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </p>

      <p>
        <label>
          body — お問い合わせ内容
          <span style="font-size:.8em;color:#999"> — 10文字以上</span><br>
          <textarea name="body" rows="4" style="width:100%"><?php echo esc_textarea($r['body']['value'] ?? ''); ?></textarea>
        </label>
      </p>

      <button type="submit">送信</button>
    </form>
  </div>
  <?php return ob_get_clean();
});
