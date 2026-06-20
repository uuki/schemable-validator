<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\SV;

/**
 * Verifies that validate() returns raw, unmodified values.
 *
 * Sanitization is the responsibility of each output layer
 * (esc_html for HTML, header stripping for email, etc.).
 */
class SecurityTest extends TestCase
{
  private function makeValidator(): \SchemableValidator\Validator
  {
    return SV::object(['field' => SV::string()])->toValidator();
  }

  public function test_validate_returns_raw_html_tags(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => '<b>hello</b>'])
      ->getResult();

    $this->assertSame('<b>hello</b>', $result['field']['value']);
  }

  public function test_validate_returns_raw_special_chars(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => '"quoted" & <escaped>'])
      ->getResult();

    // Raw value is returned; the caller must escape for their output context
    $this->assertSame('"quoted" & <escaped>', $result['field']['value']);
  }

  public function test_validate_preserves_newlines(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => "line1\nline2"])
      ->getResult();

    $this->assertStringContainsString("\n", $result['field']['value']);
  }
}
