<?php

namespace SchemableValidator\Tests\Unit;

use Respect\Validation\Validator as v;
use PHPUnit\Framework\TestCase;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\SV;
use SchemableValidator\Validation\Adapters\NativeAdapter;

/**
 * SV::custom() is a dependency-free (B) escape hatch executed via
 * CustomField::evaluate() — no engine involved. SV::respect() is the
 * Respect-backed CustomField; both run through the same engine-agnostic path.
 */
final class CustomFieldTest extends TestCase {

  public function test_custom_predicate_passes(): void {
    $r = SV::object(['code' => SV::custom(fn($v) => $v === 'OK')])
      ->toValidator()->validate(['code' => 'OK'])->getResult();
    $this->assertTrue($r['code']['is_valid']);
    $this->assertNull($r['code']['errors']);
  }

  public function test_custom_predicate_fails_with_message(): void {
    $r = SV::object(['code' => SV::custom(fn($v) => $v === 'OK', 'code must be OK')])
      ->toValidator()->validate(['code' => 'NO'])->getResult();
    $this->assertFalse($r['code']['is_valid']);
    $this->assertSame('code must be OK', $r['code']['errors']);
  }

  public function test_custom_optional_empty_is_valid(): void {
    $r = SV::object(['code' => SV::custom(fn($v) => $v === 'OK')->optional()])
      ->toValidator()->validate(['code' => ''])->getResult();
    $this->assertTrue($r['code']['is_valid']);
  }

  public function test_custom_message_resolved_via_dict(): void {
    $r = SV::object(['code' => SV::custom(fn($v) => false, 'fallback')])
      ->withMessages(new MessageDict(['code' => ['custom' => '辞書メッセージ']]))
      ->toValidator()->validate(['code' => 'x'])->getResult();
    $this->assertSame('辞書メッセージ', $r['code']['errors']);
  }

  public function test_custom_runs_engine_agnostic_under_native_adapter(): void {
    // The custom field has no IR; it must validate even when the scalar engine is Native.
    $r = SV::object([
      'name' => SV::string()->min(1),
      'code' => SV::custom(fn($v) => $v === 'OK'),
    ])->toValidator([], ['adapter' => new NativeAdapter()])
      ->validate(['name' => 'Alice', 'code' => 'NO'])->getResult();

    $this->assertTrue($r['name']['is_valid']);
    $this->assertFalse($r['code']['is_valid']);
  }

  // ── SV::respect() still works through the CustomField path ──

  public function test_respect_escape_hatch_validates(): void {
    $r = SV::object(['pc' => SV::respect(v::digit()->length(3, 3))])
      ->toValidator()->validate(['pc' => '123'])->getResult();
    $this->assertTrue($r['pc']['is_valid']);

    $r2 = SV::object(['pc' => SV::respect(v::digit()->length(3, 3))])
      ->toValidator()->validate(['pc' => 'xx'])->getResult();
    $this->assertFalse($r2['pc']['is_valid']);
  }
}
