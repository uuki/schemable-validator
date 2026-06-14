# Constraint パイプライン

`validateObject` が内部で使う低レベルの構成要素です。カスタムフィールド検証ロジックが必要なときに個別にエクスポートして使います。

```ts
import {
  constraintsFromSchema, composeConstraints,
  checkType, checkMinLength, checkMaxLength,
  checkMinimum, checkMaximum, checkFormat,
  checkPattern, checkEnum,
  PATTERN_MAX_INPUT_LENGTH,
} from '@uuki/schemable-validator-client'
```

---

## `constraintsFromSchema(schema)`

`PropertySchema` から単一の合成済み `Constraint` を生成します。`validateObject` が内部で呼び出している関数と同一です。

```ts
import { constraintsFromSchema } from '@uuki/schemable-validator-client'
import type { PropertySchema } from '@uuki/schemable-validator-client'

const schema: PropertySchema = { type: 'string', format: 'email', minLength: 5 }
const validate = constraintsFromSchema(schema)

const result = validate({ value: 'bad', errors: [] })
// { value: 'bad', errors: ['must be a valid email'] }
```

---

## `composeConstraints(constraints)`

複数の `Constraint` を1つに合成します。すべての制約が順番に実行され、エラーは蓄積されます（最初のエラーで短絡しません）。

```ts
import { composeConstraints, checkMinLength, checkFormat } from '@uuki/schemable-validator-client'

const validate = composeConstraints([
  checkMinLength(3),
  checkFormat('email'),
])

validate({ value: 'x', errors: [] })
// { value: 'x', errors: [
//     'must be at least 3 characters long',
//     'must be a valid email'
//   ] }
```

---

## 個別の Constraint ファクトリー

各ファクトリーは `Constraint` — `(state: FieldState) => FieldState` を返します。

| 関数 | 説明 |
|---|---|
| `checkType(type)` | `integer` / `number` / `boolean` の型変換を検証。文字列は常に受け入れます。 |
| `checkMinLength(min)` | 文字列長 ≥ min。 |
| `checkMaxLength(max)` | 文字列長 ≤ max。 |
| `checkMinimum(min)` | 数値 ≥ min。 |
| `checkMaximum(max)` | 数値 ≤ max。 |
| `checkFormat(format)` | ビルトインのフォーマット正規表現で検証（下表参照）。 |
| `checkPattern(pattern, maxLen?)` | 任意の正規表現文字列でテスト。`maxLen`（デフォルト `500`）超の入力はスキップ（ReDoS 対策）。 |
| `checkEnum(values)` | 値がリストに含まれているか検証。 |

**`checkFormat` のビルトインフォーマット**

| フォーマット | パターン |
|---|---|
| `email` | `local@domain.tld` — 制御文字・ゼロ幅 Unicode を除外 |
| `uri` | `https?://…` — 制御文字を除外 |
| `date` | `YYYY-MM-DD` |
| `date-time` | `YYYY-MM-DDTHH:MM:SS[.ms](Z\|±HH:MM)` |
| `time` | `HH:MM:SS[.ms][Z\|±HH:MM]` |
| `uuid` | RFC 4122 |
| `ipv4` | ドット区切り10進数 |
| `ipv6` | フル / 省略形 |
| `hostname` | `label.label.tld` |

```ts
import { checkFormat, checkMinLength, composeConstraints } from '@uuki/schemable-validator-client'

const emailField = composeConstraints([checkMinLength(1), checkFormat('email')])

emailField({ value: '', errors: [] })
// { value: '', errors: ['must be at least 1 character long'] }

emailField({ value: 'not-an-email', errors: [] })
// { value: 'not-an-email', errors: ['must be a valid email'] }
```

---

## `PATTERN_MAX_INPUT_LENGTH`

```ts
export const PATTERN_MAX_INPUT_LENGTH = 500
```

`checkPattern` がクライアント側の正規表現評価をスキップするデフォルトの最大入力長。必要に応じて上書きできます。

```ts
import { checkPattern } from '@uuki/schemable-validator-client'

// 長さに関わらず常に評価する
const strictSlug = checkPattern('^[a-z0-9-]+$', Infinity)
```
