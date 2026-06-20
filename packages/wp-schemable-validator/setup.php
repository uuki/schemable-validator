<?php
/**
 * Setup and migration for local development playground.
 * Runs on every non-admin frontend request, but exits early once v2 is done.
 * Permalink structure is set via the blueprint's setSiteOptions step.
 */

if (!defined('ABSPATH')) {
  return;
}

if (!function_exists('_schv_index_content')) {
  function _schv_index_content(): string {
    return '<ul>'
      . '<li><a href="/schv-validate/">Validate</a></li>'
      . '<li><a href="/schv-contact/">Contact Form</a></li>'
      . '<li><a href="/schv-files/">File Validation</a></li>'
      . '<li><a href="/schv-csrf/">CSRF Token</a></li>'
      . '<li><a href="/schv-template/">Template</a></li>'
      . '<li><a href="/schv-form-input/">Multi-step Form</a></li>'
      . '<li><a href="/schv-schema-client/">SchemaBuilder + Client</a></li>'
      . '<li><a href="/schv-merge-schema/">Merge Schema</a></li>'
      . '</ul>';
  }
}

if (!function_exists('_schv_clear_navigation')) {
  function _schv_clear_navigation(): void {
    $navs = get_posts([
      'post_type'   => 'wp_navigation',
      'post_status' => 'publish',
      'numberposts' => -1,
    ]);
    if (empty($navs)) {
      wp_insert_post([
        'post_type'    => 'wp_navigation',
        'post_status'  => 'publish',
        'post_title'   => 'Main Navigation',
        'post_content' => '',
      ]);
    } else {
      foreach ($navs as $nav) {
        wp_update_post(['ID' => $nav->ID, 'post_content' => '']);
      }
    }
  }
}

// Suppress the theme Navigation block so example page slugs don't appear in the header.
add_filter('render_block_core/navigation', '__return_empty_string');

add_action('init', function () {
  if (get_option('schv_setup_v2')) {
    return;
  }

  $index_content = _schv_index_content();
  $front_id      = null;

  if (!get_option('schv_setup_done')) {
    // Fresh playground: create all pages from scratch.
    $front_id = wp_insert_post([
      'post_type'    => 'page',
      'post_status'  => 'publish',
      'post_name'    => 'examples-index',
      'post_title'   => 'Schemable Validator Examples',
      'post_content' => $index_content,
    ]);

    $pages = [
      ['schv-validate',     'Validate Example',        '[schv_example_validate]'],
      ['schv-contact',      'Contact Form Example',    '[schv_example_contact]'],
      ['schv-files',        'File Validation Example', '[schv_example_files]'],
      ['schv-csrf',         'CSRF Token Example',      '[schv_example_csrf]'],
      ['schv-template',     'Template Example',        '[schv_example_template]'],
      ['schv-form-input',   'Form: Input',             '[schv_example_form_input]'],
      ['schv-form-confirm', 'Form: Confirm',           '[schv_example_form_confirm]'],
      ['schv-form-complete','Form: Complete',          '[schv_example_form_complete]'],
      ['schv-schema-client','SchemaBuilder + Client',  '[schv_example_schema_client]'],
      ['schv-merge-schema', 'Merge Schema Example',    '[schv_example_merge_schema]'],
    ];
    foreach ($pages as [$slug, $title, $content]) {
      wp_insert_post([
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_name'    => $slug,
        'post_title'   => $title,
        'post_content' => $content,
      ]);
    }

    update_option('schv_setup_done', '1');
  } else {
    // Existing playground: migrate schv-examples → static front page,
    // and ensure pages added after the initial setup exist.
    $old = get_page_by_path('schv-examples');
    if ($old) {
      wp_update_post([
        'ID'           => $old->ID,
        'post_name'    => 'examples-index',
        'post_content' => $index_content,
      ]);
      $front_id = $old->ID;
    } else {
      $existing = get_page_by_path('examples-index');
      $front_id = $existing ? $existing->ID : wp_insert_post([
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_name'    => 'examples-index',
        'post_title'   => 'Schemable Validator Examples',
        'post_content' => $index_content,
      ]);
    }

    foreach ([
      ['schv-contact',      'Contact Form Example',   '[schv_example_contact]'],
      ['schv-schema-client','SchemaBuilder + Client',  '[schv_example_schema_client]'],
      ['schv-merge-schema', 'Merge Schema Example',    '[schv_example_merge_schema]'],
    ] as [$slug, $title, $content]) {
      if (!get_page_by_path($slug)) {
        wp_insert_post([
          'post_type'    => 'page',
          'post_status'  => 'publish',
          'post_name'    => $slug,
          'post_title'   => $title,
          'post_content' => $content,
        ]);
      }
    }
  }

  update_option('show_on_front', 'page');
  update_option('page_on_front', $front_id);
  _schv_clear_navigation();
  update_option('schv_setup_v2', '1');
}, 1);
