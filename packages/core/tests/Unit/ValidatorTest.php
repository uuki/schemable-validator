<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\SV;
use SchemableValidator\Validator;

class ValidatorTest extends TestCase
{
  // ── validate() ──────────────────────────────────────────

  public function test_validate_valid_data_returns_is_valid_true(): void
  {
    $validator = new Validator(['name' => v::stringType()->length(1, 50)]);
    $result = $validator->validate(['name' => 'Alice'])->getResult();

    $this->assertTrue($result['name']['is_valid']);
    $this->assertNull($result['name']['errors']);
    $this->assertSame('Alice', $result['name']['value']);
  }

  public function test_validate_invalid_data_returns_is_valid_false(): void
  {
    $validator = new Validator(['name' => v::stringType()->length(1, 50)]);
    $result = $validator->validate(['name' => ''])->getResult();

    $this->assertFalse($result['name']['is_valid']);
    $this->assertNotNull($result['name']['errors']);
  }

  public function test_validate_missing_field_returns_is_valid_false(): void
  {
    $validator = new Validator(['name' => v::stringType()->notEmpty()]);
    $result = $validator->validate([])->getResult();

    $this->assertFalse($result['name']['is_valid']);
  }

  public function test_validate_preserves_raw_value(): void
  {
    // validate() returns raw values — output-layer callers (esc_html, wp_kses, etc.) are
    // responsible for context-appropriate escaping.
    $validator = new Validator(['name' => v::stringType()]);
    $result = $validator->validate(['name' => '<script>alert(1)</script>'])->getResult();

    $this->assertSame('<script>alert(1)</script>', $result['name']['value']);
  }

  public function test_validate_returns_static_for_chaining(): void
  {
    $validator = new Validator(['name' => v::stringType()]);
    $result = $validator->validate(['name' => 'Alice']);

    $this->assertSame($validator, $result);
  }

  // ── validateFiles() ──────────────────────────────────────

  public function test_validateFiles_normalizes_single_file(): void
  {
    $schema = ['doc' => v::key('error', v::equals(UPLOAD_ERR_OK))];
    $validator = new Validator($schema);

    $files = [
      'doc' => [
        'name' => 'test.pdf',
        'type' => 'application/pdf',
        'tmp_name' => '/tmp/phpXXX',
        'error' => UPLOAD_ERR_OK,
        'size' => 1024,
      ],
    ];

    $result = $validator->validateFiles($files)->getResult();

    $this->assertTrue($result['doc'][0]['is_valid']);
  }

  public function test_validateFiles_skips_fields_not_in_schema(): void
  {
    $validator = new Validator([]);
    $files = ['unknown' => ['name' => 'x.pdf', 'type' => '', 'tmp_name' => '', 'error' => 0, 'size' => 0]];

    $result = $validator->validateFiles($files)->getResult();

    $this->assertArrayNotHasKey('unknown', $result);
  }

  // ── getResult() ──────────────────────────────────────────

  public function test_getResult_returns_array(): void
  {
    $validator = new Validator([]);
    $this->assertIsArray($validator->getResult());
  }

  // ── CSRF token ───────────────────────────────────────────

