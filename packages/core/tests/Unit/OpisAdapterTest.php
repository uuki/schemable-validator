<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\Adapters\OpisAdapter;

/**
 * OpisAdapter is a respect-free BackendAdapter compiling raw JSON Schema
 * 2020-12 via opis/json-schema — strict structural validation, no
 * Coercion Contract v1 (see RespectAdapter / Coercion).
 */
class OpisAdapterTest extends TestCase {
  private function schema(array $properties, array $required = []): array {
    return [
      '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
      'type'       => 'object',
      'properties' => $properties,
      'required'   => $required,
    ];
  }

  public function test_typed_integer_value_is_valid(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['age' => ['type' => 'integer']], ['age'])
    );

    $result = $executable->validate(['age' => 42]);

    $this->assertTrue($result['age']['is_valid']);
    $this->assertNull($result['age']['errors']);
    $this->assertSame(42, $result['age']['value']);
  }

  public function test_typed_non_integer_value_is_invalid(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['age' => ['type' => 'integer']], ['age'])
    );

    $result = $executable->validate(['age' => 3.14]);

    $this->assertFalse($result['age']['is_valid']);
    $this->assertNotNull($result['age']['errors']);
  }

  /**
   * Documented divergence from RespectAdapter/FE: opis applies strict JSON
   * Schema semantics (no Coercion Contract), so a form string "42" fails
   * `type: integer` here even though RespectAdapter/FE accept it.
   */
  public function test_form_string_integer_is_invalid_under_strict_semantics(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['age' => ['type' => 'integer']], ['age'])
    );

    $result = $executable->validate(['age' => '42']);

    $this->assertFalse($result['age']['is_valid']);
  }

  public function test_required_field_missing_is_invalid(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['name' => ['type' => 'string', 'minLength' => 1]], ['name'])
    );

    $result = $executable->validate([]);

    $this->assertFalse($result['name']['is_valid']);
  }

  public function test_optional_field_missing_is_valid(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['nickname' => ['type' => 'string']], [])
    );

    $result = $executable->validate([]);

    $this->assertTrue($result['nickname']['is_valid']);
    $this->assertNull($result['nickname']['errors']);
  }

  public function test_string_within_length_bounds_is_valid(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50]], ['name'])
    );

    $result = $executable->validate(['name' => 'Alice']);

    $this->assertTrue($result['name']['is_valid']);
  }

  // ── engine-neutral messages (A: same canonical text as RespectAdapter/FE) ──

  /** @dataProvider neutralMessageProvider */
  public function test_emits_canonical_neutral_message(array $prop, $value, string $expected): void {
    $executable = (new OpisAdapter())->compile($this->schema(['f' => $prop], ['f']));
    $result     = $executable->validate(['f' => $value]);

    $this->assertFalse($result['f']['is_valid']);
    $this->assertSame($expected, $result['f']['errors']);
  }

  public function neutralMessageProvider(): array {
    return [
      'type integer'  => [['type' => 'integer'], 3.14, 'must be an integer'],
      'minLength'     => [['type' => 'string', 'minLength' => 3], 'ab', 'must be at least 3 characters long'],
      'maxLength sg'  => [['type' => 'string', 'maxLength' => 1], 'ab', 'must be no more than 1 character long'],
      'minimum'       => [['type' => 'integer', 'minimum' => 10], 5, 'must be at least 10'],
      'maximum'       => [['type' => 'integer', 'maximum' => 100], 200, 'must be no more than 100'],
      'pattern'       => [['type' => 'string', 'pattern' => '^[0-9]+$'], 'ab', 'must match the required format'],
      'enum'          => [['type' => 'string', 'enum' => ['a', 'b', 'c']], 'z', 'must be one of: a, b, c'],
      'format email'  => [['type' => 'string', 'format' => 'email'], 'bad', 'must be a valid email'],
    ];
  }

  public function test_required_missing_uses_canonical_message(): void {
    $executable = (new OpisAdapter())->compile(
      $this->schema(['name' => ['type' => 'string', 'minLength' => 1]], ['name'])
    );
    $result = $executable->validate([]);

    $this->assertFalse($result['name']['is_valid']);
    $this->assertSame('is required', $result['name']['errors']);
  }

  public function test_inline_errorMessage_overrides_and_interpolates(): void {
    $executable = (new OpisAdapter())->compile($this->schema([
      'f' => ['type' => 'string', 'minLength' => 3, 'errorMessage' => ['minLength' => '最低{min}文字']],
    ], ['f']));
    $result = $executable->validate(['f' => 'ab']);

    $this->assertFalse($result['f']['is_valid']);
    $this->assertSame('最低3文字', $result['f']['errors']);
  }
}
