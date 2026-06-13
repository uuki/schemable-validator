<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Controllers\FormController;
use SchemableValidator\Template;

/**
 * Verifies that Template::get() strips characters that could be exploited for
 * email header injection (CRLF) and documents the behaviour of other special
 * characters that are NOT treated as threats in the SMTP header context.
 */
class TemplateInjectionTest extends TestCase
{
  private const TEMPLATE_NAME = 'mail';

  /**
   * Seed the session-backed form store with a single field value and return a
   * Template instance wired to use it.
   */
  private function makeTemplate(string $value): Template
  {
    $ctrl = new FormController();
    $ctrl->save(['name' => ['value' => $value, 'is_valid' => true, 'errors' => null]]);

    return new Template([
      'aliases'   => ['name' => 'name'],
      'templates' => [self::TEMPLATE_NAME => 'Hello, {name}!'],
    ]);
  }

  // ── CRLF stripping (A03-3) ────────────────────────────────────────────────

  /**
   * @runInSeparateProcess
   */
  public function test_crlf_in_value_is_stripped(): void
  {
    $t = $this->makeTemplate("Alice\r\nBcc: attacker@example.com");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringNotContainsString("\r", $out);
    $this->assertStringNotContainsString("\n", $out);
    $this->assertStringContainsString('Alice', $out);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_cr_only_is_stripped(): void
  {
    $t = $this->makeTemplate("Alice\rBcc: attacker@example.com");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringNotContainsString("\r", $out);
    $this->assertStringContainsString('Alice', $out);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_lf_only_is_stripped(): void
  {
    $t = $this->makeTemplate("Alice\nBcc: attacker@example.com");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringNotContainsString("\n", $out);
    $this->assertStringContainsString('Alice', $out);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_multiple_crlf_sequences_are_all_stripped(): void
  {
    $t = $this->makeTemplate("A\r\nBcc: x\r\nX-Extra: y");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringNotContainsString("\r", $out);
    $this->assertStringNotContainsString("\n", $out);
    // Surrounding literal text remains intact
    $this->assertStringContainsString('Hello,', $out);
  }

  /**
   * @runInSeparateProcess
   */
  public function test_value_consisting_only_of_crlf_becomes_empty(): void
  {
    $t = $this->makeTemplate("\r\n\r\n");
    $out = $t->get(self::TEMPLATE_NAME);

    // Placeholder replaced with empty string; no newlines in output
    $this->assertStringNotContainsString("\r", $out);
    $this->assertStringNotContainsString("\n", $out);
    $this->assertSame('Hello, !', $out);
  }

  // ── Non-SMTP-header-injection characters (document safe pass-through) ─────

  /**
   * Null bytes are not SMTP header delimiters; they pass through as-is.
   * Output-layer callers are responsible for stripping if needed.
   *
   * @runInSeparateProcess
   */
  public function test_null_byte_passes_through(): void
  {
    $t = $this->makeTemplate("Alice\x00");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringContainsString("\x00", $out);
  }

  /**
   * Backslash has no special meaning in SMTP headers and passes through.
   *
   * @runInSeparateProcess
   */
  public function test_backslash_passes_through(): void
  {
    $t = $this->makeTemplate('C:\\Users\\Alice');
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringContainsString('C:\\Users\\Alice', $out);
  }

  /**
   * Tab (\t) is technically a valid folding whitespace in RFC 5322 header
   * values but is not an injection vector for new header lines. Passes through.
   *
   * @runInSeparateProcess
   */
  public function test_tab_passes_through(): void
  {
    $t = $this->makeTemplate("Alice\tSmith");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringContainsString("\t", $out);
  }

  /**
   * Unicode line separator U+2028 (E2 80 A8) is NOT an SMTP header delimiter
   * (SMTP uses ASCII CR/LF only) and therefore is not stripped.
   * Output contexts that interpret U+2028 as a line break (JS, some templating
   * engines) must strip it at render time.
   *
   * @runInSeparateProcess
   */
  public function test_unicode_line_separator_u2028_is_not_stripped(): void
  {
    $lineSep = "\u{2028}";
    $t = $this->makeTemplate("Alice{$lineSep}Smith");
    $out = $t->get(self::TEMPLATE_NAME);

    // Documented: U+2028 is preserved — not an SMTP injection vector
    $this->assertStringContainsString($lineSep, $out);
  }

  /**
   * Zero-width space U+200B passes through; not a control character.
   *
   * @runInSeparateProcess
   */
  public function test_zero_width_space_passes_through(): void
  {
    $zwsp = "\u{200B}";
    $t = $this->makeTemplate("Alice{$zwsp}Smith");
    $out = $t->get(self::TEMPLATE_NAME);

    $this->assertStringContainsString($zwsp, $out);
  }
}
