<?php

namespace SchemableValidator\Schema;

abstract class AbstractFieldSchema {
  /** @var bool */
  protected $required = true;

  /** @var bool */
  protected $nullable = false;

  /** @var string|null */
  private $label = null;

  /** @var array<string, string> */
  private $errorMessages = [];

  /** @return $this */
  public function optional() {
    $this->required = false;
    return $this;
  }

  /** @return $this */
  public function nullable() {
    $this->nullable = true;
    return $this;
  }

  /** @return $this */
  public function label(string $label) {
    $this->label = $label;
    return $this;
  }

  /**
   * Attach inline error messages keyed by JSON Schema keyword (AJV ajv-errors convention).
   * Example: ['format' => '有効なメールアドレスを入力してください', 'minLength' => '3文字以上必要です']
   * Resolution order: MessageDict > errorMessage > default.
   *
   * @param array<string, string> $map
   * @return $this
   */
  public function errorMessages(array $map) {
    $this->errorMessages = $map;
    return $this;
  }

  public function isRequired(): bool {
    return $this->required;
  }

  public function isMappable(): bool {
    return true;
  }

  public function getLabel(): ?string {
    return $this->label;
  }

  abstract public function toJsonSchema(): array;

  protected function applyNullable(array $schema): array {
    if ($this->nullable && isset($schema['type']) && is_string($schema['type'])) {
      $schema['type'] = [$schema['type'], 'null'];
    }
    return $schema;
  }

  protected function applyErrorMessages(array $schema): array {
    if (!empty($this->errorMessages)) {
      $schema['errorMessage'] = $this->errorMessages;
    }
    return $schema;
  }
}
