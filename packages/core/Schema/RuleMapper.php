<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;
use SchemableValidator\Rules\FileExtension;

/**
 * Single source of truth for Respect/Validation ↔ JSON Schema compatibility.
 * Add an entry here to support a new rule in both engines.
 */
final class RuleMapper {
  /**
   * Resolve a rule name + arguments into a RuleMapping pair.
   *
   * @param string $rule Rule name (e.g. 'string', 'email', 'min')
   * @param array  $args Rule arguments
   * @throws \InvalidArgumentException for unknown rule names
   */
  public static function resolve(string $rule, array $args): RuleMapping {
    switch ($rule) {
      // --- Primitive types ---
      case 'string':
        return new RuleMapping(v::stringType(), ['type' => 'string']);
      case 'integer':
        return new RuleMapping(v::intType(), ['type' => 'integer']);
      case 'number':
        return new RuleMapping(v::numericVal(), ['type' => 'number']);
      case 'boolean':
        return new RuleMapping(v::boolType(), ['type' => 'boolean']);

      // --- String formats ---
      case 'email':
        return new RuleMapping(v::email(), ['type' => 'string', 'format' => 'email']);
      case 'url':
        return new RuleMapping(v::url(), ['type' => 'string', 'format' => 'uri']);

      // --- String length (args: [?int $min, ?int $max]) ---
      case 'length':
        $min = $args[0] ?? null;
        $max = $args[1] ?? null;
        $js  = array_filter(
          ['minLength' => $min, 'maxLength' => $max],
          fn($v) => $v !== null,
        );
        return new RuleMapping(v::length($min, $max), $js);

      // --- Numeric bounds (args: [int|float $n]) ---
      case 'min':
        return new RuleMapping(v::min($args[0]), ['minimum' => $args[0]]);
      case 'max':
        return new RuleMapping(v::max($args[0]), ['maximum' => $args[0]]);

      // --- Pattern (args: [string $pattern]) — raw regex without delimiters ---
      case 'pattern':
        return new RuleMapping(
          v::regex('/' . $args[0] . '/u'),
          ['pattern' => $args[0]],
        );

      // --- Enumeration (args: [array $values]) ---
      case 'in':
        return new RuleMapping(v::in($args[0]), ['enum' => $args[0]]);

      // --- File MIME type — no JSON Schema equivalent (args: [array $mimeTypes]) ---
      case 'fileExt':
        return self::buildFileExtMapping($args[0]);

      default:
        if (in_array($rule, RuleCatalog::todo(), true)) {
          throw new \RuntimeException(
            "Rule '{$rule}' is catalogued as TODO in RuleCatalog but not yet implemented. " .
            "Use SV::respect(v::{$rule}(...)) as an escape hatch, or add the case to RuleMapper."
          );
        }
        throw new \InvalidArgumentException(
          "Unknown rule: '{$rule}'. Not in RuleCatalog. " .
          "If this is a valid Respect/Validation rule, add it to RuleCatalog first, " .
          "then implement it in RuleMapper."
        );
    }
  }

  private static function buildFileExtMapping(array $accept): RuleMapping {
    $fv = v::create();
    $fv->addRule(new FileExtension($accept));
    return new RuleMapping($fv, null);
  }
}
