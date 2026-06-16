<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\SchemaBuilder;
use SchemableValidator\SV;
use SchemableValidator\Schema\RuleMapper;
use SchemableValidator\Schema\RuleMapping;
use SchemableValidator\Validation\Adapters\RespectAdapter;
use SchemableValidator\Validator;

class SchemaBuilderTest extends TestCase {
  // ── RuleMapping ─────────────────────────────────────────────

  public function test_ruleMapping_isMappable_true_when_jsonSchema_set(): void {
    $m = new RuleMapping('string', [], ['type' => 'string']);
    $this->assertTrue($m->isMappable());
    $this->assertSame(['type' => 'string'], $m->jsonSchema);
  }

  public function test_ruleMapping_isMappable_false_when_jsonSchema_null(): void {
    $m = new RuleMapping('fileExt', [['image/jpeg']], null);
    $this->assertFalse($m->isMappable());
    $this->assertNull($m->jsonSchema);
  }

  // ── RuleMapper ──────────────────────────────────────────────

  public function test_ruleMapper_string(): void {
    $m = RuleMapper::resolve('string', []);
    $this->assertSame(['type' => 'string'], $m->jsonSchema);
  }

  public function test_ruleMapper_integer(): void {
    $m = RuleMapper::resolve('integer', []);
    $this->assertSame(['type' => 'integer'], $m->jsonSchema);
  }

  public function test_ruleMapper_number(): void {
    $m = RuleMapper::resolve('number', []);
    $this->assertSame(['type' => 'number'], $m->jsonSchema);
  }

  public function test_ruleMapper_boolean(): void {
    $m = RuleMapper::resolve('boolean', []);
    $this->assertSame(['type' => 'boolean'], $m->jsonSchema);
  }

  public function test_ruleMapper_email(): void {
    $m = RuleMapper::resolve('email', []);
    $this->assertSame(['type' => 'string', 'format' => 'email'], $m->jsonSchema);
  }

  public function test_ruleMapper_url(): void {
    $m = RuleMapper::resolve('url', []);
    $this->assertSame(['type' => 'string', 'format' => 'uri'], $m->jsonSchema);
  }

  public function test_ruleMapper_length_both(): void {
    $m = RuleMapper::resolve('length', [2, 50]);
    $this->assertSame(['minLength' => 2, 'maxLength' => 50], $m->jsonSchema);
  }

  public function test_ruleMapper_length_min_only(): void {
    $m = RuleMapper::resolve('length', [2, null]);
    $this->assertSame(['minLength' => 2], $m->jsonSchema);
  }

  public function test_ruleMapper_length_max_only(): void {
    $m = RuleMapper::resolve('length', [null, 50]);
    $this->assertSame(['maxLength' => 50], $m->jsonSchema);
  }

  public function test_ruleMapper_min(): void {
    $m = RuleMapper::resolve('min', [1]);
    $this->assertSame(['minimum' => 1], $m->jsonSchema);
  }

  public function test_ruleMapper_max(): void {
    $m = RuleMapper::resolve('max', [99]);
    $this->assertSame(['maximum' => 99], $m->jsonSchema);
  }

  public function test_ruleMapper_pattern(): void {
    $m = RuleMapper::resolve('pattern', ['^[a-z]+$']);
    $this->assertSame(['pattern' => '^[a-z]+$'], $m->jsonSchema);
  }

  public function test_ruleMapper_in(): void {
    $m = RuleMapper::resolve('in', [['a', 'b', 'c']]);
    $this->assertSame(['enum' => ['a', 'b', 'c']], $m->jsonSchema);
  }

  public function test_ruleMapper_fileExt_is_not_mappable(): void {
    $m = RuleMapper::resolve('fileExt', [['image/jpeg', 'image/png']]);
    $this->assertFalse($m->isMappable());
    $this->assertNull($m->jsonSchema);
  }

  public function test_ruleMapper_unknown_rule_throws(): void {
    $this->expectException(\InvalidArgumentException::class);
    RuleMapper::resolve('unknownRule', []);
  }

  // ── StringSchema ────────────────────────────────────────────

  public function test_stringSchema_basic_jsonSchema(): void {
    $s = SV::string();
    $this->assertSame(['type' => 'string'], $s->toJsonSchema());
  }

