<?php
namespace SchemableValidator;

require_once __DIR__ . '/constants.php';
require_once SV_VENDOR_DIR . '/autoload.php';

use SchemableValidator\Controllers\FormController;
use SchemableValidator\Interfaces\WordPress as WordpressInterface;

/**
 * Class Template
 *
 * For handling email reply formats.
 */
final class Template {
  use Helpers\Environment;

  private array $options;

  /** @var \SchemableValidator\Interfaces\AbstractInterface|null */
  private $interface;

  /** @var array<string, mixed>|null */
  private $data;

  private array $defaultOptions = [
    'aliases' => [
      'name' => 'name',
      'email' => 'email',
      'body' => 'body'
    ],
    'templates' => [],
  ];

  /**
   * Template constructor.
   *
   * @param array<string, mixed> $options An associative array of options for the template. This will merge with default options.
   */
  function __construct(array $options = [
      'aliases' => [],
      'templates' => []
    ]) {

    $form_controller = new FormController();
    $this->options = array_merge($this->defaultOptions, $options);

    $this->interface = null;
    $this->data = $form_controller->get();

    $env_name = $this->getEnvironment();

    if ($env_name === 'wordpress') {
      $this->interface = new WordpressInterface($this->options['templates']);
    }
  }

  /**
   * Retrieves a formatted template by name.
   *
   * @param string $template_name The name of the template to retrieve.
   *
   * @return string The formatted template with data replacements based on defined aliases.
   */
  function get(string $template_name): string {
    $format = isset($this->interface) ? $this->interface->getTemplate($template_name) : $this->options['templates'][$template_name];
    $body = $format;

    foreach ($this->options['aliases'] as $key => $value) {
      $field = $this->data[$value] ?? null;

      if (isset($field)) {
        // Strip CR/LF to prevent email header injection.
        $safe = str_replace(["\r", "\n"], '', (string) $field['value']);
        $body = str_replace('{'.$key.'}', $safe, $body);
      }
    }
    return $body;
  }

  function getAll(): array {
    return $this->interface->getAll();
  }
}
