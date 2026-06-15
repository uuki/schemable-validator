<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Schema\RuleCatalog;
use SchemableValidator\Schema\RuleMapper;
use SchemableValidator\Validation\Adapters\RespectAdapter;

/**
 * Regression tests for Respect/Validation compatibility.
 *
 * PURPOSE
 * When Respect updates (2.x → 3.x), rule names, argument signatures, or
 * behaviours may change. These tests catch that at the RuleMapper boundary.
 *
 * CLOSED-LOOP WORKFLOW (enforced by this test class)
 * Adding a new MAPPED rule requires ALL of the following or tests fail:
 *   1. RuleCatalog::all()          → add entry as STATUS_MAPPED
 *   2. RuleMapper::resolve()       → add switch case
 *   3. mappedRulesProvider()       → add valid/invalid input fixture
 *
 * This prevents a rule being added to one place but silently skipped elsewhere.
 */
class RuleMapperCompatibilityTest extends TestCase {
  // ── Respect/Validation integration ─────────────────────────

  /**
   * For every MAPPED rule: the Respect validator returned by RuleMapper
   * must correctly accept the valid input and reject the invalid input.
   *
   * If Respect changes a rule's name or argument signature, this will fail.
   *
   * @dataProvider mappedRulesProvider
   */
  public function test_mapped_rule_validates_correctly(
    string $rule,
    array  $args,
    $valid,
    $invalid
  ): void {
    $mapping  = RuleMapper::resolve($rule, $args);
    $respect  = RespectAdapter::compileDescriptor($mapping->rule, $mapping->args);

    $this->assertTrue(
      $respect->validate($valid),
      "Rule '{$rule}': valid input should pass Respect validation"
    );
    $this->assertFalse(
      $respect->validate($invalid),
      "Rule '{$rule}': invalid input should fail Respect validation"
    );
  }

  /**
   * Every MAPPED rule must return a non-null jsonSchema array.
   * Also verifies the switch case exists (resolve() would throw otherwise).
   *
   * Fails when: a rule is added to RuleCatalog as MAPPED but RuleMapper
   * has no switch case for it yet.
   */
  public function test_all_mapped_rules_have_json_schema(): void {
    foreach (RuleCatalog::mapped() as $rule) {
      $args    = self::defaultArgsFor($rule);
      $mapping = RuleMapper::resolve($rule, $args);

      $this->assertIsArray(
        $mapping->jsonSchema,
        "MAPPED rule '{$rule}' must produce a non-null jsonSchema"
      );
      $this->assertTrue(
        $mapping->isMappable(),
        "MAPPED rule '{$rule}': isMappable() must return true"
      );
    }
  }

  /**
   * Every MAPPED rule in the catalog must have a fixture in mappedRulesProvider().
   *
   * Fails when: a rule is moved to STATUS_MAPPED in RuleCatalog but
   * no valid/invalid test data has been added to the provider.
   */
  public function test_provider_covers_all_mapped_rules(): void {
    $covered = array_column(self::mappedRulesProvider(), 0);
    foreach (RuleCatalog::mapped() as $rule) {
      $this->assertContains(
        $rule,
        $covered,
        "MAPPED rule '{$rule}' has no integration fixture in mappedRulesProvider()"
      );
    }
  }

  // ── UNMAPPABLE ──────────────────────────────────────────────

  /**
   * UNMAPPABLE rules must resolve without error, have null jsonSchema,
   * and still compile to a working Respect validator via RespectAdapter.
   */
  public function test_unmappable_rules_resolve_with_null_json_schema(): void {
    $mapping = RuleMapper::resolve('fileExt', [['image/jpeg']]);

    $this->assertNull($mapping->jsonSchema);
    $this->assertFalse($mapping->isMappable());
    $this->assertNotNull(
      RespectAdapter::compileDescriptor($mapping->rule, $mapping->args),
      'UNMAPPABLE rule must still compile to a Respect validator'
    );
  }

  // ── Error taxonomy ──────────────────────────────────────────

  /**
   * TODO rules throw RuntimeException with an "escape hatch" hint — not
   * the same InvalidArgumentException as a truly unknown rule.
   * This lets callers distinguish "not yet done" from "misspelled/invalid".
   */
  public function test_todo_rule_throws_runtime_exception_with_escape_hatch_hint(): void {
    $todo = RuleCatalog::todo();
    if (empty($todo)) {
      $this->markTestSkipped('No TODO rules in catalog');
      return;
    }
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/escape hatch/');
    RuleMapper::resolve($todo[0], []);
  }

  /**
   * Rules absent from the catalog entirely throw InvalidArgumentException —
   * a signal that the rule name is wrong or not yet catalogued.
   */
  public function test_unknown_rule_throws_invalid_argument_exception(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/RuleCatalog/');
    RuleMapper::resolve('__completelyUnknown__', []);
  }

  /**
   * Verify that TODO and unknown rules throw DIFFERENT exception types.
   * This is the core contract of the two-tier error taxonomy.
   */
  public function test_todo_and_unknown_throw_different_exception_types(): void {
    $todoException    = null;
    $unknownException = null;

    $todo = RuleCatalog::todo();
    if (!empty($todo)) {
      try {
        RuleMapper::resolve($todo[0], []);
      } catch (\Throwable $e) {
        $todoException = get_class($e);
      }
    }

    try {
      RuleMapper::resolve('__unknown__', []);
    } catch (\Throwable $e) {
      $unknownException = get_class($e);
    }

    if ($todoException !== null) {
      $this->assertNotSame(
        $todoException,
        $unknownException,
        'TODO rules and unknown rules must throw different exception types'
      );
    }
    $this->assertSame(\InvalidArgumentException::class, $unknownException);
  }

