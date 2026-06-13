<?php
/**
 * One-time setup for local development playground.
 * Runs on first WP frontend request: creates example pages.
 * Permalink structure is set via the blueprint's setSiteOptions step.
 */
add_action('init', function () {
  if (get_option('schv_setup_done')) {
    return;
  }

  $pages = [
    [
      'schv-examples',
      'Schemable Validator Examples',
      '<ul>'
        . '<li><a href="/schv-validate/">Validate</a></li>'
        . '<li><a href="/schv-contact/">Contact Form</a></li>'
        . '<li><a href="/schv-files/">File Validation</a></li>'
        . '<li><a href="/schv-csrf/">CSRF Token</a></li>'
        . '<li><a href="/schv-template/">Template</a></li>'
        . '<li><a href="/schv-form-input/">Multi-step Form</a></li>'
        . '<li><a href="/schv-schema-sdk/">SchemaBuilder + SDK</a></li>'
        . '</ul>',
    ],
    ['schv-validate',     'Validate Example',        '[schv_example_validate]'],
    ['schv-contact',      'Contact Form Example',    '[schv_example_contact]'],
    ['schv-files',        'File Validation Example', '[schv_example_files]'],
    ['schv-csrf',         'CSRF Token Example',      '[schv_example_csrf]'],
    ['schv-template',     'Template Example',        '[schv_example_template]'],
    ['schv-form-input',   'Form: Input',             '[schv_example_form_input]'],
    ['schv-form-confirm', 'Form: Confirm',           '[schv_example_form_confirm]'],
    ['schv-form-complete','Form: Complete',          '[schv_example_form_complete]'],
    ['schv-schema-sdk',   'SchemaBuilder + SDK',     '[schv_example_schema_sdk]'],
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
}, 1);

// Incremental: create contact page if missing (for sites already set up).
add_action('init', function () {
  if (get_option('schv_contact_page_created')) {
    return;
  }
  if (!get_page_by_path('schv-contact')) {
    wp_insert_post([
      'post_type'    => 'page',
      'post_status'  => 'publish',
      'post_name'    => 'schv-contact',
      'post_title'   => 'Contact Form Example',
      'post_content' => '[schv_example_contact]',
    ]);
  }
  update_option('schv_contact_page_created', '1');
}, 2);

