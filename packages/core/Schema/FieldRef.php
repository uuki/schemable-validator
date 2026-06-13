<?php

namespace SchemableValidator\Schema;

/** Marker that refers to another field's runtime value in a when() condition. */
final class FieldRef {
  public string $name;

  public function __construct(string $name) {
    $this->name = $name;
  }
}
