<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\Adapters\NativeAdapter;

/**
 * NativeAdapter is the dependency-free, FE-faithful BackendAdapter. It honors
 * Coercion Contract v1 (form strings accepted) and emits the canonical
 * DefaultMessages strings — identical to RespectAdapter/FE.
 */
final class NativeAdapterTest extends TestCase {
  private function schema(array $properties, array $required = []): array {
    return [
      '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
      'type'       => 'object',
      'properties' => $properties,
      'required'   => $required,
    ];
  }

  private function exec(array $properties, array $required, array $data): array {
    return (new NativeAdapter())->compile($this->schema($properties, $required))->validate($data);
  }

  // ── Coercion Contract v1 (form strings, unlike Opis) ──

  public function test_form_string_integer_is_valid(): void {
    $r = $this->exec(['age' => ['type' => 'integer']], ['age'], ['age' => '42']);
    $this->assertTrue($r['age']['is_valid']);
  }

  public function test_decimal_string_rejected_for_integer(): void {
    $r = $this->exec(['age' => ['type' => 'integer']], ['age'], ['age' => '3.14']);
    $this->assertFalse($r['age']['is_valid']);
    $this->assertSame('must be an integer', $r['age']['errors']);
  }

  public function test_hex_string_rejected_for_number(): void {
    // Coercion Contract v1 delta: 0x10 is NOT accepted even though JS Number() would.
    $r = $this->exec(['n' => ['type' => 'number']], ['n'], ['n' => '0x10']);
    $this->assertFalse($r['n']['is_valid']);
  }

  public function test_boolean_accepts_on(): void {
    $r = $this->exec(['flag' => ['type' => 'boolean']], ['flag'], ['flag' => 'on']);
    $this->assertTrue($r['flag']['is_valid']);
  }

  // ── canonical messages ──

  public function test_email_canonical_message(): void {
    $r = $this->exec(['e' => ['type' => 'string', 'format' => 'email']], ['e'], ['e' => 'bad']);
    $this->assertSame('must be a valid email', $r['e']['errors']);
  }

  public function test_minLength_plural_message(): void {
    $r = $this->exec(['s' => ['type' => 'string', 'minLength' => 3]], ['s'], ['s' => 'ab']);
    $this->assertSame('must be at least 3 characters long', $r['s']['errors']);
  }

  public function test_maxLength_singular_message(): void {
    $r = $this->exec(['s' => ['type' => 'string', 'maxLength' => 1]], ['s'], ['s' => 'ab']);
    $this->assertSame('must be no more than 1 character long', $r['s']['errors']);
  }

  public function test_enum_lists_values(): void {
    $r = $this->exec(['c' => ['type' => 'string', 'enum' => ['a', 'b']]], ['c'], ['c' => 'z']);
    $this->assertSame('must be one of: a, b', $r['c']['errors']);
  }

  public function test_required_empty_message(): void {
    $r = $this->exec(['s' => ['type' => 'string', 'minLength' => 1]], ['s'], ['s' => '']);
    $this->assertFalse($r['s']['is_valid']);
    $this->assertSame('is required', $r['s']['errors']);
  }

  public function test_optional_empty_is_valid(): void {
    $r = $this->exec(['s' => ['type' => 'string', 'minLength' => 3]], [], ['s' => '']);
    $this->assertTrue($r['s']['is_valid']);
  }

  // ── error accumulation in canonical order ──

  public function test_multi_rule_accumulates_in_canonical_order(): void {
    // format → pattern, mirroring constraintsFromSchema() order.
    $r = $this->exec(
      ['code' => ['type' => 'string', 'format' => 'email', 'pattern' => '^[0-9]+$']],
      ['code'],
      ['code' => 'zz']
    );
    $this->assertFalse($r['code']['is_valid']);
    $this->assertSame("must be a valid email\nmust match the required format", $r['code']['errors']);
  }

  // ── inline errorMessage override + interpolation ──

  public function test_inline_override_interpolates(): void {
    $r = $this->exec(
      ['s' => ['type' => 'string', 'minLength' => 3, 'errorMessage' => ['minLength' => '最低{min}文字']]],
      ['s'],
      ['s' => 'ab']
    );
    $this->assertSame('最低3文字', $r['s']['errors']);
  }

  // ── arrays ──

  public function test_array_min_items(): void {
    $r = $this->exec(
      ['tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2]],
      ['tags'],
      ['tags' => ['a']]
    );
    $this->assertFalse($r['tags']['is_valid']);
    $this->assertSame('must have at least 2 items', $r['tags']['errors']);
  }

  public function test_array_items_validated(): void {
    $r = $this->exec(
      ['nums' => ['type' => 'array', 'items' => ['type' => 'integer']]],
      ['nums'],
      ['nums' => ['1', 'x', '3']]
    );
    $this->assertFalse($r['nums']['is_valid']);
    $this->assertSame('must be an integer', $r['nums']['errors']);
  }
}
