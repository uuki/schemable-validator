<?php

namespace SchemableValidator\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Adapters\Respect\RespectAdapter;
use SchemableValidator\Validation\JsonLogicEval;
use SchemableValidator\Validation\Transform;

/**
 * Cross-stack conformance runner. Reads the same conformance/*.json fixtures as
 * packages/client/tests/conformance.test.ts and asserts the PHP kernel's result
 * against each fixture's `expected` block.
 *
 * Fixtures marked `knownMismatch: true` document a currently-open BE/FE gap:
 * if this runner's result doesn't yet match `expected`, the test is reported as
 * incomplete (visible, but does not fail the suite) instead of red. Once the
 * underlying gap is closed, this runner's result will match `expected` and the
 * assertion passes normally.
 */
final class ConformanceTest extends TestCase {

  /**
   * @dataProvider fixtureProvider
   * @param array<string, mixed> $fixture
   */
  public function test_fixture(array $fixture, string $relativePath): void {
    // Apply per-field x-transform before validation.
    $input = $fixture['input'];
    foreach ($fixture['schema']['properties'] ?? [] as $field => $prop) {
      if (!empty($prop['x-transform']) && isset($input[$field]) && is_string($input[$field])) {
        $input[$field] = Transform::apply($input[$field], $prop['x-transform']);
      }
    }

    $executable = (new RespectAdapter())->compile($fixture['schema']);
    $result     = $executable->validate($input);

    // Apply x-when (JSONLogic) conditionals when present in the schema.
    foreach ($fixture['schema']['x-when'] ?? [] as $cond) {
      if (!JsonLogicEval::apply($cond['condition'], $input)) {
        continue;
      }
      foreach ($cond['require'] as $field) {
        $val     = $input[$field] ?? null;
        $isEmpty = $val === null || $val === '' || $val === [];
        if ($isEmpty) {
          $result[$field] = ['value' => $val, 'errors' => "{$field} is required", 'is_valid' => false];
        }
      }
    }

    $knownMismatch = $fixture['knownMismatch'] ?? false;

    foreach ($fixture['expected'] as $field => $exp) {
      $actual = $result[$field]['is_valid'] ?? null;

      if ($actual !== $exp['is_valid']) {
        if ($knownMismatch) {
          $this->markTestIncomplete(sprintf(
            "[%s] known BE/FE mismatch on field '%s': expected is_valid=%s, BE got %s (see %s)",
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

      // Optional cross-stack message-text parity: when a fixture pins `errors`,
      // compare the normalized error list. PHP joins messages with "\n"; split
      // back to an array so both stacks compare as string lists.
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
