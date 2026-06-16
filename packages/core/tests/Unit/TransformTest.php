<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\Transform;

final class TransformTest extends TestCase {

  // ── catalog: trim ────────────────────────────────────────────────────────────

  public function test_trim_removes_spaces(): void {
    $this->assertSame('hello', Transform::apply('  hello  ', ['trim']));
  }

  public function test_trim_removes_tab(): void {
    $this->assertSame('hello', Transform::apply("\thello\t", ['trim']));
  }

  public function test_trim_removes_lf(): void {
    $this->assertSame('hello', Transform::apply("\nhello\n", ['trim']));
  }

  public function test_trim_removes_cr(): void {
    $this->assertSame('hello', Transform::apply("\rhello\r", ['trim']));
  }

  public function test_trim_empty_string_stays_empty(): void {
    $this->assertSame('', Transform::apply('   ', ['trim']));
  }

  public function test_trim_leaves_inner_spaces(): void {
    $this->assertSame('hello world', Transform::apply('  hello world  ', ['trim']));
  }

  // ── catalog: toLowerCase ─────────────────────────────────────────────────────

  public function test_toLowerCase_ascii(): void {
    $this->assertSame('hello world', Transform::apply('Hello World', ['toLowerCase']));
  }

  public function test_toLowerCase_leaves_non_ascii(): void {
    // Non-ASCII chars are not transformed
    $this->assertSame('Ñ', Transform::apply('Ñ', ['toLowerCase']));
  }

  // ── catalog: toUpperCase ─────────────────────────────────────────────────────

  public function test_toUpperCase_ascii(): void {
    $this->assertSame('HELLO WORLD', Transform::apply('hello world', ['toUpperCase']));
  }

  // ── pipeline order ───────────────────────────────────────────────────────────

  public function test_pipeline_trim_then_toLowerCase(): void {
    $this->assertSame('hello', Transform::apply('  HELLO  ', ['trim', 'toLowerCase']));
  }

  public function test_pipeline_trim_then_toUpperCase(): void {
    $this->assertSame('HELLO', Transform::apply('  hello  ', ['trim', 'toUpperCase']));
  }

  // ── unknown transform throws ─────────────────────────────────────────────────

  public function test_unknown_transform_throws(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Unknown x-transform/');
    Transform::apply('value', ['stripHtml']);
  }

  // ── SchemaBuilder integration ─────────────────────────────────────────────────

  public function test_transform_appears_in_json_schema(): void {
    $js = \SchemableValidator\SV::string()->transform(['trim'])->toJsonSchema();
    $this->assertArrayHasKey('x-transform', $js);
    $this->assertSame(['trim'], $js['x-transform']);
  }

  public function test_transform_absent_when_not_set(): void {
    $js = \SchemableValidator\SV::string()->toJsonSchema();
    $this->assertArrayNotHasKey('x-transform', $js);
  }

  public function test_validator_applies_transform_before_validation(): void {
    $sb = \SchemableValidator\SV::object([
      'name' => \SchemableValidator\SV::string()->transform(['trim']),
    ]);
    $result = $sb->toValidator()->validate(['name' => '  Alice  '])->getResult();
    $this->assertTrue($result['name']['is_valid']);
    $this->assertSame('Alice', $result['name']['value']);
  }

  public function test_transform_order_trim_then_toLowerCase_in_validator(): void {
    $sb = \SchemableValidator\SV::object([
      'code' => \SchemableValidator\SV::string()->transform(['trim', 'toLowerCase']),
    ]);
    $result = $sb->toValidator()->validate(['code' => '  ABC  '])->getResult();
    $this->assertSame('abc', $result['code']['value']);
  }

  public function test_trim_then_minLength_fails_whitespace_only(): void {
    $sb = \SchemableValidator\SV::object([
      'name' => \SchemableValidator\SV::string()->min(1)->transform(['trim']),
    ]);
    // '   ' trims to '', which fails minLength(1)
    $result = $sb->toValidator()->validate(['name' => '   '])->getResult();
    $this->assertFalse($result['name']['is_valid']);
  }
}
