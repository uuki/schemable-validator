<?php

namespace SchemableValidator\I18n;

/**
 * Immutable dictionary for per-field / per-rule error message overrides.
 *
 * resolve() priority:
 *   1. $definitions[$field][$ruleId]  — field + rule specific
 *   2. $definitions[$field] (string)  — field-wide shorthand
 *   3. $defaults[$ruleId]             — locale preset
 *   4. $fallback                      — Respect default message
 */
final class MessageDict {
  /** @var array<string, string|array<string, string>> */
  private array $definitions;

  /** @var array<string, string> */
  private array $defaults;

  public function __construct(array $definitions = [], array $defaults = []) {
    $this->definitions = $definitions;
    $this->defaults    = $defaults;
  }

  public function resolve(string $field, string $ruleId, string $fallback): string {
    $def = $this->definitions[$field] ?? null;

    if (is_array($def) && isset($def[$ruleId])) {
      return $def[$ruleId];
    }

    if (is_string($def)) {
      return $def;
    }

    return $this->defaults[$ruleId] ?? $fallback;
  }

  public function merge(array $definitions): self {
    $merged = $this->definitions;
    foreach ($definitions as $field => $value) {
      if (is_array($value) && isset($merged[$field]) && is_array($merged[$field])) {
        $merged[$field] = array_merge($merged[$field], $value);
      } else {
        $merged[$field] = $value;
      }
    }
    return new self($merged, $this->defaults);
  }

  public static function ja(array $definitions = []): self {
    return new self($definitions, require __DIR__ . '/Locales/ja.php');
  }

  public static function en(array $definitions = []): self {
    return new self($definitions, require __DIR__ . '/Locales/en.php');
  }
}
