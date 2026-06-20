<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Schema\RuleMapper;
use SchemableValidator\Adapters\Respect\RespectAdapter;

/**
 * Round-trip coverage for RespectAdapter::jsonSchemaPropertyToDescriptors()
 * (the Validator::fromJsonSchema() path for raw JSON Schema input).
 *
 * For every STATUS_MAPPED rule, RuleMapper::resolve() produces a JSON Schema
 * fragment (RuleMapping::$jsonSchema). jsonSchemaPropertyToDescriptors() must
 * be able to turn that fragment back into descriptors that compile to a
 * validator equivalent to compiling the rule directly.
 *
 * FAILS WHEN: a new `type` or `format` value is introduced via
 * RuleMapper::resolve() (i.e. a rule is moved to STATUS_MAPPED) but
 * jsonSchemaPropertyToDescriptors() has no matching switch case — the
 * fragment silently loses that constraint on the fromJsonSchema() path.
 *
 * Reuses RuleMapperCompatibilityTest::mappedRulesProvider(), so any rule
 * added there — already required for every STATUS_MAPPED rule — is
 * automatically covered here too.
 */
class JsonSchemaRoundTripTest extends TestCase {
  /**
   * 'min'/'max' carry no 'type' of their own in RuleMapping::$jsonSchema —
   * they are bound constraints layered onto IntegerSchema/NumberSchema.
   * Every other mapped rule either declares its own 'type' (string/integer/
   * number/boolean/email/url) or is layered onto a string base
   * (StringSchema/EnumSchema). This map supplies the base 'type' each rule
   * is realistically combined with when building the property fragment.
   */
  private const BASE_TYPE = [
    'min' => 'integer',
    'max' => 'integer',
  ];

  /**
   * @dataProvider mappedRulesProvider
   */
  public function test_mapped_rule_json_schema_round_trips(
    string $rule,
    array  $args,
    $valid,
    $invalid
  ): void {
    $mapping = RuleMapper::resolve($rule, $args);
    $this->assertTrue($mapping->isMappable(), "Rule '{$rule}': STATUS_MAPPED rules must produce a jsonSchema fragment");

    $base = self::BASE_TYPE[$rule] ?? 'string';
    $prop = array_merge(['type' => $base], $mapping->jsonSchema);

    $direct    = RespectAdapter::compileDescriptor($mapping->rule, $mapping->args);
    $roundTrip = RespectAdapter::compileProperty($prop);

    $this->assertTrue(
      $roundTrip->validate($valid),
      "Rule '{$rule}': fromJsonSchema-compiled validator should accept valid input"
    );
    $this->assertFalse(
      $roundTrip->validate($invalid),
      "Rule '{$rule}': fromJsonSchema-compiled validator should reject invalid input"
    );

    $this->assertSame(
      $direct->validate($valid),
      $roundTrip->validate($valid),
      "Rule '{$rule}': direct and fromJsonSchema validators disagree on valid input"
    );
    $this->assertSame(
      $direct->validate($invalid),
      $roundTrip->validate($invalid),
      "Rule '{$rule}': direct and fromJsonSchema validators disagree on invalid input"
    );
  }

  /** @return array<int, array{0: string, 1: array, 2: mixed, 3: mixed}> */
  public static function mappedRulesProvider(): array {
    return RuleMapperCompatibilityTest::mappedRulesProvider();
  }
}
