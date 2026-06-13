<?php

namespace SchemableValidator\Schema;

/**
 * Complete registry of every Respect/Validation leaf rule (v2.x).
 *
 * STATUS_MAPPED      – Respect rule + JSON Schema fragment, fully wired in RuleMapper.
 * STATUS_UNMAPPABLE  – Respect validates it; JSON Schema has no equivalent keyword.
 * STATUS_TODO        – Known, useful rule; not yet wired in RuleMapper (escape-hatch available).
 * STATUS_EXCLUDED    – Intentionally out of scope for RuleMapper (see inline comment).
 *
 * RULES FOR MAINTAINING THIS FILE
 * - Every leaf rule class in Respect/Validation must appear here.
 *   RuleMapperCompatibilityTest::test_all_respect_rules_are_in_catalog() enforces this.
 * - 'respect' = camelCase method name (lcfirst of the class file name), or null for custom rules.
 * - When Respect upgrades and adds/removes rules, the test above will fail and guide the fix.
 *
 * WORKFLOW FOR IMPLEMENTING A TODO RULE
 * 1. Move its entry to STATUS_MAPPED (or STATUS_UNMAPPABLE if appropriate)
 * 2. test_all_mapped_rules_have_json_schema()   → fails → add switch case to RuleMapper
 * 3. test_provider_covers_all_mapped_rules()    → fails → add fixture to mappedRulesProvider()
 * → all green = fully covered and regression-tested
 */
final class RuleCatalog {
  const STATUS_MAPPED     = 'mapped';
  const STATUS_UNMAPPABLE = 'unmappable';
  const STATUS_TODO       = 'todo';
  const STATUS_EXCLUDED   = 'excluded';

