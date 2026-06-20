<?php

namespace SchemableValidator\Tests\Unit;

use Respect\Validation\Validator as v;
use PHPUnit\Framework\TestCase;
use SchemableValidator\Adapters\Respect\RespectRules;
use SchemableValidator\Schema\RawRespectSchema;
use SchemableValidator\SV;

/**
 * The Respect driver (RespectRules) is the canonical, namespaced home for
 * Respect-backed (B) escape hatches. SV::respect/postalCode/... are kept as
 * deprecated delegations for back-compat.
 */
final class RespectDriverTest extends TestCase {

  public function test_rule_returns_raw_respect_field(): void {
    $this->assertInstanceOf(RawRespectSchema::class, RespectRules::rule(v::email()));
  }

  public function test_driver_field_validates_through_schema_builder(): void {
    $r = SV::object(['zip' => RespectRules::postalCode('JP')])
      ->toValidator()->validate(['zip' => '101-0021'])->getResult();
    $this->assertTrue($r['zip']['is_valid']);

    $r2 = SV::object(['zip' => RespectRules::postalCode('JP')])
      ->toValidator()->validate(['zip' => 'nope'])->getResult();
    $this->assertFalse($r2['zip']['is_valid']);
  }

  public function test_iban_and_creditCard_factories(): void {
    $this->assertInstanceOf(RawRespectSchema::class, RespectRules::iban());
    $this->assertInstanceOf(RawRespectSchema::class, RespectRules::creditCard());
  }

  public function test_deprecated_sv_shims_still_delegate(): void {
    // Back-compat: SV::* produce the same field type as the driver.
    $this->assertInstanceOf(RawRespectSchema::class, SV::respect(v::email()));
    $this->assertInstanceOf(RawRespectSchema::class, SV::postalCode('JP'));
    $this->assertInstanceOf(RawRespectSchema::class, SV::iban());
  }
}
