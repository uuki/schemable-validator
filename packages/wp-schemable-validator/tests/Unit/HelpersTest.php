<?php

namespace SchemableValidator\Interfaces\WordPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SchemableValidator\Contracts\SchemaProviderInterface;

class HelpersTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Monkey\setUp();
    require_once __DIR__ . '/../../src/Interfaces/WordPress/helpers.php';
  }

  protected function tearDown(): void
  {
    Monkey\tearDown();
    parent::tearDown();
  }

  private function makeProvider(): SchemaProviderInterface
  {
    return new class implements SchemaProviderInterface {
      public function toJsonSchema(): array { return ['type' => 'object']; }
      public function toJson(): string { return '{"type":"object"}'; }
    };
  }

  public function test_schv_register_schema_calls_add_action(): void
  {
    $hookRegistered = false;
    Functions\expect('add_action')
      ->once()
      ->with('rest_api_init', \Mockery::type('callable'))
      ->andReturnUsing(function () use (&$hookRegistered): void {
        $hookRegistered = true;
      });

    schv_register_schema('/contact', $this->makeProvider());

    $this->assertTrue($hookRegistered, 'add_action(rest_api_init) was not called');
  }

  public function test_schv_schema_url_returns_rest_url(): void
  {
    Functions\expect('get_rest_url')
      ->once()
      ->with(null, 'schv/v1/contact')
      ->andReturn('https://example.com/wp-json/schv/v1/contact');

    $url = schv_schema_url('/contact');

    $this->assertSame('https://example.com/wp-json/schv/v1/contact', $url);
  }

  public function test_schv_schema_url_handles_nested_route(): void
  {
    Functions\expect('get_rest_url')
      ->once()
      ->with(null, 'schv/v1/forms/signup')
      ->andReturn('https://example.com/wp-json/schv/v1/forms/signup');

    $url = schv_schema_url('/forms/signup');

    $this->assertSame('https://example.com/wp-json/schv/v1/forms/signup', $url);
  }
}
