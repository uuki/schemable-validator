<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\SV;
use SchemableValidator\Validator;

class MessageDictTest extends TestCase
{
  // ── resolve() priority ──────────────────────────────────────

  public function test_resolve_returns_field_rule_specific(): void
  {
    $dict = new MessageDict(['email' => ['email' => 'カスタムメール']]);
    $this->assertSame('カスタムメール', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_returns_field_wide_string(): void
  {
    $dict = new MessageDict(['email' => 'フィールド全体メッセージ']);
    $this->assertSame('フィールド全体メッセージ', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_returns_locale_default(): void
  {
    $dict = new MessageDict([], ['email' => 'デフォルトメール']);
    $this->assertSame('デフォルトメール', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_returns_fallback_when_no_match(): void
  {
    $dict = new MessageDict();
    $this->assertSame('fallback', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_field_rule_takes_priority_over_field_wide(): void
  {
    $dict = new MessageDict([
      'email' => ['email' => 'ルール固有'],
    ], ['email' => 'デフォルト']);
    // field+rule wins over defaults and field-wide (here field entry is array, not string)
    $this->assertSame('ルール固有', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_field_wide_takes_priority_over_default(): void
  {
    $dict = new MessageDict(
      ['email' => 'フィールド全体'],
      ['email' => 'デフォルト']
    );
    $this->assertSame('フィールド全体', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_resolve_default_takes_priority_over_fallback(): void
  {
    $dict = new MessageDict([], ['length' => 'デフォルト長さ']);
    $this->assertSame('デフォルト長さ', $dict->resolve('name', 'length', 'fallback'));
  }

  public function test_resolve_unknown_field_uses_default(): void
  {
    $dict = new MessageDict(['other_field' => 'X'], ['email' => 'default email']);
    $this->assertSame('default email', $dict->resolve('email', 'email', 'fallback'));
  }

  // ── merge() immutability ────────────────────────────────────

  public function test_merge_returns_new_instance(): void
  {
    $dict  = new MessageDict(['name' => 'A']);
    $dict2 = $dict->merge(['email' => 'B']);

    $this->assertNotSame($dict, $dict2);
  }

  public function test_merge_original_is_unchanged(): void
  {
    $dict  = new MessageDict(['name' => 'A']);
    $dict->merge(['email' => 'B']);

    $this->assertSame('fallback', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_merge_new_instance_has_merged_definitions(): void
  {
    $dict  = new MessageDict(['name' => 'A']);
    $dict2 = $dict->merge(['email' => 'B']);

    $this->assertSame('A', $dict2->resolve('name', 'any', 'fallback'));
    $this->assertSame('B', $dict2->resolve('email', 'any', 'fallback'));
  }

  public function test_merge_preserves_existing_rule_keys_within_field(): void
  {
    $dict  = new MessageDict(['name' => ['length' => 'A', 'email' => 'B']]);
    $dict2 = $dict->merge(['name' => ['length' => 'new']]);

    // 'email' key must survive — shallow array_merge would drop it
    $this->assertSame('new', $dict2->resolve('name', 'length', 'fallback'));
    $this->assertSame('B',   $dict2->resolve('name', 'email',  'fallback'));
  }

  public function test_merge_string_to_array_replaces(): void
  {
    // field-wide string → rule-specific array: array wins
    $dict  = new MessageDict(['email' => 'flat']);
    $dict2 = $dict->merge(['email' => ['email' => 'specific']]);

    $this->assertSame('specific', $dict2->resolve('email', 'email', 'fallback'));
  }

  public function test_merge_array_to_string_replaces(): void
  {
    // rule-specific array → flat string: string wins
    $dict  = new MessageDict(['name' => ['length' => 'old']]);
    $dict2 = $dict->merge(['name' => 'flat']);

    $this->assertSame('flat', $dict2->resolve('name', 'length', 'fallback'));
  }

  // ── factory presets ─────────────────────────────────────────

  public function test_ja_applies_email_default(): void
  {
    $dict = MessageDict::ja();
    $this->assertSame('有効なメールアドレスを入力してください', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_ja_custom_definitions_override_defaults(): void
  {
    $dict = MessageDict::ja(['email' => 'カスタムメール']);
    $this->assertSame('カスタムメール', $dict->resolve('email', 'email', 'fallback'));
  }

  public function test_en_uses_fallback(): void
  {
    $dict = MessageDict::en();
    $this->assertSame('fallback', $dict->resolve('email', 'email', 'fallback'));
  }

  // ── Validator integration ───────────────────────────────────

  public function test_validator_uses_dict_for_error_messages(): void
  {
    $dict = MessageDict::ja();
    $validator = new Validator(
      ['email' => v::email()],
      [],
      [],
      $dict
    );
    $result = $validator->validate(['email' => 'invalid'])->getResult();

    $this->assertFalse($result['email']['is_valid']);
    $this->assertSame('有効なメールアドレスを入力してください', $result['email']['errors']);
  }

  public function test_validator_without_dict_uses_respect_default(): void
  {
    $validator = new Validator(['email' => v::email()]);
    $result = $validator->validate(['email' => 'invalid'])->getResult();

    $this->assertFalse($result['email']['is_valid']);
    $this->assertNotSame('有効なメールアドレスを入力してください', $result['email']['errors']);
    $this->assertNotEmpty($result['email']['errors']);
  }

  public function test_validator_dict_field_specific_override(): void
  {
    $dict = MessageDict::ja(['email' => 'メールを正しく入力してください']);
    $validator = new Validator(['email' => v::email()], [], [], $dict);
    $result = $validator->validate(['email' => 'bad'])->getResult();

    $this->assertSame('メールを正しく入力してください', $result['email']['errors']);
  }

  public function test_validator_dict_conditional_required(): void
  {
    $sb = SV::object([
      'type' => SV::enum(['personal', 'company']),
      'company_name' => SV::string()->optional(),
    ])->when('type', 'company', ['company_name'])
      ->withMessages(MessageDict::ja());

    $result = $sb->toValidator()->validate(['type' => 'company'])->getResult();

    $this->assertFalse($result['company_name']['is_valid']);
    $this->assertSame('必須項目です', $result['company_name']['errors']);
  }

  // ── errors format consistency ──────────────────────────────

  public function test_errors_format_has_no_bullet_prefix_without_dict(): void
  {
    // Without dict, errors must NOT start with "- " (Respect's getFullMessage format)
    $validator = new Validator(['email' => v::email()]);
    $result = $validator->validate(['email' => 'bad'])->getResult();

    $this->assertStringNotContainsString('- ', substr($result['email']['errors'], 0, 2));
  }

  public function test_errors_format_consistent_between_dict_and_no_dict(): void
  {
    // Both paths must produce the same message for the same failure when dict has no match
    $withoutDict = new Validator(['email' => v::email()]);
    $withDict    = new Validator(['email' => v::email()], [], [], new MessageDict());

    $r1 = $withoutDict->validate(['email' => 'bad'])->getResult();
    $r2 = $withDict->validate(['email' => 'bad'])->getResult();

    $this->assertSame($r1['email']['errors'], $r2['email']['errors']);
  }

  // ── resolve() variable substitution (Step 5) ────────────────

  public function test_resolve_substitutes_simple_var(): void {
    $dict = new MessageDict(['name' => ['minLength' => '最低{min}文字必要です']]);
    $this->assertSame('最低3文字必要です', $dict->resolve('name', 'minLength', 'fallback', ['min' => 3]));
  }

  public function test_resolve_substitutes_icu_type_annotation(): void {
    $dict = new MessageDict(['name' => ['minLength' => '{min, number}文字以上']]);
    $this->assertSame('3文字以上', $dict->resolve('name', 'minLength', 'fallback', ['min' => 3]));
  }

  public function test_resolve_unknown_var_stays_as_placeholder(): void {
    $dict = new MessageDict(['name' => ['minLength' => '{foo}文字以上']]);
    $this->assertSame('{foo}文字以上', $dict->resolve('name', 'minLength', 'fallback', ['min' => 3]));
  }

  public function test_resolve_no_vars_returns_template_unchanged(): void {
    $dict = new MessageDict(['name' => ['minLength' => '最低{min}文字']]);
    $this->assertSame('最低{min}文字', $dict->resolve('name', 'minLength', 'fallback'));
  }

  public function test_resolve_substitutes_vars_in_defaults(): void {
    $dict = new MessageDict([], ['minLength' => '最低{min}文字が必要']);
    $this->assertSame('最低5文字が必要', $dict->resolve('name', 'minLength', 'fallback', ['min' => 5]));
  }

  public function test_resolve_substitutes_vars_in_field_wide_message(): void {
    $dict = new MessageDict(['name' => 'エラー: {min}以上']);
    $this->assertSame('エラー: 10以上', $dict->resolve('name', 'any', 'fallback', ['min' => 10]));
  }

  public function test_resolve_multiple_vars(): void {
    $dict = new MessageDict([], ['length' => '{min}〜{max}文字で入力してください']);
    $this->assertSame('3〜20文字で入力してください', $dict->resolve('name', 'length', 'fallback', ['min' => 3, 'max' => 20]));
  }

  // ── BE inline errorMessage resolution (Step 1-a / Step 5 parity) ──

  public function test_be_honors_inline_errorMessage_format_override(): void {
    $sb = SV::object([
      'email' => SV::string()->email()->errorMessages(['format' => '有効なメールアドレスを入力してください']),
    ]);
    $result = $sb->toValidator()->validate(['email' => 'not-an-email'])->getResult();

    $this->assertFalse($result['email']['is_valid']);
    $this->assertSame('有効なメールアドレスを入力してください', $result['email']['errors']);
  }

  public function test_be_interpolates_inline_minLength_template(): void {
    $sb = SV::object([
      'name' => SV::string()->min(3)->errorMessages(['minLength' => '最低{min}文字必要です']),
    ]);
    $result = $sb->toValidator()->validate(['name' => 'ab'])->getResult();

    $this->assertFalse($result['name']['is_valid']);
    $this->assertSame('最低3文字必要です', $result['name']['errors']);
  }

  public function test_be_interpolates_inline_maximum_template(): void {
    $sb = SV::object([
      'age' => SV::integer()->max(100)->errorMessages(['maximum' => '{max}以下で入力してください']),
    ]);
    $result = $sb->toValidator()->validate(['age' => '200'])->getResult();

    $this->assertFalse($result['age']['is_valid']);
    $this->assertSame('100以下で入力してください', $result['age']['errors']);
  }

  public function test_dict_takes_priority_over_inline_errorMessage(): void {
    // Resolution order: MessageDict (by ruleId) > inline errorMessage (by keyword) > default.
    $sb = SV::object([
      'email' => SV::string()->email()->errorMessages(['format' => 'インライン']),
    ])->withMessages(MessageDict::ja(['email' => ['email' => '辞書が勝つ']]));

    $result = $sb->toValidator()->validate(['email' => 'bad'])->getResult();

    $this->assertSame('辞書が勝つ', $result['email']['errors']);
  }

  public function test_be_inline_errorMessage_via_fromJsonSchema(): void {
    $validator = Validator::fromJsonSchema([
      'type'       => 'object',
      'properties' => [
        'name' => ['type' => 'string', 'minLength' => 3, 'errorMessage' => ['minLength' => '最低{min}文字']],
      ],
      'required'   => ['name'],
    ]);
    $result = $validator->validate(['name' => 'ab'])->getResult();

    $this->assertFalse($result['name']['is_valid']);
    $this->assertSame('最低3文字', $result['name']['errors']);
  }

  public function test_be_without_inline_errorMessage_uses_respect_default(): void {
    $sb = SV::object(['email' => SV::string()->email()]);
    $result = $sb->toValidator()->validate(['email' => 'bad'])->getResult();

    $this->assertFalse($result['email']['is_valid']);
    $this->assertStringContainsString('valid email', $result['email']['errors']);
  }

  // ── SchemaBuilder::withMessages() ──────────────────────────

  public function test_schema_builder_withMessages_passes_dict_to_validator(): void
  {
    $sb = SV::object(['email' => SV::string()->email()])
      ->withMessages(MessageDict::ja());

    $result = $sb->toValidator()->validate(['email' => 'bad'])->getResult();

    $this->assertFalse($result['email']['is_valid']);
    $this->assertSame('有効なメールアドレスを入力してください', $result['email']['errors']);
  }
}
