<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
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

  public function test_validate_sanitizes_input(): void
  {
    $validator = new Validator(['name' => v::stringType()]);
    $result = $validator->validate(['name' => '<script>alert(1)</script>'])->getResult();

    $this->assertStringNotContainsString('<script>', $result['name']['value']);
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

    $this->assertSame($token, $_SESSION['schv_csrf_token']);
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
}
