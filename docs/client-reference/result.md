# Result Primitives

Railway Oriented Programming (ROP) helpers. Every `Result<A, E>` is either `Ok<A>` or `Err<E>` — no exceptions, no `null`.

```ts
import { ok, err, isOk, isErr, map, flatMap, mapErr, getOrElse } from '@uuki/schemable-validator-client'
```

---

## `ok(value)` / `err(error)`

Construct the two variants.

```ts
import { ok, err } from '@uuki/schemable-validator-client'

const success = ok('validated value')   // Ok<string>
const failure = err(['is required'])    // Err<string[]>
```

---

## `isOk(result)` / `isErr(result)`

Type-narrowing guards.

```ts
import { isOk, isErr } from '@uuki/schemable-validator-client'

if (isOk(result)) {
  console.log(result.value)   // narrowed to Ok<A>
}
if (isErr(result)) {
  console.log(result.error)   // narrowed to Err<E>
}
```

---

## `map(result, fn)`

Transform the `Ok` value; pass `Err` through unchanged.

```ts
import { ok, err, map } from '@uuki/schemable-validator-client'

const r = ok('  hello  ')
const trimmed = map(r, (s) => s.trim())   // Ok<'hello'>
const failed  = map(err('oops'), (s) => s.trim())  // Err<'oops'>
```

---

## `flatMap(result, fn)`

Chain an `Ok` value into another `Result`-returning function. Short-circuits on `Err`.

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

Transform the `Err` value; pass `Ok` through unchanged. Useful for translating error messages.

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

Unwrap the `Ok` value, or return a fallback for `Err`.

```ts
import { ok, err, getOrElse } from '@uuki/schemable-validator-client'

getOrElse(ok('Alice'), 'unknown')    // 'Alice'
getOrElse(err('missing'), 'unknown') // 'unknown'
```
