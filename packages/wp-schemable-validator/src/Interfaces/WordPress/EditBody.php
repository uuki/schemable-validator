<?php
function sv_register_settings() {
  $settings = [
    SV_REPLY_FORMAT_FOR_USER,
    SV_REPLY_FORMAT_FOR_ADMIN
  ];
  foreach ($settings as $key) {
    register_setting('sv_options_group', $key);
  }
}
function sv_create_menu() {
  add_options_page('Schemable Validator Settings', 'Schemable Validator', 'manage_options', 'sv-settings', 'sv_settings_page');
}

add_action('admin_init', 'sv_register_settings');
add_action('admin_menu', 'sv_create_menu');

function sv_settings_page() {
  ?>
  <div class="wrap">
    <h1>Schemable Validator Settings</h1>
    <form method="post" action="options.php">
      <?php settings_fields('sv_options_group'); ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Reply format（Admin）</th>
          <td>
            <textarea name="<?php echo esc_attr(SV_REPLY_FORMAT_FOR_ADMIN); ?>" rows="20" cols="60"><?php echo esc_textarea(get_option(SV_REPLY_FORMAT_FOR_ADMIN)); ?></textarea>
            <p class="description">Use {name}, {email}, {body} as placeholders.</p>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Reply format（User）</th>
          <td>
            <textarea name="<?php echo esc_attr(SV_REPLY_FORMAT_FOR_USER); ?>" rows="20" cols="60"><?php echo esc_textarea(get_option(SV_REPLY_FORMAT_FOR_USER)); ?></textarea>
            <p class="description">Use {name}, {email}, {body} as placeholders.</p>
          </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}