  // ── Version-tracking (Respect upgrade safety) ───────────────

  /**
   * Every catalog entry that references a Respect rule must map to an
   * existing Respect class file.
   *
   * FAILS WHEN: Respect renames or removes a built-in rule (e.g. 2.x → 3.x).
   * ACTION:     Update RuleCatalog::entries() 'respect' field to the new name,
   *             then update the RuleMapper switch case accordingly.
   */
  public function test_catalog_respect_references_point_to_existing_classes(): void {
    $reflector = new \ReflectionClass(\Respect\Validation\Validator::class);
    $rulesDir  = dirname($reflector->getFileName()) . '/Rules';

    foreach (RuleCatalog::entries() as $alias => $entry) {
      if ($entry['respect'] === null) {
        continue;  // custom rule — not from Respect, skip
      }

      $classFile = $rulesDir . '/' . ucfirst($entry['respect']) . '.php';
      $this->assertFileExists(
        $classFile,
        "Catalog '{$alias}' references Respect class '" . ucfirst($entry['respect']) . "' " .
        "which no longer exists in the installed Respect package. " .
        "Respect may have renamed or removed this rule."
      );
    }
  }

  /**
   * Every Respect leaf-rule class must appear in RuleCatalog (as any status).
   *
   * FAILS WHEN: Respect adds a new rule that hasn't been evaluated yet.
   * ACTION:     Add the new rule to RuleCatalog as STATUS_TODO with a 'respect'
   *             entry, so it is explicitly acknowledged.
   *
   * NOTE: This test is intentionally strict — even rules we decide are
   * out-of-scope must be listed, with a comment explaining the decision.
   * This prevents new rules from being silently ignored across upgrades.
   */
  public function test_all_respect_rules_are_in_catalog(): void {
    $respectRules   = RuleCatalog::scanRespectRules();
    $catalogedNames = array_filter(
      array_column(RuleCatalog::entries(), 'respect'),
      fn($r) => $r !== null,
    );

    $uncatalogued = array_diff($respectRules, $catalogedNames);

    $this->assertEmpty(
      $uncatalogued,
      sprintf(
        "%d Respect rule(s) not in RuleCatalog — add as STATUS_TODO (or document why excluded):\n  %s",
        count($uncatalogued),
        implode(', ', $uncatalogued),
      )
    );
  }

  // ── Data providers & helpers ────────────────────────────────

  /**
   * One row per MAPPED rule: [rule, args, valid_input, invalid_input]
   *
   * Adding a row here is REQUIRED when moving a rule to STATUS_MAPPED in RuleCatalog.
   * test_provider_covers_all_mapped_rules() enforces this.
   */
  public static function mappedRulesProvider(): array {
    return [
      //  rule        args              valid input                       invalid input
      ['string',   [],                 'hello',                          123                  ],
      ['integer',  [],                 42,                               3.14                 ],
      ['number',   [],                 3.14,                             'hello'              ],
      ['boolean',  [],                 true,                             'maybe'              ],
      ['email',    [],                 'a@example.com',                  'not-an-email'       ],
      ['url',      [],                 'https://ex.com',                 'not-a-url'          ],
      ['length',   [2, 10],            'hello',                          'x'                  ],
      ['min',      [5],                10,                               3                    ],
      ['max',      [5],                3,                                10                   ],
      ['pattern',  ['^[a-z]+$'],       'abc',                            'ABC'                ],
      ['in',       [['a', 'b', 'c']],  'a',                              'd'                  ],
      ['date',     [],                 '2024-01-15',                     'not-a-date'         ],
      ['dateTime', [],                 '2024-01-15T12:00:00+00:00',      'not-a-datetime'     ],
      ['time',     [],                 '12:30:00',                       'not-a-time'         ],
      ['uuid',     [],                 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'not-a-uuid'  ],
      ['ipv4',     [],                 '192.168.1.1',                    '256.0.0.1'          ],
      ['ipv6',     [],                 '2001:db8::1',                    'not-an-ip'          ],
      ['slug',     [],                 'my-slug-123',                    'My Slug!'           ],
      ['domain',   [],                 'example.com',                    'not a domain'       ],
    ];
  }

  /** Minimal valid args for each MAPPED rule (used in catalog-sweep tests). */
  private static function defaultArgsFor(string $rule): array {
    $defaults = [
      'string'   => [],
      'integer'  => [],
      'number'   => [],
      'boolean'  => [],
      'email'    => [],
      'url'      => [],
      'length'   => [1, null],
      'min'      => [0],
      'max'      => [100],
      'pattern'  => ['^.+$'],
      'in'       => [['a', 'b']],
      'date'     => [],
      'dateTime' => [],
      'time'     => [],
      'uuid'     => [],
      'ipv4'     => [],
      'ipv6'     => [],
      'slug'     => [],
      'domain'   => [],
    ];
    return $defaults[$rule] ?? [];
  }
}
