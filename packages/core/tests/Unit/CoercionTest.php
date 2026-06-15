<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\Coercion;

/**
 * Pins Coercion Contract v1: the acceptance tables implemented by
 * Coercion::acceptsInteger(), acceptsNumber(), acceptsBoolean().
 *
 * Whitespace handling: numeric predicates trim leading/trailing whitespace
 * before matching (mirrors ECMAScript Number(string) — Number(" 42 ") === 42).
 * Boolean predicate does NOT trim; whitespace-padded booleans are rejected.
 */
class CoercionTest extends TestCase {
  // ── toNumber ─────────────────────────────────────────────────────────────

  /** @dataProvider toNumberFiniteProvider */
  public function test_toNumber_returns_finite_float(string $input, float $expected): void {
    $this->assertSame($expected, Coercion::toNumber($input));
  }

  public function toNumberFiniteProvider(): array {
    return [
      'integer'                => ['42',       42.0],
      'negative integer'       => ['-5',       -5.0],
      'signed positive'        => ['+3',       3.0],
      'decimal'                => ['3.14',     3.14],
      'leading dot'            => ['.5',       0.5],
      'trailing dot'           => ['3.',       3.0],
      'exponent'               => ['1e3',      1000.0],
      'negative exponent'      => ['1.5e-3',   0.0015],
      'signed exponent'        => ['+1E2',     100.0],
      'zero'                   => ['0',        0.0],
      'empty string -> zero'   => ['',         0.0],
      'whitespace-only -> zero' => [' ',       0.0],
      'whitespace-padded int'  => [' 42 ',     42.0],
      'whitespace-padded float' => ["\t3.14\n", 3.14],
    ];
  }

  /** @dataProvider toNumberNanProvider */
  public function test_toNumber_returns_nan(string $input): void {
    $this->assertTrue(is_nan(Coercion::toNumber($input)));
  }

  public function toNumberNanProvider(): array {
    return [
      'hex literal'    => ['0x10'],
      'octal literal'  => ['0o7'],
      'binary literal' => ['0b101'],
      'Infinity'       => ['Infinity'],
      'neg Infinity'   => ['-Infinity'],
      'NaN literal'    => ['NaN'],
      'plain text'     => ['hello'],
      'underscore sep' => ['1_000'],
      'space in middle' => ['4 2'],
    ];
  }

  // ── acceptsInteger ───────────────────────────────────────────────────────

  /** @dataProvider integerAcceptedProvider */
  public function test_acceptsInteger_accepts(string $value): void {
    $this->assertTrue(Coercion::acceptsInteger($value));
  }

  public function integerAcceptedProvider(): array {
    return [
      'plain integer'           => ['42'],
      'zero'                    => ['0'],
      'negative integer'        => ['-5'],
      'signed positive'         => ['+3'],
      'integer with trailing dot' => ['3.'],
      'float equal to integer'  => ['1.0'],
      'large exponent integer'  => ['1e2'],
      'leading whitespace'      => [' 42'],
      'trailing whitespace'     => ['42 '],
      'padded whitespace'       => [' 42 '],
      'whitespace-only (-> 0)'  => [' '],
    ];
  }

  /** @dataProvider integerRejectedProvider */
  public function test_acceptsInteger_rejects(string $value): void {
    $this->assertFalse(Coercion::acceptsInteger($value));
  }

  public function integerRejectedProvider(): array {
    return [
      'empty string'   => [''],
      'float'          => ['3.14'],
      'hex literal'    => ['0x10'],
      'octal literal'  => ['0o7'],
      'binary literal' => ['0b101'],
      'Infinity'       => ['Infinity'],
      'NaN'            => ['NaN'],
      'plain text'     => ['abc'],
      'space in middle' => ['4 2'],
    ];
  }

  // ── acceptsNumber ────────────────────────────────────────────────────────

  /** @dataProvider numberAcceptedProvider */
  public function test_acceptsNumber_accepts(string $value): void {
    $this->assertTrue(Coercion::acceptsNumber($value));
  }

  public function numberAcceptedProvider(): array {
    return [
      'integer'                 => ['42'],
      'float'                   => ['3.14'],
      'negative float'          => ['-1.5'],
      'exponent'                => ['1.5e3'],
      'negative exponent'       => ['1e-5'],
      'padded whitespace'       => [' 3.14 '],
      'whitespace-only (-> 0)'  => ['  '],
    ];
  }

  /** @dataProvider numberRejectedProvider */
  public function test_acceptsNumber_rejects(string $value): void {
    $this->assertFalse(Coercion::acceptsNumber($value));
  }

  public function numberRejectedProvider(): array {
    return [
      'empty string'   => [''],
      'hex literal'    => ['0x10'],
      'octal literal'  => ['0o7'],
      'binary literal' => ['0b101'],
      'Infinity'       => ['Infinity'],
      'neg Infinity'   => ['-Infinity'],
      'NaN'            => ['NaN'],
      'plain text'     => ['abc'],
      'space in middle' => ['4 2'],
    ];
  }

  // ── acceptsBoolean ───────────────────────────────────────────────────────

  /** @dataProvider booleanAcceptedProvider */
  public function test_acceptsBoolean_accepts(string $value): void {
    $this->assertTrue(Coercion::acceptsBoolean($value));
  }

  public function booleanAcceptedProvider(): array {
    return [
      'true lowercase'  => ['true'],
      'true uppercase'  => ['TRUE'],
      'true mixed-case' => ['True'],
      'false lowercase' => ['false'],
      'false uppercase' => ['FALSE'],
      'one'             => ['1'],
      'zero'            => ['0'],
      'on lowercase'    => ['on'],
      'on uppercase'    => ['ON'],
      'off lowercase'   => ['off'],
      'off uppercase'   => ['OFF'],
      'yes lowercase'   => ['yes'],
      'yes uppercase'   => ['YES'],
      'no lowercase'    => ['no'],
      'no uppercase'    => ['NO'],
    ];
  }

  /** @dataProvider booleanRejectedProvider */
  public function test_acceptsBoolean_rejects(string $value): void {
    $this->assertFalse(Coercion::acceptsBoolean($value));
  }

  public function booleanRejectedProvider(): array {
    return [
      'empty string'          => [''],
      'whitespace-padded true' => [' true '],
      'whitespace-padded 1'   => [' 1 '],
      'leading space'         => [' true'],
      'arbitrary string'      => ['yes_maybe'],
      'integer 2'             => ['2'],
      'plain text'            => ['hello'],
    ];
  }
}
