<?php

namespace SchemableValidator\Interfaces\WordPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SchemableValidator\Interfaces\WordPress\SchemaEndpoint;
use SchemableValidator\Contracts\SchemaProviderInterface;

class SchemaEndpointTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Monkey\setUp();
  }

  protected function tearDown(): void
  {
    Monkey\tearDown();
    parent::tearDown();
  }

  private function makeProvider(array $schema): SchemaProviderInterface
  {
    return new class ($schema) implements SchemaProviderInterface {
      private array $schema;
      public function __construct(array $schema) { $this->schema = $schema; }
      public function toJsonSchema(): array { return $this->schema; }
      public function toJson(): string { return (string) json_encode($this->schema); }
    };
  }

  public function test_register_hooks_rest_api_init(): void
  {
    $hooked = false;
    Functions\expect('add_action')
      ->once()
      ->with('rest_api_init', \Mockery::type('callable'))
      ->andReturnUsing(function (string $hook, callable $cb) use (&$hooked): void {
        $hooked = true;
      });

    SchemaEndpoint::register('/contact', $this->makeProvider(['type' => 'object']));

    $this->assertTrue($hooked);
  }

  public function test_callback_registers_rest_route(): void
  {
    $routeArgs = null;

    Functions\expect('add_action')
      ->once()
      ->with('rest_api_init', \Mockery::type('callable'))
      ->andReturnUsing(function (string $hook, callable $cb) use (&$routeArgs): void {
        // Simulate WP firing rest_api_init
        Functions\expect('register_rest_route')
          ->once()
          ->with('schv/v1', '/contact', \Mockery::type('array'))
          ->andReturnUsing(function (string $ns, string $route, array $args) use (&$routeArgs): void {
            $routeArgs = $args;
          });

        $cb();
      });

    SchemaEndpoint::register('/contact', $this->makeProvider(['type' => 'object']));

    $this->assertSame('GET', $routeArgs['methods']);
    $this->assertSame('__return_true', $routeArgs['permission_callback']);
    $this->assertIsCallable($routeArgs['callback']);
  }

  public function test_callback_returns_schema_with_etag(): void
  {
    $schema   = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
    $provider = $this->makeProvider($schema);

    Functions\expect('add_action')
      ->once()
      ->andReturnUsing(function (string $hook, callable $cb): void {
        Functions\expect('register_rest_route')->once()->andReturnUsing(
          function (string $ns, string $route, array $args): void {
            /** @var \WP_REST_Response $response */
            $response = $args['callback']();

            $this->assertInstanceOf(\WP_REST_Response::class, $response);
            $this->assertSame(200, $response->getStatus());
            $this->assertNotEmpty($response->getHeader('etag'));
          }
        );
        $cb();
      });

    SchemaEndpoint::register('/contact', $provider);
  }

  public function test_callback_returns_304_on_matching_etag(): void
  {
    $schema = ['type' => 'object'];
    $etag   = '"' . md5(serialize($schema)) . '"';

    $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

    Functions\expect('add_action')
      ->once()
      ->andReturnUsing(function (string $hook, callable $cb) use ($etag): void {
        Functions\expect('register_rest_route')->once()->andReturnUsing(
          function (string $ns, string $route, array $args) use ($etag): void {
            /** @var \WP_REST_Response $response */
            $response = $args['callback']();

            $this->assertSame(304, $response->getStatus());
            $this->assertNull($response->getData());
          }
        );
        $cb();
      });

    SchemaEndpoint::register('/schema', $this->makeProvider($schema));

    unset($_SERVER['HTTP_IF_NONE_MATCH']);
  }

  public function test_callback_returns_200_on_mismatched_etag(): void
  {
    $schema = ['type' => 'object'];
    $_SERVER['HTTP_IF_NONE_MATCH'] = '"stale-etag"';

    Functions\expect('add_action')
      ->once()
      ->andReturnUsing(function (string $hook, callable $cb): void {
        Functions\expect('register_rest_route')->once()->andReturnUsing(
          function (string $ns, string $route, array $args): void {
            /** @var \WP_REST_Response $response */
            $response = $args['callback']();

            $this->assertSame(200, $response->getStatus());
          }
        );
        $cb();
      });

    SchemaEndpoint::register('/schema', $this->makeProvider($schema));

    unset($_SERVER['HTTP_IF_NONE_MATCH']);
  }

  public function test_etag_changes_when_schema_changes(): void
  {
    $schema1  = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];
    $schema2  = ['type' => 'object', 'properties' => ['b' => ['type' => 'string']]];

    $etag1 = '"' . md5(serialize($schema1)) . '"';
    $etag2 = '"' . md5(serialize($schema2)) . '"';

    $this->assertNotSame($etag1, $etag2);
  }
}