  /**
   * Full catalog keyed by our alias name.
   * 'respect' is the Respect method name (null = custom rule not in Respect).
   *
   * @return array<string, array{status: string, respect: string|null}>
   */
  public static function entries(): array {
    return [
      // ═══════════════════════════════════════════════════════
      // MAPPED  — alias => Respect method name, has JSON Schema
      // ═══════════════════════════════════════════════════════
      'string'     => ['status' => self::STATUS_MAPPED, 'respect' => 'stringType'],
      'integer'    => ['status' => self::STATUS_MAPPED, 'respect' => 'intType'],
      'number'     => ['status' => self::STATUS_MAPPED, 'respect' => 'numericVal'],
      'boolean'    => ['status' => self::STATUS_MAPPED, 'respect' => 'boolType'],
      'email'      => ['status' => self::STATUS_MAPPED, 'respect' => 'email'],
      'url'        => ['status' => self::STATUS_MAPPED, 'respect' => 'url'],
      'length'     => ['status' => self::STATUS_MAPPED, 'respect' => 'length'],
      'min'        => ['status' => self::STATUS_MAPPED, 'respect' => 'min'],
      'max'        => ['status' => self::STATUS_MAPPED, 'respect' => 'max'],
      'pattern'    => ['status' => self::STATUS_MAPPED, 'respect' => 'regex'],
      'in'         => ['status' => self::STATUS_MAPPED, 'respect' => 'in'],

      // ═══════════════════════════════════════════════════════
      // UNMAPPABLE  — Respect validates it; no JSON Schema keyword
      // ═══════════════════════════════════════════════════════
      'fileExt'    => ['status' => self::STATUS_UNMAPPABLE, 'respect' => null],       // custom MIME rule
      'base64'     => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'base64'],   // no JSON Schema keyword
      'creditCard' => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'creditCard'], // Luhn + format check
      'extension'  => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'extension'], // file extension check
      'image'      => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'image'],    // binary image check
      'mimetype'   => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'mimetype'], // file MIME type
      'phone'      => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'phone'],    // no standard JSON Schema format
      'uploaded'   => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'uploaded'], // HTTP upload check
      'unique'     => ['status' => self::STATUS_UNMAPPABLE, 'respect' => 'unique'],   // uniqueItems applies to arrays, not scalars

      // ═══════════════════════════════════════════════════════
      // TODO  — useful rule; not yet implemented (use SV::respect() escape hatch)
      //         Proposed JSON Schema mapping noted in comment.
      // ═══════════════════════════════════════════════════════
      'alnum'      => ['status' => self::STATUS_TODO, 'respect' => 'alnum'],      // { pattern: "^[a-zA-Z0-9]+$" }
      'alpha'      => ['status' => self::STATUS_TODO, 'respect' => 'alpha'],      // { pattern: "^[a-zA-Z]+$" }
      'between'    => ['status' => self::STATUS_TODO, 'respect' => 'between'],    // { minimum, maximum }
      'boolVal'    => ['status' => self::STATUS_TODO, 'respect' => 'boolVal'],    // { type: "boolean" } (accepts "true"/"1")
      'contains'   => ['status' => self::STATUS_TODO, 'respect' => 'contains'],   // no direct equivalent; pattern workaround
      'containsAny'=> ['status' => self::STATUS_TODO, 'respect' => 'containsAny'],// no direct equivalent
      'date'       => ['status' => self::STATUS_TODO, 'respect' => 'date'],       // { format: "date" }
      'dateTime'   => ['status' => self::STATUS_TODO, 'respect' => 'dateTime'],   // { format: "date-time" }
      'decimal'    => ['status' => self::STATUS_TODO, 'respect' => 'decimal'],    // { type: "number", ... }
      'digit'      => ['status' => self::STATUS_TODO, 'respect' => 'digit'],      // { pattern: "^[0-9]+$" }
      'domain'     => ['status' => self::STATUS_TODO, 'respect' => 'domain'],     // { format: "hostname" }
      'endsWith'   => ['status' => self::STATUS_TODO, 'respect' => 'endsWith'],   // { pattern: "suffix$" }
      'equals'     => ['status' => self::STATUS_TODO, 'respect' => 'equals'],     // { const: value }
      'greaterThan'=> ['status' => self::STATUS_TODO, 'respect' => 'greaterThan'],// { exclusiveMinimum: n }
      'identical'  => ['status' => self::STATUS_TODO, 'respect' => 'identical'],  // { const: value } (strict)
      'ip'         => ['status' => self::STATUS_TODO, 'respect' => 'ip'],         // { format: "ipv4" | "ipv6" }
      'json'       => ['status' => self::STATUS_TODO, 'respect' => 'json'],       // { type: "string" } (structure not expressible)
      'lessThan'   => ['status' => self::STATUS_TODO, 'respect' => 'lessThan'],   // { exclusiveMaximum: n }
      'lowercase'  => ['status' => self::STATUS_TODO, 'respect' => 'lowercase'],  // { pattern: "^[a-z\\s]+$" }
      'negative'   => ['status' => self::STATUS_TODO, 'respect' => 'negative'],   // { exclusiveMaximum: 0 }
      'noWhitespace'=> ['status' => self::STATUS_TODO, 'respect' => 'noWhitespace'], // { pattern: "^\\S+$" }
      'notBlank'   => ['status' => self::STATUS_TODO, 'respect' => 'notBlank'],   // rejects blank-string / null / false
      'notEmpty'   => ['status' => self::STATUS_TODO, 'respect' => 'notEmpty'],   // { minLength: 1 }
      'notEmoji'   => ['status' => self::STATUS_TODO, 'respect' => 'notEmoji'],   // { pattern: no-emoji } — no JSON Schema equivalent
      'notOptional'=> ['status' => self::STATUS_TODO, 'respect' => 'notOptional'],// alias for required / notNull
      'positive'   => ['status' => self::STATUS_TODO, 'respect' => 'positive'],   // { exclusiveMinimum: 0 }
      'size'       => ['status' => self::STATUS_TODO, 'respect' => 'size'],       // file size — likely UNMAPPABLE
      'slug'       => ['status' => self::STATUS_TODO, 'respect' => 'slug'],       // { pattern: "^[a-z0-9-]+$" }
      'sorted'     => ['status' => self::STATUS_TODO, 'respect' => 'sorted'],     // no JSON Schema equivalent
      'startsWith' => ['status' => self::STATUS_TODO, 'respect' => 'startsWith'], // { pattern: "^prefix" }
      'subset'     => ['status' => self::STATUS_TODO, 'respect' => 'subset'],     // { items: { enum: [...] } } for arrays
      'time'       => ['status' => self::STATUS_TODO, 'respect' => 'time'],       // { format: "time" }
      'uppercase'  => ['status' => self::STATUS_TODO, 'respect' => 'uppercase'],  // { pattern: "^[A-Z\\s]+$" }
      'uuid'       => ['status' => self::STATUS_TODO, 'respect' => 'uuid'],       // { format: "uuid" }
      'version'    => ['status' => self::STATUS_TODO, 'respect' => 'version'],    // { pattern: "semver" }

      // ═══════════════════════════════════════════════════════
      // EXCLUDED  — intentionally out of scope; not relevant for web form validation.
      //             'respect' is still recorded to track Respect API changes.
      // ═══════════════════════════════════════════════════════

      // Testing utilities — not real-world validation rules
      'alwaysInvalid'   => ['status' => self::STATUS_EXCLUDED, 'respect' => 'alwaysInvalid'],
      'alwaysValid'     => ['status' => self::STATUS_EXCLUDED, 'respect' => 'alwaysValid'],

      // PHP structural / callable
      'attribute'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'attribute'],   // PHP object attribute
      'call'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'call'],        // PHP callable wrapper
      'callableType'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'callableType'],
      'callback'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'callback'],    // arbitrary PHP callback
      'instance'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'instance'],    // PHP instanceof
      'sf'              => ['status' => self::STATUS_EXCLUDED, 'respect' => 'sf'],          // Symfony Framework validator
      'type'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'type'],        // PHP gettype() check
      'filterVar'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'filterVar'],   // PHP filter_var wrapper

      // PHP type duplicates — use our typed schemas instead (string/integer/number/boolean)
      'arrayType'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'arrayType'],
      'arrayVal'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'arrayVal'],
      'floatType'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'floatType'],   // use number
      'floatVal'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'floatVal'],    // use number
      'intVal'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'intVal'],      // use integer
      'iterableType'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'iterableType'],
      'nullType'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'nullType'],
      'numberAlias'     => ['status' => self::STATUS_EXCLUDED, 'respect' => 'number'],      // Respect's `number` rule = alias for numericVal; conflicts with our 'number' alias
      'objectType'      => ['status' => self::STATUS_EXCLUDED, 'respect' => 'objectType'],
      'resourceType'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'resourceType'],
      'scalarVal'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'scalarVal'],
      'stringVal'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'stringVal'],   // use string
      'falseVal'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'falseVal'],    // use boolean
      'trueVal'         => ['status' => self::STATUS_EXCLUDED, 'respect' => 'trueVal'],     // use boolean

      // Filesystem — server-side, not web form data
      'directory'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'directory'],
      'executable'      => ['status' => self::STATUS_EXCLUDED, 'respect' => 'executable'],
      'exists'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'exists'],
      'file'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'file'],
      'readable'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'readable'],
      'symbolicLink'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'symbolicLink'],
      'writable'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'writable'],

      // National / government IDs — domain-specific
      'bsn'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'bsn'],         // Dutch BSN
      'cnh'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'cnh'],         // Brazilian driver's license
      'cnpj'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'cnpj'],        // Brazilian company ID
      'cpf'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'cpf'],         // Brazilian individual ID
      'iban'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'iban'],        // bank account
      'imei'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'imei'],        // device IMEI
      'isbn'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'isbn'],        // book ISBN
      'luhn'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'luhn'],        // Luhn algorithm
      'nfeAccessKey'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'nfeAccessKey'],// Brazilian NF-e
      'nif'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'nif'],         // Portuguese/Spanish tax ID
      'nip'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'nip'],         // Polish tax ID
      'pesel'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'pesel'],       // Polish national ID
      'phpLabel'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'phpLabel'],    // PHP identifier
      'pis'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'pis'],         // Brazilian PIS
      'polishIdCard'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'polishIdCard'],
      'portugueseNif'   => ['status' => self::STATUS_EXCLUDED, 'respect' => 'portugueseNif'],

      // Locale-specific codes (use SV::enum() to validate a known list instead)
      'countryCode'     => ['status' => self::STATUS_EXCLUDED, 'respect' => 'countryCode'],
      'currencyCode'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'currencyCode'],
      'languageCode'    => ['status' => self::STATUS_EXCLUDED, 'respect' => 'languageCode'],
      'postalCode'      => ['status' => self::STATUS_EXCLUDED, 'respect' => 'postalCode'],  // locale-specific format
      'subdivisionCode' => ['status' => self::STATUS_EXCLUDED, 'respect' => 'subdivisionCode'],

      // Mathematical / number theory — rarely needed in web forms
      'even'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'even'],
      'factor'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'factor'],
      'fibonacci'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'fibonacci'],
      'finite'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'finite'],
      'infinite'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'infinite'],
      'multiple'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'multiple'],
      'odd'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'odd'],
      'perfectSquare'   => ['status' => self::STATUS_EXCLUDED, 'respect' => 'perfectSquare'],
      'primeNumber'     => ['status' => self::STATUS_EXCLUDED, 'respect' => 'primeNumber'],

      // Character classification — too low-level for schema-level validation
      'base'            => ['status' => self::STATUS_EXCLUDED, 'respect' => 'base'],        // number base (hex etc.)
      'charset'         => ['status' => self::STATUS_EXCLUDED, 'respect' => 'charset'],     // character encoding
      'consonant'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'consonant'],
      'control'         => ['status' => self::STATUS_EXCLUDED, 'respect' => 'control'],     // control characters
      'countable'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'countable'],   // PHP Countable
      'graph'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'graph'],       // printable non-space
      'printable'       => ['status' => self::STATUS_EXCLUDED, 'respect' => 'printable'],
      'punct'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'punct'],       // punctuation
      'roman'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'roman'],       // Roman numerals
      'space'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'space'],       // only whitespace
      'vowel'           => ['status' => self::STATUS_EXCLUDED, 'respect' => 'vowel'],
      'xdigit'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'xdigit'],     // hex digits

      // Network / specialised formats
      'hexRgbColor'     => ['status' => self::STATUS_EXCLUDED, 'respect' => 'hexRgbColor'], // CSS color
      'macAddress'      => ['status' => self::STATUS_EXCLUDED, 'respect' => 'macAddress'],
      'tld'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'tld'],         // top-level domain alone
      'videoUrl'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'videoUrl'],    // too specialised

      // Date specialisations unlikely to be needed in schemas
      'leapDate'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'leapDate'],
      'leapYear'        => ['status' => self::STATUS_EXCLUDED, 'respect' => 'leapYear'],
      'maxAge'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'maxAge'],
      'minAge'          => ['status' => self::STATUS_EXCLUDED, 'respect' => 'minAge'],

      // Loose equality / logic-negation equivalents — implementation-detail
      'equivalent'      => ['status' => self::STATUS_EXCLUDED, 'respect' => 'equivalent'],  // PHP loose ==
      'no'              => ['status' => self::STATUS_EXCLUDED, 'respect' => 'no'],          // 'no'/'n'/'nein'
      'yes'             => ['status' => self::STATUS_EXCLUDED, 'respect' => 'yes'],         // 'yes'/'y'/'ja'
    ];
  }

  /**
   * Flat alias => status map (backward-compatible with existing callers).
   *
   * @return array<string, string>
   */
  public static function all(): array {
    return array_map(fn($e) => $e['status'], self::entries());
  }

  /** @return string[] */
  public static function byStatus(string $status): array {
    return array_keys(array_filter(self::all(), fn($s) => $s === $status));
  }

  /** @return string[] Rules that produce full JSON Schema output */
  public static function mapped(): array {
    return self::byStatus(self::STATUS_MAPPED);
  }

  /** @return string[] Rules with no JSON Schema equivalent */
  public static function unmappable(): array {
    return self::byStatus(self::STATUS_UNMAPPABLE);
  }

  /** @return string[] Known Respect rules not yet implemented in RuleMapper */
  public static function todo(): array {
    return self::byStatus(self::STATUS_TODO);
  }

  /** @return string[] Rules intentionally excluded from RuleMapper scope */
  public static function excluded(): array {
    return self::byStatus(self::STATUS_EXCLUDED);
  }

  /**
   * Return the Respect method name for a given catalog alias, or null.
   */
  public static function respectMethodFor(string $alias): ?string {
    return self::entries()[$alias]['respect'] ?? null;
  }

  /**
   * Scan the installed Respect/Validation package and return all leaf rule names
   * as camelCase method names (lcfirst of each class file name).
   * Composite and structural rule classes are excluded.
   *
   * @return string[]
   */
  public static function scanRespectRules(): array {
    $reflector = new \ReflectionClass(\Respect\Validation\Validator::class);
    $rulesDir  = dirname($reflector->getFileName()) . '/Rules';
    $namespace = 'Respect\\Validation\\Rules\\';

    // Composite/structural classes excluded by exact name (not prefix match,
    // so leaf rules like NotEmpty are NOT accidentally excluded).
    $skipExact = [
      'Not', 'Nullable', 'Optional', 'When', 'Each',
      'Key', 'KeyNested', 'KeySet', 'KeyValue',
      'AllOf', 'AnyOf', 'OneOf', 'NoneOf',
    ];

    $rules = [];
    foreach (glob($rulesDir . '/*.php') as $file) {
      $class = basename($file, '.php');
      if (in_array($class, $skipExact, true)) {
        continue;
      }
      // Skip abstract classes (AbstractRule, AbstractComposite, etc.)
      $fqcn = $namespace . $class;
      if (!class_exists($fqcn)) {
        continue;
      }
      if ((new \ReflectionClass($fqcn))->isAbstract()) {
        continue;
      }
      $rules[] = lcfirst($class);
    }

    sort($rules);
    return $rules;
  }
}
