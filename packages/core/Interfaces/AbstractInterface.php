<?php
namespace SchemableValidator\Interfaces;

class AbstractInterface {
  /** @var array<string, string> */
  protected array $templates;

  /** @param array<string, string> $templates */
  function __construct(array $templates) {
    $this->templates = $templates;
  }

  function getTemplate(string $template_name): string {
    return $this->templates[$template_name] ?? '';
  }

  function getAll(): array {
    return $this->templates;
  }
}
