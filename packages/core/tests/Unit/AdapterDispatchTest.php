<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\SV;
use SchemableValidator\Validation\Adapters\NativeAdapter;
use SchemableValidator\Validation\Adapters\OpisAdapter;
use SchemableValidator\Validation\Adapters\RespectAdapter;

/**
 * Verifies that SchemaBuilder::toValidator() / Validator::fromJsonSchema() now
 * dispatch field validation through the chosen BackendAdapter (the adapter is
 * load-bearing in production), and that swapping the engine actually changes
 * behavior on the documented coercion axis.
 */
final class AdapterDispatchTest extends TestCase {

  // ── default engine unchanged (Respect, coercing) ──

  public function test_default_adapter_is_respect_and_coerces(): void {
    $r = SV::object(['age' => SV::integer()])->toValidator()
      ->validate(['age' => '42'])->getResult();
    $this->assertTrue($r['age']['is_valid']); // Coercion Contract v1
  }

  // ── engine is actually swapped ──

  public function test_native_adapter_coerces_form_strings(): void {
    $r = SV::object(['age' => SV::integer()])->toValidator([], new NativeAdapter())
      ->validate(['age' => '42'])->getResult();
    $this->assertTrue($r['age']['is_valid']);
  }

  public function test_opis_adapter_is_strict_rejects_form_string(): void {
    // Same schema + input, different engine → different result (documented coercion divergence).
    $r = SV::object(['age' => SV::integer()])->toValidator([], new OpisAdapter())
      ->validate(['age' => '42'])->getResult();
    $this->assertFalse($r['age']['is_valid']);
  }

  public function test_native_and_respect_agree_on_canonical_message(): void {
    $native = SV::object(['email' => SV::string()->email()])->toValidator([], new NativeAdapter())
      ->validate(['email' => 'bad'])->getResult();
    $respect = SV::object(['email' => SV::string()->email()])->toValidator([], new RespectAdapter())
      ->validate(['email' => 'bad'])->getResult();

    $this->assertSame('must be a valid email', $native['email']['errors']);
    $this->assertSame($respect['email']['errors'], $native['email']['errors']);
  }

  // ── MessageDict flows through the adapter (withMessages still works) ──

  public function test_dict_applies_through_native_adapter(): void {
    $r = SV::object(['email' => SV::string()->email()])
      ->withMessages(MessageDict::ja())
      ->toValidator([], new NativeAdapter())
      ->validate(['email' => 'bad'])->getResult();
    $this->assertSame('有効なメールアドレスを入力してください', $r['email']['errors']);
  }

  public function test_dict_applies_through_default_respect_adapter(): void {
    $r = SV::object(['name' => SV::string()->min(3)])
      ->withMessages(MessageDict::ja())
      ->toValidator()
      ->validate(['name' => 'ab'])->getResult();
    $this->assertSame('最低3文字で入力してください', $r['name']['errors']);
  }

  // ── fromJsonSchema dispatches through the adapter ──

  public function test_fromJsonSchema_uses_native_adapter(): void {
    $schema = [
      'type'       => 'object',
      'properties' => ['n' => ['type' => 'integer']],
      'required'   => ['n'],
    ];
    $r = \SchemableValidator\Validator::fromJsonSchema($schema, [], [], null, new NativeAdapter())
      ->validate(['n' => '7'])->getResult();
    $this->assertTrue($r['n']['is_valid']); // Native coerces
  }

  // ── (B) escape hatch: RawRespectSchema still validated regardless of adapter ──

  public function test_raw_respect_escape_hatch_runs_under_native_adapter(): void {
    // postal_code is a RawRespectSchema (UnmappableField) — it has no JSON Schema
    // form, so it runs on Respect directly even when the mappable engine is Native.
    $sb = SV::object([
      'name'        => SV::string()->min(1),
      'postal_code' => SV::respect(v::digit()->length(3, 3)),
    ]);
    $r = $sb->toValidator([], new NativeAdapter())
      ->validate(['name' => 'Alice', 'postal_code' => 'xx'])->getResult();

    $this->assertTrue($r['name']['is_valid']);          // mappable via Native
    $this->assertFalse($r['postal_code']['is_valid']);  // escape hatch via Respect
  }

  public function test_raw_respect_escape_hatch_passes_valid_value(): void {
    $sb = SV::object([
      'postal_code' => SV::respect(v::digit()->length(3, 3)),
    ]);
    $r = $sb->toValidator([], new NativeAdapter())
      ->validate(['postal_code' => '123'])->getResult();
    $this->assertTrue($r['postal_code']['is_valid']);
  }
}
