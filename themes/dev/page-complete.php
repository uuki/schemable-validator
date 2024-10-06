<?php
/* Template Name: form-complete */
  get_header();

  /**
   * Example
   */
  use SchemableValidator\FormController;
  $form_controller = new FormController();
  $form_controller->clear();
?>

<div class="p-[30px]">
  <h1 class="font-bold text-[30px]">サンプルフォーム（完了）</h1>
</div>

<?php get_footer(); ?>