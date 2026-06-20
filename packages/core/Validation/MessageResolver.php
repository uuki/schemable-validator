<?php

namespace SchemableValidator\Validation;

use SchemableValidator\I18n\DefaultMessages;
use SchemableValidator\I18n\MessageDict;

/**
 * Shared message resolution chain used by all ExecutableValidator implementations.
 *
 * Resolution priority (highest first):
 *   1. MessageDict (user overrides, per field + ruleId)
 *   2. Inline errorMessage (per field, keyed by JSON Schema keyword)
 *   3. DefaultMessages catalog (canonical engine-neutral templates)
 *   4. Engine fallback (Respect/opis text, or a generic string)
 *
 * {var} placeholders are interpolated on whichever template wins.
 */
final class MessageResolver {
  /**
   * @param ?MessageDict $dict            User message overrides (null = none).
   * @param string       $field           Field name being validated.
   * @param string       $ruleId          Engine-neutral rule identifier (e.g. 'minLength', 'email').
   * @param ?string      $keyword         JSON Schema keyword for inline errorMessage lookup (may differ from ruleId).
   * @param array<string, int|float|string> $vars  Substitution values for {var} placeholders.
   * @param array<string, array<string, string>> $inlineMessages  Per-field inline errorMessage map.
   * @param string       $engineFallback  Last-resort message from the validation engine.
   */
  public static function resolve(
    ?MessageDict $dict,
    string $field,
    string $ruleId,
    ?string $keyword,
    array $vars,
    array $inlineMessages,
    string $engineFallback
  ): string {
    // Build the template from bottom up (lowest priority first, overwritten by higher).
    $template = $engineFallback;

    // 3. DefaultMessages catalog (neutral ruleId)
    $catalog = DefaultMessages::template($ruleId);
    if ($catalog !== null) {
      $template = $catalog;
    }

    // 2. Inline errorMessage (keyword-based, per field)
    if ($keyword !== null && isset($inlineMessages[$field][$keyword])) {
      $template = $inlineMessages[$field][$keyword];
    }

    // 1. MessageDict (highest priority) — resolve() handles field+ruleId lookup
    //    and falls back to the $template assembled above.
    if ($dict !== null) {
      return $dict->resolve($field, $ruleId, $template, $vars);
    }

    return MessageDict::interpolate($template, $vars);
  }
}
