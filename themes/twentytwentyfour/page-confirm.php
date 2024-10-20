<?php
/* Template Name: form-confirm */
  get_header();

  // require_once SCHEMABLE_VALIDATOR_PATH . '/Template.php';
  // require_once SCHEMABLE_VALIDATOR_PATH . '/Controllers/FormController.php';
  use SchemableValidator\Template;
  use SchemableValidator\FormController;

  $template = new Template([
    'name' => 'name',
    'email' => 'email',
    'body' => 'body',
  ]);
  $form_controller = new FormController();
  $form_data = $form_controller->get();

  $format_admin = $template->get('admin');
  $format_user = $template->get('user');
?>

<div class="p-[30px]">
  <h1 class="font-bold text-[30px]">サンプルフォーム（確認）</h1>

  <?php
    echo '<pre class="text-[10px]"><code class="language-php">';
    echo '&lt;?php' . PHP_EOL;
    var_dump($form_data);
    echo PHP_EOL . '?&gt;</code></pre>';

    echo '<pre><code class="language-php">';
    echo '&lt;?php' . PHP_EOL;
    var_dump($format_admin);
    echo '---' . PHP_EOL;
    var_dump($format_user);
    echo PHP_EOL . '?&gt;</code></pre>';
  ?>
</div>

<?php get_footer(); ?>