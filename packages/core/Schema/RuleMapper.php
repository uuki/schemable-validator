<?php

namespace SchemableValidator\Schema;

/**
 * Single source of truth for rule name -> JSON Schema fragment mapping.
 *
 * Execution (rule + args -> Respect/Opis/... validator) is a BackendAdapter's
 * responsibility; see Validation/Adapters/RespectAdapter::compileDescriptor().
 */
final class RuleMapper {
  /**
   * Resolve a rule name + arguments into a RuleMapping descriptor.
   *
   * @param string $rule Rule name (e.g. 'string', 'email', 'min')
   * @param array  $args Rule arguments
   * @throws \InvalidArgumentException for unknown rule names
   */
  public static function resolve(string $rule, array $args): RuleMapping {
    switch ($rule) {
      // --- Primitive types ---
      case 'string':
        return new RuleMapping($rule, $args, ['type' => 'string']);
      case 'integer':
        return new RuleMapping($rule, $args, ['type' => 'integer']);
      case 'number':
        return new RuleMapping($rule, $args, ['type' => 'number']);
      case 'boolean':
        return new RuleMapping($rule, $args, ['type' => 'boolean']);

      // --- String formats ---
      case 'email':
        return new RuleMapping($rule, $args, ['type' => 'string', 'format' => 'email']);
      case 'url':
        return new RuleMapping($rule, $args, ['type' => 'string', 'format' => 'uri']);

      // --- String length (args: [?int $min, ?int $max]) ---
      case 'length':
        $min = $args[0] ?? null;
        $max = $args[1] ?? null;
        $js  = array_filter(
          ['minLength' => $min, 'maxLength' => $max],
          fn($v) => $v !== null,
        );
        return new RuleMapping($rule, $args, $js);

      // --- Numeric bounds (args: [int|float $n]) ---
      case 'min':
        return new RuleMapping($rule, $args, ['minimum' => $args[0]]);
      case 'max':
        return new RuleMapping($rule, $args, ['maximum' => $args[0]]);

      // --- Pattern (args: [string $pattern]) — raw regex without delimiters ---
      case 'pattern':
        return new RuleMapping($rule, $args, ['pattern' => $args[0]]);

      // --- String formats ---
      case 'date':
        return new RuleMapping($rule, $args, ['format' => 'date']);
      case 'dateTime':
        return new RuleMapping($rule, $args, ['format' => 'date-time']);
      case 'time':
        return new RuleMapping($rule, $args, ['format' => 'time']);
      case 'uuid':
        return new RuleMapping($rule, $args, ['format' => 'uuid']);
      case 'ipv4':
        return new RuleMapping($rule, $args, ['format' => 'ipv4']);
      case 'ipv6':
        return new RuleMapping($rule, $args, ['format' => 'ipv6']);
      case 'slug':
        return new RuleMapping($rule, $args, ['pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$']);
      case 'domain':
        return new RuleMapping($rule, $args, ['format' => 'hostname']);

      // --- Enumeration (args: [array $values]) ---
      case 'in':
        return new RuleMapping($rule, $args, ['enum' => $args[0]]);

      // --- File MIME type — no JSON Schema equivalent (args: [array $mimeTypes]) ---
      case 'fileExt':
        return new RuleMapping($rule, $args, null);

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
}
