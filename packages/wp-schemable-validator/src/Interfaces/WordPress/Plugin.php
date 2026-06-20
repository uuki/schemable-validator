<?php

namespace SchemableValidator\Interfaces\WordPress;

final class Plugin
{
  /** @var array<string, array<string, string>> */
  private array $settings;

  /**
   * @param array<string, array<string, string>>|null $templates
   */
  public function __construct(?array $templates = null)
  {
    if ($templates === null) {
      $templates = [
        'user' => [
          'title'       => __('Reply format (User)', 'schemable-validator'),
          'description' => __('Use {name}, {email}, {body} as placeholders.', 'schemable-validator'),
        ],
        'admin' => [
          'title'       => __('Reply format (Admin)', 'schemable-validator'),
          'description' => __('Use {name}, {email}, {body} as placeholders.', 'schemable-validator'),
        ],
      ];
    }
    $this->settings = $templates;

    $this->registerHelpers();
    add_action('admin_init', [$this, 'registerSettings']);
    add_action('admin_menu', [$this, 'createMenu']);

    SchemaEditor::register();
  }

  private function registerHelpers(): void
  {
    require_once __DIR__ . '/helpers.php';
  }

  public function registerSettings(): void
  {
    foreach ($this->settings as $key => $setting) {
      register_setting('schv_options_group', "SCHV_REPLY_FORMAT_FOR_$key");
    }
  }

  public function createMenu(): void
  {
    add_menu_page(
      __('Schemable Validator', 'schemable-validator'),
      __('Schemable Validator', 'schemable-validator'),
      'manage_options',
      'schv-settings',
      [$this, 'renderPage'],
      'dashicons-editor-spellcheck',
      81
    );

    add_submenu_page(
      'schv-settings',
      __('Settings', 'schemable-validator'),
      __('Settings', 'schemable-validator'),
      'manage_options',
      'schv-settings',
      [$this, 'renderPage']
    );
  }

  public function renderPage(): void
  {
    ?>
    <div class="wrap">
      <h1><?php echo esc_html__('Schemable Validator Settings', 'schemable-validator'); ?></h1>
      <form method="post" action="options.php">
        <?php settings_fields('schv_options_group'); ?>
        <table class="form-table">
          <?php foreach ($this->settings as $key => $setting): ?>
            <tr valign="top">
              <th scope="row"><?php echo esc_html($setting['title']); ?></th>
              <td>
                <textarea name="<?php echo esc_attr("SCHV_REPLY_FORMAT_FOR_$key"); ?>" rows="20" cols="60"><?php echo esc_textarea(get_option("SCHV_REPLY_FORMAT_FOR_$key")); ?></textarea>
                <p class="description"><?php echo esc_html($setting['description']); ?></p>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?php submit_button(__('Save Changes', 'schemable-validator')); ?>
      </form>
    </div>
    <?php
  }

  /** @return array<string, string> */
  public function keysAll(): array
  {
    $result = [];
    foreach ($this->settings as $key => $setting) {
      $result[$key] = "SCHV_REPLY_FORMAT_FOR_$key";
    }
    return $result;
  }
}
