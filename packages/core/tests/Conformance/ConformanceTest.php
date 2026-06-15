<?php

namespace SchemableValidator\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\Adapters\RespectAdapter;

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
    $executable = (new RespectAdapter())->compile($fixture['schema']);
    $result     = $executable->validate($fixture['input']);

    $knownMismatch = $fixture['knownMismatch'] ?? false;

    foreach ($fixture['expected'] as $field => $exp) {
      $actual = $result[$field]['is_valid'] ?? null;

      if ($actual === $exp['is_valid']) {
        $this->assertSame($exp['is_valid'], $actual);
        continue;
      }

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
