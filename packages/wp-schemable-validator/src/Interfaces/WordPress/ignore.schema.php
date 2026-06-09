<?php

add_action('admin_menu', 'custom_validation_menu');
function custom_validation_menu() {
  add_options_page('バリデーション設定', 'バリデーション設定', 'manage_options', 'custom-validation-settings', 'custom_validation_settings_page');
}

function custom_validation_settings_page() {
  ?>
  <div class="wrap">
      <h1>バリデーション設定</h1>
      <form method="post" action="options.php">
          <?php
          settings_fields('custom_validation_settings');
          do_settings_sections('custom-validation-settings');
          submit_button();
          ?>
      </form>

      <button id="add_field">フィールドを追加</button>
  </div>
  
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        let fieldCounter = <?php echo count(get_option('custom_validation_fields', [])); ?>;

        // "フィールドを追加"ボタンのクリックイベント
        document.getElementById('add_field').addEventListener('click', function(e) {
            e.preventDefault();

            const container = document.createElement('div');
            container.classList.add('field-group');

            const fieldHTML = `
                <label for="field_name_${fieldCounter}">フィールド名:</label>
                <input type="text" name="custom_validation_fields[${fieldCounter}][field_name]" />

                <div class="validation-rules-container">
                    <label>バリデーションルール:</label>
                    <div class="validation-rule">
                        <select class="validation_rule_selector" name="custom_validation_fields[${fieldCounter}][validation_rules][]">
                            <option value="">選択してください</option>
                            <option value="alnum">英数字のみ</option>
                            <option value="noWhitespace">空白禁止</option>
                            <option value="length">文字数制限</option>
                        </select>

                        <div class="length-options" style="display:none;">
                            <label for="length_min_${fieldCounter}">最小文字数:</label>
                            <input type="number" name="custom_validation_fields[${fieldCounter}][length_min]" />

                            <label for="length_max_${fieldCounter}">最大文字数:</label>
                            <input type="number" name="custom_validation_fields[${fieldCounter}][length_max]" />
                        </div>

                        <button class="remove_rule">ルールを削除</button>
                    </div>
                </div>
                <button class="add_rule">ルールを追加</button>

                <button class="remove_field">フィールドを削除</button>
                <hr>
            `;

            container.innerHTML = fieldHTML;

            // フォームの最後にフィールドを追加
            document.querySelector('form').appendChild(container);

            // バリデーションルールの追加ボタン
            container.querySelector('.add_rule').addEventListener('click', function(e) {
                e.preventDefault();

                const ruleContainer = document.createElement('div');
                ruleContainer.classList.add('validation-rule');
                ruleContainer.innerHTML = `
                    <select class="validation_rule_selector" name="custom_validation_fields[${fieldCounter}][validation_rules][]">
                        <option value="">選択してください</option>
                        <option value="alnum">英数字のみ</option>
                        <option value="noWhitespace">空白禁止</option>
                        <option value="length">文字数制限</option>
                    </select>

                    <div class="length-options" style="display:none;">
                        <label for="length_min_${fieldCounter}">最小文字数:</label>
                        <input type="number" name="custom_validation_fields[${fieldCounter}][length_min]" />

                        <label for="length_max_${fieldCounter}">最大文字数:</label>
                        <input type="number" name="custom_validation_fields[${fieldCounter}][length_max]" />
                    </div>

                    <button class="remove_rule">ルールを削除</button>
                `;

                container.querySelector('.validation-rules-container').appendChild(ruleContainer);

                // 新しく追加されたルールのイベントバインド
                bindRuleEvents(ruleContainer);
            });

            // 削除ボタンのイベント
            container.querySelector('.remove_field').addEventListener('click', function(e) {
                e.preventDefault();
                container.remove();
            });

            // 初期状態のルールにイベントバインド
            bindRuleEvents(container.querySelector('.validation-rule'));

            fieldCounter++;
        });

        // バリデーションルールに関連するイベントをバインド
        function bindRuleEvents(ruleContainer) {
            // バリデーションルール選択時に、lengthフィールドの表示を切り替え
            ruleContainer.querySelector('.validation_rule_selector').addEventListener('change', function() {
                if (this.value === 'length') {
                    ruleContainer.querySelector('.length-options').style.display = 'block';
                } else {
                    ruleContainer.querySelector('.length-options').style.display = 'none';
                }
            });

            // ルール削除ボタンのイベント
            ruleContainer.querySelector('.remove_rule').addEventListener('click', function(e) {
                e.preventDefault();
                ruleContainer.remove();
            });
        }

        // 初期状態のフィールド削除ボタンにイベントバインド
        document.querySelectorAll('.remove_field').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                this.closest('.field-group').remove();
            });
        });

        // 初期状態のバリデーションルールにイベントバインド
        document.querySelectorAll('.validation-rule').forEach(ruleContainer => {
            bindRuleEvents(ruleContainer);
        });
    });
</script>
  <?php
}

// バリデーション設定を登録
add_action('admin_init', 'custom_validation_settings_init');
function custom_validation_settings_init() {
    register_setting('custom_validation_settings', 'custom_validation_fields');

    add_settings_section(
        'custom_validation_main_section',
        '入力フィールドのバリデーションスキーマ',
        null,
        'custom-validation-settings'
    );

    add_settings_field(
        'validation_rules',
        'バリデーションルール',
        'custom_validation_rules_render',
        'custom-validation-settings',
        'custom_validation_main_section'
    );
}

// バリデーションルールの入力フィールド
function custom_validation_rules_render() {
    $fields = get_option('custom_validation_fields', []);

    // 各フィールドに対してバリデーションルールを表示
    foreach ($fields as $index => $field) {
        ?>
        <div class="field-group">
            <label for="field_name_<?php echo $index; ?>">フィールド名:</label>
            <input type="text" name="custom_validation_fields[<?php echo $index; ?>][field_name]" value="<?php echo esc_attr($field['field_name']); ?>" />

            <label for="validation_rules_<?php echo $index; ?>">バリデーションルール:</label>
            <select name="custom_validation_fields[<?php echo $index; ?>][validation_rules][]" multiple>
                <option value="alnum" <?php echo in_array('alnum', $field['validation_rules']) ? 'selected' : ''; ?>>英数字のみ</option>
                <option value="noWhitespace" <?php echo in_array('noWhitespace', $field['validation_rules']) ? 'selected' : ''; ?>>空白禁止</option>
                <option value="length" <?php echo in_array('length', $field['validation_rules']) ? 'selected' : ''; ?>>文字数制限</option>
            </select>

            <label for="length_min_<?php echo $index; ?>">最小文字数:</label>
            <input type="number" name="custom_validation_fields[<?php echo $index; ?>][length_min]" value="<?php echo esc_attr($field['length_min']); ?>" />

            <label for="length_max_<?php echo $index; ?>">最大文字数:</label>
            <input type="number" name="custom_validation_fields[<?php echo $index; ?>][length_max]" value="<?php echo esc_attr($field['length_max']); ?>" />

            <button class="remove_field">フィールドを削除</button>
            <hr>
        </div>
        <?php
    }
}