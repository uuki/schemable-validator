# Result プリミティブ

Railway Oriented Programming (ROP) ヘルパー。`Result<A, E>` は `Ok<A>` または `Err<E>` のいずれかであり、例外も `null` も使いません。

```ts
import { ok, err, isOk, isErr, map, flatMap, mapErr, getOrElse } from '@uuki/schemable-validator-client'
```

---

## `ok(value)` / `err(error)`

2つのバリアントを生成します。

```ts
import { ok, err } from '@uuki/schemable-validator-client'

const success = ok('validated value')   // Ok<string>
const failure = err(['is required'])    // Err<string[]>
```

---

## `isOk(result)` / `isErr(result)`

型を絞り込むガード関数。

```ts
import { isOk, isErr } from '@uuki/schemable-validator-client'

if (isOk(result)) {
  console.log(result.value)   // Ok<A> に絞り込まれる
}
if (isErr(result)) {
  console.log(result.error)   // Err<E> に絞り込まれる
}
```

---

## `map(result, fn)`

`Ok` の値を変換し、`Err` はそのまま通過させます。

```ts
import { ok, err, map } from '@uuki/schemable-validator-client'

const r = ok('  hello  ')
const trimmed = map(r, (s) => s.trim())            // Ok<'hello'>
const failed  = map(err('oops'), (s) => s.trim())  // Err<'oops'>
```

---

## `flatMap(result, fn)`

`Ok` の値を別の `Result` を返す関数に渡します。`Err` の場合は短絡します。

```ts
import { ok, err, flatMap } from '@uuki/schemable-validator-client'

const parseAge = (s: string) =>
  Number.isInteger(+s) ? ok(+s) : err('not a number')

const checkAdult = (n: number) =>
  n >= 18 ? ok(n) : err('must be at least 18')

const result = flatMap(parseAge('21'), checkAdult)  // Ok<21>
const failed = flatMap(parseAge('abc'), checkAdult) // Err<'not a number'>
```

---

## `mapErr(result, fn)`

`Err` の値を変換し、`Ok` はそのまま通過させます。エラーメッセージの翻訳などに使います。

```ts
import { err, mapErr } from '@uuki/schemable-validator-client'

const translated = mapErr(
  err('is required'),
  (msg) => `このフィールドは${msg}`,
)
// Err<'このフィールドはis required'>
```

---

## `getOrElse(result, fallback)`

`Ok` の値を取り出し、`Err` の場合はフォールバック値を返します。

```ts
import { ok, err, getOrElse } from '@uuki/schemable-validator-client'

getOrElse(ok('Alice'), 'unknown')    // 'Alice'
getOrElse(err('missing'), 'unknown') // 'unknown'
```
