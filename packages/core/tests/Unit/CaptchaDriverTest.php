<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\SV;
use SchemableValidator\Adapters\Captcha\HCaptchaDriver;
use SchemableValidator\Adapters\Captcha\NullCaptchaDriver;
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Captcha\TurnstileDriver;

/**
 * Tests for CaptchaDriver implementations and validateCaptcha() integration.
 *
 * Network-dependent drivers (ReCaptchaV3Driver, HCaptchaDriver, TurnstileDriver)
 * are tested only for construction-time guards. Actual HTTP calls require
 * integration tests with real credentials.
 */
final class CaptchaDriverTest extends TestCase {

  // ── NullCaptchaDriver ─────────────────────────────────────────

  public function test_null_driver_passes_by_default(): void {
    $r = (new NullCaptchaDriver())->verify('any-token');
    $this->assertTrue($r['is_valid']);
    $this->assertNull($r['errors']);
    $this->assertNull($r['score']);
  }

  public function test_null_driver_fails_when_constructed_false(): void {
    $r = (new NullCaptchaDriver(false))->verify('any-token');
    $this->assertFalse($r['is_valid']);
    $this->assertNotNull($r['errors']);
  }

  // ── ReCaptchaV3Driver construction guards ─────────────────────

  public function test_recaptcha_rejects_empty_secret(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ReCaptchaV3Driver('');
  }

  public function test_recaptcha_rejects_unknown_endpoint(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ReCaptchaV3Driver('secret', 0.5, 'https://evil.example.com/verify');
  }

  public function test_recaptcha_accepts_official_google_endpoint(): void {
    $driver = new ReCaptchaV3Driver('secret', 0.5, 'https://www.google.com/recaptcha/api/siteverify');
    $this->assertInstanceOf(ReCaptchaV3Driver::class, $driver);
  }

  public function test_recaptcha_accepts_recaptcha_net_endpoint(): void {
    $driver = new ReCaptchaV3Driver('secret', 0.5, 'https://www.recaptcha.net/recaptcha/api/siteverify');
    $this->assertInstanceOf(ReCaptchaV3Driver::class, $driver);
  }

  // ── HCaptchaDriver construction guards ────────────────────────

  public function test_hcaptcha_rejects_empty_secret(): void {
    $this->expectException(\InvalidArgumentException::class);
    new HCaptchaDriver('');
  }

  public function test_hcaptcha_accepts_valid_secret(): void {
    $driver = new HCaptchaDriver('my-secret');
    $this->assertInstanceOf(HCaptchaDriver::class, $driver);
  }

  public function test_hcaptcha_accepts_optional_site_key(): void {
    $driver = new HCaptchaDriver('my-secret', 'my-site-key');
    $this->assertInstanceOf(HCaptchaDriver::class, $driver);
  }

  // ── TurnstileDriver construction guards ───────────────────────

  public function test_turnstile_rejects_empty_secret(): void {
    $this->expectException(\InvalidArgumentException::class);
    new TurnstileDriver('');
  }

  public function test_turnstile_accepts_valid_secret(): void {
    $driver = new TurnstileDriver('my-secret');
    $this->assertInstanceOf(TurnstileDriver::class, $driver);
  }

  // ── empty-token short-circuit (no network call) ──────────────

  public function test_recaptcha_verify_empty_token_returns_invalid(): void {
    $driver = new ReCaptchaV3Driver('secret');
    $r      = $driver->verify('');
    $this->assertFalse($r['is_valid']);
    $this->assertSame('CAPTCHA token is missing', $r['errors']);
  }

  public function test_hcaptcha_verify_empty_token_returns_invalid(): void {
    $driver = new HCaptchaDriver('secret');
    $r      = $driver->verify('');
    $this->assertFalse($r['is_valid']);
    $this->assertSame('CAPTCHA token is missing', $r['errors']);
  }

  public function test_turnstile_verify_empty_token_returns_invalid(): void {
    $driver = new TurnstileDriver('secret');
    $r      = $driver->verify('');
    $this->assertFalse($r['is_valid']);
    $this->assertSame('CAPTCHA token is missing', $r['errors']);
  }

  public function test_validateCaptcha_returns_invalid_when_no_token_in_post(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(true)]);

    // No CAPTCHA field in the POST data — validateCaptcha must short-circuit locally.
    $result = $validator
      ->validate(['name' => 'Alice'])
      ->validateCaptcha()
      ->getResult();

    $this->assertFalse($result['captcha']['is_valid']);
    $this->assertSame('CAPTCHA token is missing', $result['captcha']['errors']);
  }

  // ── hostname verification (hCaptcha / Turnstile) ─────────────

  public function test_hcaptcha_accepts_expected_hostname_param(): void {
    $driver = new HCaptchaDriver('secret', null, 'example.com');
    $this->assertInstanceOf(HCaptchaDriver::class, $driver);
  }

  public function test_turnstile_accepts_expected_hostname_param(): void {
    $driver = new TurnstileDriver('secret', 'example.com');
    $this->assertInstanceOf(TurnstileDriver::class, $driver);
  }

  // ── validateCaptcha() integration ────────────────────────────

  public function test_validateCaptcha_passes_with_null_driver(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(true)]);

    $result = $validator
      ->validate(['name' => 'Alice', 'recaptcha_token' => 'tok'])
      ->validateCaptcha()
      ->getResult();

    $this->assertTrue($result['captcha']['is_valid']);
    $this->assertNull($result['captcha']['errors']);
  }

  public function test_validateCaptcha_fails_with_rejecting_null_driver(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(false)]);

    $result = $validator
      ->validate(['name' => 'Alice', 'recaptcha_token' => 'tok'])
      ->validateCaptcha()
      ->getResult();

    $this->assertFalse($result['captcha']['is_valid']);
    $this->assertNotNull($result['captcha']['errors']);
  }

  public function test_validateCaptcha_throws_without_driver(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/CaptchaDriver/');

    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(); // no captchaDriver

    $validator->validate(['name' => 'Alice'])->validateCaptcha();
  }

  public function test_validateCaptcha_reads_g_recaptcha_response_field(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(true)]);

    // Token sent as g-recaptcha-response (reCAPTCHA standard field name)
    $result = $validator
      ->validate(['name' => 'Alice', 'g-recaptcha-response' => 'tok'])
      ->validateCaptcha()
      ->getResult();

    $this->assertTrue($result['captcha']['is_valid']);
  }

  public function test_validateCaptcha_reads_h_captcha_response_field(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(true)]);

    $result = $validator
      ->validate(['name' => 'Alice', 'h-captcha-response' => 'tok'])
      ->validateCaptcha()
      ->getResult();

    $this->assertTrue($result['captcha']['is_valid']);
  }

  public function test_validateCaptcha_reads_cf_turnstile_response_field(): void {
    $schema    = SV::object(['name' => SV::string()]);
    $validator = $schema->toValidator(['captchaDriver' => new NullCaptchaDriver(true)]);

    $result = $validator
      ->validate(['name' => 'Alice', 'cf-turnstile-response' => 'tok'])
      ->validateCaptcha()
      ->getResult();

    $this->assertTrue($result['captcha']['is_valid']);
  }
}
