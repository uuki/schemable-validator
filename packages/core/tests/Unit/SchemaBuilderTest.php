<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\SchemaBuilder;
use SchemableValidator\SV;
use SchemableValidator\Schema\RuleMapper;
use SchemableValidator\Schema\RuleMapping;
use SchemableValidator\Validator;

class SchemaBuilderTest extends TestCase {
  // ── RuleMapping ─────────────────────────────────────────────

  public function test_ruleMapping_isMappable_true_when_jsonSchema_set(): void {
    $m = new RuleMapping(v::stringType(), ['type' => 'string']);
    $this->assertTrue($m->isMappable());
    $this->assertSame(['type' => 'string'], $m->jsonSchema);
  }

  public function test_ruleMapping_isMappable_false_when_jsonSchema_null(): void {
    $m = new RuleMapping(v::create(), null);
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
    $r = SV::string()->min(2)->toRespect();
    $this->assertTrue($r->validate('hello'));
  }

  public function test_stringSchema_toRespect_validates_invalid(): void {
    $r = SV::string()->min(5)->toRespect();
    $this->assertFalse($r->validate('hi'));
  }

  public function test_stringSchema_email_toRespect(): void {
    $r = SV::string()->email()->toRespect();
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
    $r = SV::integer()->min(1)->max(10)->toRespect();
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
    $r = SV::boolean()->toRespect();
    $this->assertTrue($r->validate(true));
    $this->assertFalse($r->validate('yes'));
  }

  // ── EnumSchema ──────────────────────────────────────────────

  public function test_enumSchema_jsonSchema(): void {
    $js = SV::enum(['a', 'b', 'c'])->toJsonSchema();
    $this->assertSame('string', $js['type']);
    $this->assertSame(['a', 'b', 'c'], $js['enum']);
  }

  public function test_enumSchema_toRespect(): void {
    $r = SV::enum(['general', 'support'])->toRespect();
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
}
