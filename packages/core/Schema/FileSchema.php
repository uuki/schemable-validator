<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;
use SchemableValidator\Rules\FileExtension;

final class FileSchema extends AbstractFieldSchema implements UnmappableField {
  /** @var array */
  private $accept;

  /**
   * Optional image constraints dispatched to ImageDriver after MIME acceptance.
   * Keys: maxWidth, maxHeight, minWidth, minHeight (px), maxSize (bytes).
   * @var array<string, int>
   */
  private $imageConstraints;

  public function __construct(array $accept = [], array $imageConstraints = []) {
    $this->accept           = $accept;
    $this->imageConstraints = $imageConstraints;
  }

  public function isMappable(): bool {
    return false;
  }

  /** @return string[] Allowed MIME types (empty = any). Consumed by FileValidationDriver. */
  public function getAccept(): array {
    return $this->accept;
  }

  /** @return array<string, int> Image constraints (empty if none set). Consumed by ImageDriver. */
  public function getImageConstraints(): array {
    return $this->imageConstraints;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(new FileExtension($this->accept));
    return $chain;
  }

  public function toJsonSchema(): array {
    return [];
  }
}