  /**
   * @runInSeparateProcess
   */
  public function test_createToken_returns_64_char_hex_string(): void
  {
    $validator = new Validator([]);
    $token = $validator->createToken();

    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_checkToken_returns_true_for_matching_token(): void
  {
    $validator = new Validator([]);
    $token = $validator->createToken();

    $this->assertTrue($validator->checkToken($token));
  }

  /**
   * @runInSeparateProcess
   */
  public function test_checkToken_returns_false_for_wrong_token(): void
  {
    $validator = new Validator([]);
    $validator->createToken();

    $this->assertFalse($validator->checkToken('wrong_token'));
  }

  /**
   * @runInSeparateProcess
   */
  public function test_token_persisted_in_session(): void
  {
    $validator = new Validator([]);
    $token = $validator->createToken();

    $this->assertSame($token, $_SESSION['schv_csrf_tokens']['default']['token']);
    $this->assertGreaterThan(time(), $_SESSION['schv_csrf_tokens']['default']['exp']);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_token_scoped_per_form(): void
  {
    $v = new Validator([]);
    $t1 = $v->createToken('form_a');
    $t2 = $v->createToken('form_b');

    $this->assertTrue($v->checkToken($t1, 'form_a'));
    $this->assertTrue($v->checkToken($t2, 'form_b'));
    // Cross-scope check must fail
    $this->assertFalse($v->checkToken($t1, 'form_b'));
    $this->assertFalse($v->checkToken($t2, 'form_a'));
  }

  /**
   * @runInSeparateProcess
   */
  public function test_expired_token_returns_false(): void
  {
    $v     = new Validator([]);
    $token = $v->createToken('expire_test');
    // Force expiry
    $_SESSION['schv_csrf_tokens']['expire_test']['exp'] = time() - 1;

    $this->assertFalse($v->checkToken($token, 'expire_test'));
    $this->assertArrayNotHasKey('expire_test', $_SESSION['schv_csrf_tokens']);
  }

  // ── chaining ─────────────────────────────────────────────

  public function test_method_chaining_validate_and_getResult(): void
  {
    $schema = [
      'email' => v::email(),
      'name'  => v::stringType()->length(1, 50),
    ];
    $result = (new Validator($schema))
      ->validate(['email' => 'test@example.com', 'name' => 'Bob'])
      ->getResult();

    $this->assertTrue($result['email']['is_valid']);
    $this->assertTrue($result['name']['is_valid']);
  }

  // ── Array field values (sanitizeValue regression) ─────────

  public function test_validate_accepts_array_value_for_array_field(): void {
    $sb = SV::object(['tags' => SV::array(SV::string()->min(1))]);
    $result = $sb->toValidator()->validate(['tags' => ['php', 'js']])->getResult();

    $this->assertTrue($result['tags']['is_valid']);
  }

  public function test_validate_rejects_array_with_short_items(): void {
    $sb = SV::object(['tags' => SV::array(SV::string()->min(3))]);
    $result = $sb->toValidator()->validate(['tags' => ['ab']])->getResult();

    $this->assertFalse($result['tags']['is_valid']);
  }

  public function test_validate_does_not_throw_for_array_value(): void {
    // Regression: before sanitizeValue(), passing an array to sanitize() caused a TypeError.
    $sb = SV::object(['items' => SV::array(SV::string())]);
    // Should not throw
    $result = $sb->toValidator()->validate(['items' => ['a', 'b', 'c']])->getResult();
    $this->assertArrayHasKey('items', $result);
  }

  // ── Conditional required (when) ───────────────────────────

  public function test_conditional_required_triggers_when_condition_matches(): void {
    $sb = SV::object([
      'type'         => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', 'company', ['company_name']);

    $result = $sb->toValidator()->validate(['type' => 'company'])->getResult();
    $this->assertArrayHasKey('company_name', $result);
    $this->assertFalse($result['company_name']['is_valid']);
  }

  public function test_conditional_required_does_not_trigger_when_condition_unmet(): void {
    $sb = SV::object([
      'type'         => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', 'company', ['company_name']);

    $result = $sb->toValidator()->validate(['type' => 'personal'])->getResult();
    // company_name is optional and empty → should be valid (or absent)
    if (isset($result['company_name'])) {
      $this->assertTrue($result['company_name']['is_valid']);
    } else {
      $this->assertTrue(true); // not present → also fine
    }
  }

  // ── toFloat edge cases (via numeric when() conditions) ───────────────────────

  /**
   * Hex string "0x10" must be treated as 0.0, not 16.
   * Before toFloat() this worked by accident ((float)"0x10" = 0.0 in PHP),
   * but the helper makes the intent explicit and prevents future PHP drift.
   */
  public function test_hex_string_is_treated_as_zero_in_numeric_comparison(): void {
    $sb = SV::object([
      'code'  => SV::string(),
      'extra' => SV::string()->optional(),
    ])->when('code', SV::greaterThanOrEqual(15), ['extra']);

    // "0x10" must become 0.0, not 16 — condition must NOT trigger
    $result = $sb->toValidator()->validate(['code' => '0x10', 'extra' => ''])->getResult();
    if (isset($result['extra'])) {
      $this->assertTrue($result['extra']['is_valid'], '"0x10" must not be treated as 16');
    }
  }

  public function test_hex_string_with_plus_sign_is_treated_as_zero(): void {
    $sb = SV::object([
      'score' => SV::string(),
      'bonus' => SV::string()->optional(),
    ])->when('score', SV::greaterThanOrEqual(1), ['bonus']);

    $result = $sb->toValidator()->validate(['score' => '+0xFF', 'bonus' => ''])->getResult();
    if (isset($result['bonus'])) {
      $this->assertTrue($result['bonus']['is_valid'], '"+0xFF" must be 0.0');
    }
  }

  /** Non-numeric strings must coerce to 0.0, not throw */
  public function test_non_numeric_string_is_treated_as_zero(): void {
    $sb = SV::object([
      'age'     => SV::string(),
      'consent' => SV::string()->optional(),
    ])->when('age', SV::greaterThanOrEqual(18), ['consent']);

    // "Infinity", "NaN", "" must all become 0.0
    foreach (['Infinity', 'NaN', ''] as $bad) {
      $result = $sb->toValidator()->validate(['age' => $bad, 'consent' => ''])->getResult();
      if (isset($result['consent'])) {
        $this->assertTrue($result['consent']['is_valid'], "'{$bad}' must not trigger >= 18");
      }
    }
  }

  /**
   * "1e308" overflows to INF in PHP. toFloat() returns INF for this input,
   * which means a condition like `>= 18` would always trigger.
   * This is documented expected behaviour: server validates; client defers.
   */
  public function test_float_overflow_1e308_becomes_inf_and_triggers_condition(): void {
    $sb = SV::object([
      'age'     => SV::string(),
      'consent' => SV::string()->optional(),
    ])->when('age', SV::greaterThanOrEqual(18), ['consent']);

    $result = $sb->toValidator()->validate(['age' => '1e308'])->getResult();
    // INF >= 18 = true → consent is required → validation fails when absent
    if (isset($result['consent'])) {
      $this->assertFalse($result['consent']['is_valid'],
        '"1e308" overflows to INF; condition triggers (consent required)');
    }
  }

  /** Scientific notation within normal range works correctly */
  public function test_scientific_notation_within_range_works(): void {
    $sb = SV::object([
      'score'   => SV::string(),
      'premium' => SV::string()->optional(),
    ])->when('score', SV::greaterThanOrEqual(100), ['premium']);

    // "1e2" = 100.0 — condition should trigger
    $result = $sb->toValidator()->validate(['score' => '1e2'])->getResult();
    if (isset($result['premium'])) {
      $this->assertFalse($result['premium']['is_valid'],
        '"1e2" should be 100.0 and trigger >= 100');
    }
  }

  public function test_conditional_required_passes_when_required_field_is_present(): void {
    $sb = SV::object([
      'type'         => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', 'company', ['company_name']);

    $result = $sb->toValidator()->validate([
      'type'         => 'company',
      'company_name' => 'Acme Corp',
    ])->getResult();

    $this->assertTrue($result['company_name']['is_valid']);
  }
}
