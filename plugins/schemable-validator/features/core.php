<?php
namespace SchemableValidator;

require SV_ROOT_DIR . "/vendor/autoload.php";
use Respect\Validation\Validator as v;

class Validator {
  /**
   * @var array<string, v> $schema
   */
  private array $schema;

  /**
   * @param array<string, v> $schema
   */
  function __construct(array $schema = []) {
    $this->schema = $schema;
  }

  function validate(array $data) {
    $result = [];

    foreach($this->schema as $name => $validator) {
      $state = [
        'value' => null,
        'errors' => null,
        'is_valid' => false,
      ];

      $value = isset($data[$name]) ? $this->sanitize($data[$name]) : null;
      $state['value'] = $value;

      try {
        $validator->assert(input: $value);
      } catch(\Respect\Validation\Exceptions\ValidationException $e) {
        $state['errors'] = $e->getFullMessage();
      }

      if (!isset($state['errors'])) {
        $state['is_valid'] = true;
      }

      $result[$name] = $state;
    }

    return $result;
  }

  private function sanitize(string $str) {
    $result = '';
    // HTMLタグを排除
    $result = strip_tags($str);
    // 特殊文字をHTMLエンティティに変換
    $result = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    // 改行、タブなどの制御文字と空白を排除
    $result = trim(preg_replace('/[\r\n\t]/', '', $result));
    return $result;
  }
}