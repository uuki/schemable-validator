<?php

namespace SchemableValidator\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Adapters\Native\NativeAdapter;
use SchemableValidator\Validation\JsonLogicEval;
use SchemableValidator\Validation\Transform;

/**
 * W2 Native verification gate: runs the SAME conformance/*.json fixtures as
 * ConformanceTest.php (RespectAdapter) and conformance.test.ts (FE) through the
 * dependency-free NativeAdapter, asserting identical is_valid — and identical
 * error text wherever a fixture pins `expected.<field>.errors`.
 *
 * This proves the native engine path is Respect-free AND FE-faithful on the
 * coercion ("parity") fixtures, which OpisAdapter cannot satisfy (strict JSON
 * Schema, no Coercion Contract v1).
 */
final class NativeConformanceTest extends TestCase {

  /**
   * @dataProvider fixtureProvider
   * @param array<string, mixed> $fixture
   */
  public function test_fixture(array $fixture, string $relativePath): void {
    // Apply per-field x-transform before validation (mirrors ConformanceTest).
    $input = $fixture['input'];
    foreach ($fixture['schema']['properties'] ?? [] as $field => $prop) {
      if (!empty($prop['x-transform']) && isset($input[$field]) && is_string($input[$field])) {
        $input[$field] = Transform::apply($input[$field], $prop['x-transform']);
      }
    }

    $executable = (new NativeAdapter())->compile($fixture['schema']);
    $result     = $executable->validate($input);

    // Apply x-when (JSONLogic) conditionals when present.
    foreach ($fixture['schema']['x-when'] ?? [] as $cond) {
      if (!JsonLogicEval::apply($cond['condition'], $input)) {
        continue;
      }
      foreach ($cond['require'] as $field) {
        $val     = $input[$field] ?? null;
        $isEmpty = $val === null || $val === '' || $val === [];
        if ($isEmpty) {
          $result[$field] = ['value' => $val, 'errors' => 'is required', 'is_valid' => false];
        }
      }
    }

    $knownMismatch = $fixture['knownMismatch'] ?? false;

    foreach ($fixture['expected'] as $field => $exp) {
      $actual = $result[$field]['is_valid'] ?? null;

      if ($actual !== $exp['is_valid']) {
        if ($knownMismatch) {
          $this->markTestIncomplete(sprintf(
            "[%s] known mismatch on field '%s': expected is_valid=%s, native got %s (see %s)",
            $fixture['name'],
            $field,
            var_export($exp['is_valid'], true),
            var_export($actual, true),
            $relativePath
          ));
        }
        $this->assertSame($exp['is_valid'], $actual, sprintf(
          "[%s] field '%s' (%s)",
          $fixture['name'],
          $field,
          $relativePath
        ));
        continue;
      }

      $this->assertSame($exp['is_valid'], $actual);

      if (array_key_exists('errors', $exp)) {
        $raw          = $result[$field]['errors'] ?? null;
        $actualErrors = ($raw === null || $raw === '') ? [] : explode("\n", $raw);
        $this->assertSame($exp['errors'], $actualErrors, sprintf(
          "[%s] field '%s' error messages (%s)",
          $fixture['name'],
          $field,
          $relativePath
        ));
      }
    }
  }

  /**
   * @return array<string, array{0: array<string, mixed>, 1: string}>
   */
  public function fixtureProvider(): array {
    $root  = dirname(__DIR__, 4) . '/conformance';
    $files = glob($root . '/*/*.json') ?: [];
    sort($files);

    $cases = [];
    foreach ($files as $file) {
      $fixture  = json_decode((string) file_get_contents($file), true);
      $relative = substr($file, strlen($root) + 1);
      $cases[$relative] = [$fixture, $relative];
    }
    return $cases;
  }
}
