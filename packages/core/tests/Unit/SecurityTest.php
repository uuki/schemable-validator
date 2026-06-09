<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\Validator;

class SecurityTest extends TestCase
{
  private function makeValidator(): Validator
  {
    return new Validator(['field' => v::stringType()]);
  }

  public function test_sanitize_strips_html_tags(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => '<b>hello</b>'])
      ->getResult();

    $this->assertSame('hello', $result['field']['value']);
  }

  public function test_sanitize_escapes_special_chars(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => '"quoted" & <escaped>'])
      ->getResult();

    $this->assertStringContainsString('&amp;', $result['field']['value']);
    $this->assertStringNotContainsString('<', $result['field']['value']);
  }

  public function test_sanitize_preserves_newlines_by_default(): void
  {
    $result = $this->makeValidator()
      ->validate(['field' => "line1\nline2"])
      ->getResult();

    $this->assertStringContainsString("\n", $result['field']['value']);
  }
}