  public function test_stringSchema_min_max(): void {
    $s = SV::string()->min(2)->max(50);
    $js = $s->toJsonSchema();
    $this->assertSame('string', $js['type']);
    $this->assertSame(2, $js['minLength']);
    $this->assertSame(50, $js['maxLength']);
  }

  public function test_stringSchema_email(): void {
    $js = SV::string()->email()->toJsonSchema();
    $this->assertSame('string', $js['type']);
    $this->assertSame('email', $js['format']);
  }

  public function test_stringSchema_url(): void {
    $js = SV::string()->url()->toJsonSchema();
    $this->assertSame('uri', $js['format']);
  }

  public function test_stringSchema_pattern(): void {
    $js = SV::string()->pattern('^[a-z]+$')->toJsonSchema();
    $this->assertSame('^[a-z]+$', $js['pattern']);
  }

  public function test_stringSchema_nullable(): void {
    $js = SV::string()->nullable()->toJsonSchema();
    $this->assertSame(['string', 'null'], $js['type']);
  }

  public function test_stringSchema_toRespect_validates_valid(): void {
    $r = RespectAdapter::compileField(SV::string()->min(2));
    $this->assertTrue($r->validate('hello'));
  }

  public function test_stringSchema_toRespect_validates_invalid(): void {
    $r = RespectAdapter::compileField(SV::string()->min(5));
    $this->assertFalse($r->validate('hi'));
  }

  public function test_stringSchema_email_toRespect(): void {
    $r = RespectAdapter::compileField(SV::string()->email());
    $this->assertTrue($r->validate('user@example.com'));
    $this->assertFalse($r->validate('not-an-email'));
  }

  // ── IntegerSchema ───────────────────────────────────────────

  public function test_integerSchema_jsonSchema(): void {
    $js = SV::integer()->min(1)->max(99)->toJsonSchema();
    $this->assertSame('integer', $js['type']);
    $this->assertSame(1, $js['minimum']);
    $this->assertSame(99, $js['maximum']);
  }

  public function test_integerSchema_toRespect_validates(): void {
    $r = RespectAdapter::compileField(SV::integer()->min(1)->max(10));
    $this->assertTrue($r->validate(5));
    $this->assertFalse($r->validate(0));
    $this->assertFalse($r->validate(11));
  }

  // ── NumberSchema ────────────────────────────────────────────

  public function test_numberSchema_jsonSchema(): void {
    $js = SV::number()->min(0.5)->toJsonSchema();
    $this->assertSame('number', $js['type']);
    $this->assertSame(0.5, $js['minimum']);
  }

  // ── BooleanSchema ───────────────────────────────────────────

  public function test_booleanSchema_jsonSchema(): void {
    $this->assertSame(['type' => 'boolean'], SV::boolean()->toJsonSchema());
  }

  public function test_booleanSchema_toRespect(): void {
    $r = RespectAdapter::compileField(SV::boolean());
    $this->assertTrue($r->validate(true));
    // Coercion Contract v1: {true,false,1,0,on,off,yes,no} accepted, case-insensitive.
    $this->assertTrue($r->validate('on'));
    $this->assertFalse($r->validate('maybe'));
  }

  // ── EnumSchema ──────────────────────────────────────────────

  public function test_enumSchema_jsonSchema(): void {
    $js = SV::enum(['a', 'b', 'c'])->toJsonSchema();
    $this->assertSame('string', $js['type']);
    $this->assertSame(['a', 'b', 'c'], $js['enum']);
  }

  public function test_enumSchema_toRespect(): void {
    $r = RespectAdapter::compileField(SV::enum(['general', 'support']));
    $this->assertTrue($r->validate('general'));
    $this->assertFalse($r->validate('unknown'));
  }

  // ── FileSchema ──────────────────────────────────────────────

  public function test_fileSchema_is_not_mappable(): void {
    $this->assertFalse(SV::file(['image/jpeg'])->isMappable());
  }

  public function test_fileSchema_toJsonSchema_returns_empty(): void {
    $this->assertSame([], SV::file(['image/jpeg'])->toJsonSchema());
  }

  // ── RawRespectSchema ────────────────────────────────────────

  public function test_rawRespectSchema_is_not_mappable(): void {
    $this->assertFalse(SV::respect(v::email())->isMappable());
  }

  public function test_rawRespectSchema_toRespect_delegates(): void {
    $r = SV::respect(v::email())->toRespect();
    $this->assertTrue($r->validate('a@b.com'));
  }

