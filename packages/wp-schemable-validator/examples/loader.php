<?php
if (!defined('ABSPATH')) {
  return;
}

foreach (['validate', 'files', 'csrf', 'template', 'multipage', 'contact', 'schema-sdk'] as $example) {
  require_once __DIR__ . "/{$example}.php";
}

// Incremental setup: create new example pages on existing playgrounds
// where schv_setup_done is already set (so setup.php won't run again).
add_action('init', function () {
  if (get_option('schv_schema_sdk_page_created')) {
    return;
  }
  if (!get_page_by_path('schv-schema-sdk')) {
    wp_insert_post([
      'post_type'    => 'page',
      'post_status'  => 'publish',
      'post_name'    => 'schv-schema-sdk',
      'post_title'   => 'SchemaBuilder + SDK',
      'post_content' => '[schv_example_schema_sdk]',
    ]);
  }
  $index = get_page_by_path('schv-examples');
  if ($index && strpos($index->post_content, 'schv-schema-sdk') === false) {
    $updated = str_replace(
      '</ul>',
      '<li><a href="/schv-schema-sdk/">SchemaBuilder + SDK</a></li></ul>',
      $index->post_content,
    );
    wp_update_post(['ID' => $index->ID, 'post_content' => $updated]);
  }
  update_option('schv_schema_sdk_page_created', '1');
}, 3);