  // ── SchemaBuilder constructor validation ────────────────────

  public function test_constructor_rejects_nested_schemaBuilder_field(): void {
    $inner = SV::object(['street' => SV::string()]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Nested SV::object\(\)/');
    SV::object([
      'name'    => SV::string(),
      'address' => $inner,
    ]);
  }

  public function test_constructor_rejects_non_field_schema_value(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches("/field 'name'/");
    SV::object(['name' => 'not-a-field-schema']);
  }

  // ── SchemaBuilder::toJsonSchema() ───────────────────────────

  public function test_schemaBuilder_toJsonSchema_structure(): void {
    $sb = SV::object([
      'name'  => SV::string()->min(2)->max(50),
      'email' => SV::string()->email(),
      'count' => SV::integer()->min(1)->optional(),
    ]);

    $js = $sb->toJsonSchema();

    $this->assertSame('https://json-schema.org/draft/2020-12/schema', $js['$schema']);
    $this->assertSame('object', $js['type']);
    $this->assertArrayHasKey('properties', $js);
    $this->assertSame(['name', 'email'], $js['required']);

    $this->assertSame(['type' => 'string', 'minLength' => 2, 'maxLength' => 50], $js['properties']['name']);
    $this->assertSame(['type' => 'string', 'format' => 'email'], $js['properties']['email']);
    $this->assertArrayNotHasKey('count', array_flip($js['required'] ?? []));
    $this->assertArrayHasKey('count', $js['properties']);
  }

  public function test_schemaBuilder_excludes_unmappable_fields(): void {
    $sb = SV::object([
      'name' => SV::string(),
      'file' => SV::file(['image/jpeg']),
    ]);
    $js = $sb->toJsonSchema();
    $this->assertArrayNotHasKey('file', $js['properties']);
    $this->assertSame(['file'], $js['x-unmapped-fields']);
  }

  public function test_schemaBuilder_toJson_produces_valid_json(): void {
    $sb = SV::object(['name' => SV::string()->min(2)]);
    $json = $sb->toJson();
    $decoded = json_decode($json, true);
    $this->assertIsArray($decoded);
    $this->assertSame('object', $decoded['type']);
  }

  // ── SchemaBuilder::toValidator() ────────────────────────────

  public function test_schemaBuilder_toValidator_returns_validator(): void {
    $sb = SV::object(['name' => SV::string()->min(2)]);
    $validator = $sb->toValidator();
    $this->assertInstanceOf(Validator::class, $validator);
  }

  public function test_schemaBuilder_toValidator_validates_data(): void {
    $sb = SV::object(['name' => SV::string()->min(2)->max(50)]);
    $result = $sb->toValidator()->validate(['name' => 'Alice'])->getResult();
    $this->assertTrue($result['name']['is_valid']);
  }

  public function test_schemaBuilder_toValidator_rejects_invalid_data(): void {
    $sb = SV::object(['name' => SV::string()->min(5)]);
    $result = $sb->toValidator()->validate(['name' => 'Hi'])->getResult();
    $this->assertFalse($result['name']['is_valid']);
  }

  public function test_schemaBuilder_toValidator_with_enum(): void {
    $sb = SV::object(['type' => SV::enum(['a', 'b'])]);
    $result = $sb->toValidator()->validate(['type' => 'a'])->getResult();
    $this->assertTrue($result['type']['is_valid']);

    $result2 = $sb->toValidator()->validate(['type' => 'c'])->getResult();
    $this->assertFalse($result2['type']['is_valid']);
  }

  // ── New string formats ───────────────────────────────────────

  /**
   * @dataProvider stringFormatProvider
   */
  public function test_stringSchema_format_jsonSchema(string $method, string $expectedKey, string $expectedValue): void {
    $js = SV::string()->{$method}()->toJsonSchema();
    $this->assertSame($expectedValue, $js[$expectedKey] ?? null);
  }

  public static function stringFormatProvider(): array {
    return [
      ['date',     'format',  'date'      ],
      ['dateTime', 'format',  'date-time' ],
      ['time',     'format',  'time'      ],
      ['uuid',     'format',  'uuid'      ],
      ['ipv4',     'format',  'ipv4'      ],
      ['ipv6',     'format',  'ipv6'      ],
      ['domain',   'format',  'hostname'  ],
      ['slug',     'pattern', '^[a-z0-9]+(?:-[a-z0-9]+)*$'],
    ];
  }

  public function test_stringSchema_format_preserves_type(): void {
    foreach (['date', 'dateTime', 'time', 'uuid', 'ipv4', 'ipv6', 'domain', 'slug'] as $method) {
      $js = SV::string()->{$method}()->toJsonSchema();
      $this->assertSame('string', $js['type'], "type must be 'string' for format method '{$method}'");
    }
  }

  public function test_stringSchema_date_nullable(): void {
    $js = SV::string()->date()->nullable()->toJsonSchema();
    $this->assertSame(['string', 'null'], $js['type']);
    $this->assertSame('date', $js['format']);
  }

  // ── SV facade — new wrappers ────────────────────────────────

  public function test_postalCode_is_not_mappable(): void {
    $field = SV::postalCode('JP');
    $this->assertFalse($field->isMappable());
    $this->assertSame([], $field->toJsonSchema());
  }

  public function test_postalCode_toRespect_validates(): void {
    $r = SV::postalCode('JP')->toRespect();
    $this->assertTrue($r->validate('101-0021'));
    $this->assertFalse($r->validate('not-a-postal'));
  }

  public function test_creditCard_is_not_mappable(): void {
    $this->assertFalse(SV::creditCard()->isMappable());
  }

  public function test_creditCard_toRespect_validates(): void {
    $r = SV::creditCard()->toRespect();
    // Luhn-valid test number (Visa)
    $this->assertTrue($r->validate('4111111111111111'));
    $this->assertFalse($r->validate('1234567890123456'));
  }

  public function test_iban_is_not_mappable(): void {
    $this->assertFalse(SV::iban()->isMappable());
  }

  public function test_iban_toRespect_validates(): void {
    $r = SV::iban()->toRespect();
    $this->assertTrue($r->validate('GB82WEST12345698765432'));
    $this->assertFalse($r->validate('NOTANIBAN'));
  }

  // ── ArraySchema ─────────────────────────────────────────────

  public function test_arraySchema_basic_jsonSchema(): void {
    $js = SV::array(SV::string()->min(1))->toJsonSchema();
    $this->assertSame('array', $js['type']);
    $this->assertSame(['type' => 'string', 'minLength' => 1], $js['items']);
    $this->assertArrayNotHasKey('minItems', $js);
    $this->assertArrayNotHasKey('maxItems', $js);
  }

  public function test_arraySchema_minItems_maxItems(): void {
    $js = SV::array(SV::string())->minItems(1)->maxItems(5)->toJsonSchema();
    $this->assertSame(1, $js['minItems']);
    $this->assertSame(5, $js['maxItems']);
  }

  public function test_arraySchema_enum_items(): void {
    $js = SV::array(SV::enum(['a', 'b', 'c']))->toJsonSchema();
    $this->assertSame('array', $js['type']);
    $this->assertSame(['a', 'b', 'c'], $js['items']['enum']);
  }

  public function test_arraySchema_in_object_appears_in_properties(): void {
    $sb = SV::object(['tags' => SV::array(SV::string())->optional()]);
    $js = $sb->toJsonSchema();
    $this->assertArrayHasKey('tags', $js['properties']);
    $this->assertSame('array', $js['properties']['tags']['type']);
    $this->assertNotContains('tags', $js['required'] ?? []);
  }

  public function test_arraySchema_toRespect_validates_valid_items(): void {
    $r = RespectAdapter::compileField(SV::array(SV::string()->min(2)));
    $this->assertTrue($r->validate(['ab', 'cd']));
  }

  public function test_arraySchema_toRespect_rejects_short_items(): void {
    $r = RespectAdapter::compileField(SV::array(SV::string()->min(3)));
    $this->assertFalse($r->validate(['ab']));
  }

  public function test_arraySchema_is_mappable(): void {
    $this->assertTrue(SV::array(SV::string())->isMappable());
  }

  // ── SchemaBuilder::when() ────────────────────────────────────

  public function test_when_single_produces_if_then(): void {
    $js = SV::object([
      'type'         => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', 'company', ['company_name'])->toJsonSchema();

    $this->assertArrayHasKey('if', $js);
    $this->assertArrayHasKey('then', $js);
    $this->assertArrayNotHasKey('allOf', $js);
    $this->assertSame(['const' => 'company'], $js['if']['properties']['type']);
    $this->assertSame(['company_name'], $js['then']['required']);
  }

  public function test_when_multiple_produces_allOf(): void {
    $js = SV::object([
      'plan'          => SV::enum(['free', 'enterprise']),
      'billing_email' => SV::string()->optional(),
      'contract'      => SV::string()->optional(),
    ])->when('plan', 'enterprise', ['billing_email'])
      ->when('plan', 'enterprise', ['contract'])
      ->toJsonSchema();

    $this->assertArrayNotHasKey('if', $js);
    $this->assertArrayNotHasKey('then', $js);
    $this->assertArrayHasKey('allOf', $js);
    $this->assertCount(2, $js['allOf']);
    $this->assertSame(['contract'], $js['allOf'][1]['then']['required']);
  }

  public function test_when_absent_produces_no_conditional_keys(): void {
    $js = SV::object(['name' => SV::string()])->toJsonSchema();
    $this->assertArrayNotHasKey('if', $js);
    $this->assertArrayNotHasKey('then', $js);
    $this->assertArrayNotHasKey('allOf', $js);
    $this->assertArrayNotHasKey('x-when', $js);
  }

  // ── SV::equal / SV::notEqual / SV::field ────────────────────

  public function test_when_with_equal_expr_produces_x_when(): void {
    $js = SV::object([
      'type'         => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', SV::equal('company'), ['company_name'])->toJsonSchema();

    $this->assertArrayHasKey('x-when', $js);
    $this->assertCount(1, $js['x-when']);
    $cond = $js['x-when'][0];
    $this->assertArrayHasKey('condition', $cond);
    $this->assertArrayHasKey('===', $cond['condition']);
    $this->assertSame(['var' => 'type'], $cond['condition']['==='][0]);
    $this->assertSame('company',         $cond['condition']['==='][1]);
    $this->assertSame(['company_name'], $cond['require']);
    // literal === also emits standard if/then
    $this->assertArrayHasKey('if', $js);
    $this->assertArrayHasKey('then', $js);
  }

  public function test_when_with_not_equal_expr_produces_x_when_no_if_then(): void {
    $js = SV::object([
      'role'  => SV::enum(['admin', 'user']),
      'note'  => SV::string()->optional(),
    ])->when('role', SV::notEqual('admin'), ['note'])->toJsonSchema();

    $this->assertArrayHasKey('x-when', $js);
    $cond = $js['x-when'][0];
    $this->assertArrayHasKey('!==', $cond['condition']);
    $this->assertSame(['var' => 'role'], $cond['condition']['!=='][0]);
    $this->assertSame('admin',           $cond['condition']['!=='][1]);
    // !== conditions are not expressible in standard JSON Schema
    $this->assertArrayNotHasKey('if', $js);
    $this->assertArrayNotHasKey('then', $js);
  }

  public function test_when_with_field_ref_produces_equalsField(): void {
    $js = SV::object([
      'password'         => SV::string(),
      'confirm_password' => SV::string()->optional(),
      'hint'             => SV::string()->optional(),
    ])->when('password', SV::equal(SV::field('confirm_password')), ['hint'])->toJsonSchema();

    $this->assertArrayHasKey('x-when', $js);
    $cond = $js['x-when'][0];
    $this->assertArrayHasKey('===', $cond['condition']);
    $this->assertSame(['var' => 'password'],         $cond['condition']['==='][0]);
    $this->assertSame(['var' => 'confirm_password'], $cond['condition']['==='][1]);
    // field refs can't emit standard if/then
    $this->assertArrayNotHasKey('if', $js);
  }

  public function test_when_notEqual_field_ref_produces_equalsField(): void {
    $js = SV::object([
      'new_password'     => SV::string(),
      'old_password'     => SV::string()->optional(),
      'confirmation_msg' => SV::string()->optional(),
    ])->when('new_password', SV::notEqual(SV::field('old_password')), ['confirmation_msg'])->toJsonSchema();

    $cond = $js['x-when'][0];
    $this->assertArrayHasKey('!==', $cond['condition']);
    $this->assertSame(['var' => 'new_password'], $cond['condition']['!=='][0]);
    $this->assertSame(['var' => 'old_password'], $cond['condition']['!=='][1]);
  }

  // ── Validator: equal / notEqual runtime ──────────────────────

  public function test_notEqual_triggers_when_condition_matches(): void {
    $sb = SV::object([
      'role' => SV::enum(['admin', 'user']),
      'note' => SV::string()->optional(),
    ])->when('role', SV::notEqual('admin'), ['note']);

    // role !== 'admin' → note required
    $result = $sb->toValidator()->validate(['role' => 'user'])->getResult();
    $this->assertFalse($result['note']['is_valid']);
  }

  public function test_notEqual_does_not_trigger_when_condition_unmet(): void {
    $sb = SV::object([
      'role' => SV::enum(['admin', 'user']),
      'note' => SV::string()->optional(),
    ])->when('role', SV::notEqual('admin'), ['note']);

    // role === 'admin' → condition unmet, note not required
    $result = $sb->toValidator()->validate(['role' => 'admin'])->getResult();
    if (isset($result['note'])) {
      $this->assertTrue($result['note']['is_valid']);
    } else {
      $this->assertTrue(true);
    }
  }

  public function test_equal_field_ref_triggers_when_fields_match(): void {
    $sb = SV::object([
      'password'         => SV::string(),
      'confirm_password' => SV::string(),
      'hint'             => SV::string()->optional(),
    ])->when('password', SV::equal(SV::field('confirm_password')), ['hint']);

    // password === confirm_password → hint required
    $result = $sb->toValidator()->validate([
      'password'         => 'secret',
      'confirm_password' => 'secret',
    ])->getResult();
    $this->assertFalse($result['hint']['is_valid']);
  }

  public function test_equal_field_ref_does_not_trigger_when_fields_differ(): void {
    $sb = SV::object([
      'password'         => SV::string(),
      'confirm_password' => SV::string(),
      'hint'             => SV::string()->optional(),
    ])->when('password', SV::equal(SV::field('confirm_password')), ['hint']);

    // password !== confirm_password → condition unmet
    $result = $sb->toValidator()->validate([
      'password'         => 'secret',
      'confirm_password' => 'different',
    ])->getResult();
    if (isset($result['hint'])) {
      $this->assertTrue($result['hint']['is_valid']);
    } else {
      $this->assertTrue(true);
    }
  }

  // ── Numeric operators (>=, <=, >, <) ────────────────────────

  /**
   * @dataProvider numericOpProvider
   */
  public function test_numeric_op_x_when_output(string $method, string $expectedOp): void {
    $js = SV::object([
      'age'     => SV::integer(),
      'consent' => SV::string()->optional(),
    ])->when('age', SV::{$method}(18), ['consent'])->toJsonSchema();

    $this->assertArrayHasKey('x-when', $js);
    $cond = $js['x-when'][0];
    $this->assertArrayHasKey($expectedOp, $cond['condition']);
    $this->assertSame(['var' => 'age'], $cond['condition'][$expectedOp][0]);
    $this->assertSame(18,               $cond['condition'][$expectedOp][1]);
    // Numeric ops are not expressible in standard JSON Schema
    $this->assertArrayNotHasKey('if', $js);
  }

  public static function numericOpProvider(): array {
    return [
      ['greaterThanOrEqual', '>='],
      ['lessThanOrEqual',    '<='],
      ['greaterThan',        '>' ],
      ['lessThan',           '<' ],
    ];
  }

  public function test_greaterThanOrEqual_triggers_at_boundary(): void {
    $sb = SV::object(['age' => SV::integer(), 'note' => SV::string()->optional()])
      ->when('age', SV::greaterThanOrEqual(18), ['note']);
    // 18 >= 18 → triggers
    $result = $sb->toValidator()->validate(['age' => '18'])->getResult();
    $this->assertFalse($result['note']['is_valid']);
    // 17 >= 18 → does not trigger
    $result2 = $sb->toValidator()->validate(['age' => '17', 'note' => ''])->getResult();
    $this->assertTrue($result2['note']['is_valid']);
  }

  public function test_lessThan_triggers_below_boundary(): void {
    $sb = SV::object(['qty' => SV::integer(), 'warn' => SV::string()->optional()])
      ->when('qty', SV::lessThan(1), ['warn']);
    // 0 < 1 → triggers
    $result = $sb->toValidator()->validate(['qty' => '0'])->getResult();
    $this->assertFalse($result['warn']['is_valid']);
    // 1 < 1 → does not trigger (boundary is exclusive)
    $result2 = $sb->toValidator()->validate(['qty' => '1', 'warn' => ''])->getResult();
    $this->assertTrue($result2['warn']['is_valid']);
  }

  public function test_lessThanOrEqual_triggers_at_boundary(): void {
    $sb = SV::object(['score' => SV::integer(), 'retry' => SV::string()->optional()])
      ->when('score', SV::lessThanOrEqual(50), ['retry']);
    $this->assertFalse(
      $sb->toValidator()->validate(['score' => '50'])->getResult()['retry']['is_valid']
    );
    $this->assertTrue(
      $sb->toValidator()->validate(['score' => '51', 'retry' => ''])->getResult()['retry']['is_valid']
    );
  }

  public function test_greaterThan_triggers_above_boundary(): void {
    $sb = SV::object(['level' => SV::integer(), 'bonus' => SV::string()->optional()])
      ->when('level', SV::greaterThan(10), ['bonus']);
    $this->assertFalse(
      $sb->toValidator()->validate(['level' => '11'])->getResult()['bonus']['is_valid']
    );
    $this->assertTrue(
      $sb->toValidator()->validate(['level' => '10', 'bonus' => ''])->getResult()['bonus']['is_valid']
    );
  }

  public function test_numeric_op_with_field_ref(): void {
    $sb = SV::object([
      'price'     => SV::integer(),
      'min_price' => SV::integer()->optional(),
      'note'      => SV::string()->optional(),
    ])->when('price', SV::greaterThanOrEqual(SV::field('min_price')), ['note']);

    $js = $sb->toJsonSchema();
    $cond = $js['x-when'][0];
    $this->assertArrayHasKey('>=', $cond['condition']);
    $this->assertSame(['var' => 'price'],     $cond['condition']['>='][0]);
    $this->assertSame(['var' => 'min_price'], $cond['condition']['>='][1]);

    // price(100) >= min_price(50) → triggers
    $result = $sb->toValidator()->validate(['price' => '100', 'min_price' => '50'])->getResult();
    $this->assertFalse($result['note']['is_valid']);

    // price(30) >= min_price(50) → does not trigger
    $result2 = $sb->toValidator()->validate(['price' => '30', 'min_price' => '50', 'note' => ''])->getResult();
    $this->assertTrue($result2['note']['is_valid']);
  }

  public function test_when_expr_invalid_op_throws_invalid_argument_exception(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Invalid WhenExpr operator/');
    new \SchemableValidator\Schema\WhenExpr('~=', 'value');
  }

  public function test_when_expr_empty_string_op_throws(): void {
    $this->expectException(\InvalidArgumentException::class);
    new \SchemableValidator\Schema\WhenExpr('', 'value');
  }

  public function test_when_expr_whitespace_padded_op_throws(): void {
    // ' ===' is a different string from '==='; must not silently match
    $this->expectException(\InvalidArgumentException::class);
    new \SchemableValidator\Schema\WhenExpr(' ===', 'value');
  }

  public function test_when_expr_lookalike_op_throws(): void {
    // '==' (JS loose equality) must not be accepted
    $this->expectException(\InvalidArgumentException::class);
    new \SchemableValidator\Schema\WhenExpr('==', 'value');
  }

  public function test_when_expr_valid_ops_do_not_throw(): void {
    foreach (['===', '!==', '>=', '<=', '>', '<'] as $op) {
      $expr = new \SchemableValidator\Schema\WhenExpr($op, 'x');
      $this->assertSame($op, $expr->op);
    }
  }

  public function test_scalar_shorthand_still_works_as_equal(): void {
    // Passing a plain scalar to when() should behave identically to SV::equal()
    $js1 = SV::object(['type' => SV::enum(['a', 'b']), 'x' => SV::string()->optional()])
      ->when('type', 'a', ['x'])->toJsonSchema();
    $js2 = SV::object(['type' => SV::enum(['a', 'b']), 'x' => SV::string()->optional()])
      ->when('type', SV::equal('a'), ['x'])->toJsonSchema();

    $this->assertSame($js1['x-when'][0]['condition'], $js2['x-when'][0]['condition']);
  }

  // ── UISchema (Step 1-e) ──────────────────────────────────────

  public function test_toUiSchema_basic_layout(): void {
    $sb = SV::object([
      'name'  => SV::string(),
      'email' => SV::string()->email(),
    ]);
    $ui = $sb->toUiSchema();
    $this->assertSame('VerticalLayout', $ui['type']);
    $this->assertCount(2, $ui['elements']);
    $this->assertSame('Control',             $ui['elements'][0]['type']);
    $this->assertSame('#/properties/name',   $ui['elements'][0]['scope']);
    $this->assertSame('name',                $ui['elements'][0]['label']);
    $this->assertSame('#/properties/email',  $ui['elements'][1]['scope']);
    $this->assertSame('email',               $ui['elements'][1]['label']);
  }

  public function test_toUiSchema_label_override(): void {
    $sb = SV::object([
      'name'  => SV::string()->label('お名前'),
      'email' => SV::string()->email()->label('メールアドレス'),
    ]);
    $ui = $sb->toUiSchema();
    $this->assertSame('お名前',           $ui['elements'][0]['label']);
    $this->assertSame('メールアドレス',   $ui['elements'][1]['label']);
  }

  public function test_toUiSchema_excludes_unmapped_fields(): void {
    $sb = SV::object([
      'name' => SV::string(),
      'file' => SV::file(['image/jpeg']),
    ]);
    $ui = $sb->toUiSchema();
    // Only mappable fields appear in elements
    $this->assertCount(1, $ui['elements']);
    $this->assertSame('#/properties/name', $ui['elements'][0]['scope']);
  }

  public function test_toUiSchema_does_not_affect_toJsonSchema(): void {
    $sb = SV::object(['name' => SV::string()->label('お名前')]);
    $js = $sb->toJsonSchema();
    // label must not leak into the JSON Schema output
    $this->assertArrayNotHasKey('label', $js['properties']['name']);
  }

  // ── errorMessages (Step 1-a) ────────────────────────────────

  public function test_errorMessages_appears_in_toJsonSchema(): void {
    $js = SV::string()->email()->errorMessages([
      'format' => '有効なメールアドレスを入力してください',
    ])->toJsonSchema();
    $this->assertArrayHasKey('errorMessage', $js);
    $this->assertSame('有効なメールアドレスを入力してください', $js['errorMessage']['format']);
  }

  public function test_errorMessages_absent_when_not_set(): void {
    $js = SV::string()->email()->toJsonSchema();
    $this->assertArrayNotHasKey('errorMessage', $js);
  }

  public function test_errorMessages_on_integer(): void {
    $js = SV::integer()->min(1)->errorMessages(['type' => '整数で入力してください'])->toJsonSchema();
    $this->assertSame('整数で入力してください', $js['errorMessage']['type']);
  }

  public function test_errorMessages_survives_nullable(): void {
    $js = SV::string()->nullable()->errorMessages(['type' => 'custom'])->toJsonSchema();
    $this->assertSame(['string', 'null'], $js['type']);
    $this->assertSame('custom', $js['errorMessage']['type']);
  }

  public function test_errorMessages_in_object_toJsonSchema(): void {
    $sb = SV::object([
      'email' => SV::string()->email()->errorMessages(['format' => 'メール形式が無効です']),
    ]);
    $js = $sb->toJsonSchema();
    $this->assertArrayHasKey('errorMessage', $js['properties']['email']);
    $this->assertSame('メール形式が無効です', $js['properties']['email']['errorMessage']['format']);
  }

  // ── SchemaBuilder::customFields() (Step 3-c) ─────────────────

  public function test_customFields_appears_in_json_schema(): void {
    $js = SV::object(['email' => SV::string()])
      ->customFields(['email_unique', 'age_verify'])
      ->toJsonSchema();

    $this->assertArrayHasKey('x-custom-fields', $js);
    $this->assertSame(['email_unique', 'age_verify'], $js['x-custom-fields']);
  }

  public function test_customFields_absent_when_not_called(): void {
    $js = SV::object(['name' => SV::string()])->toJsonSchema();
    $this->assertArrayNotHasKey('x-custom-fields', $js);
  }

  public function test_customFields_single_entry(): void {
    $js = SV::object(['email' => SV::string()])
      ->customFields(['email_unique'])
      ->toJsonSchema();

    $this->assertSame(['email_unique'], $js['x-custom-fields']);
  }

  public function test_customFields_does_not_affect_validation(): void {
    $sb = SV::object(['name' => SV::string()])->customFields(['custom_check']);
    $result = $sb->toValidator()->validate(['name' => 'Alice'])->getResult();
    $this->assertTrue($result['name']['is_valid']);
    // custom_check not in result — server-side only
    $this->assertArrayNotHasKey('custom_check', $result);
  }
}
