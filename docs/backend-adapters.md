# Backend Adapters & Governance

The PHP core validates through a swappable **backend adapter** boundary. All
Respect/Validation knowledge is isolated behind it, so the validation engine can
be swapped — or omitted entirely — without changing the public API or the
`{value, is_valid, errors}` result shape.

---

## The contract

```php
// packages/core/Validation/BackendAdapter.php
interface BackendAdapter {
    public function compile(array $jsonSchema): ExecutableValidator;
}

// packages/core/Validation/ExecutableValidator.php
interface ExecutableValidator {
    // returns array<string, array{value: mixed, is_valid: bool, errors: ?string}>
    public function validate(array $data): array;
}
```

`compile()` turns a JSON Schema 2020-12 object (`properties` / `required`, plus
the owned `x-*` extensions) into an `ExecutableValidator`. The executable
validates **per field** and returns the common result shape. `x-transform` and
`x-when` are applied by the caller (`Validator` / the conformance runners), not
by the executable.

Messages are engine-neutral: each adapter maps its engine's failure to a neutral
rule id and resolves text via the shared catalog (see
[MessageDict](./message-dict.md)), so every backend emits identical strings.

---

## Built-in adapters

| Adapter | Dependency | Coercion Contract v1 | Use case |
|:--|:--|:--|:--|
| `NativeAdapter` (default) | none | yes | Dependency-free, FE-faithful; the default engine |
| `RespectAdapter` | `respect/validation` (optional) | yes | Full Respect rule surface / escape hatches |
| `OpisAdapter` | `opis/json-schema` (optional) | **no** (strict JSON Schema) | Typed-JSON input; structural validation |

- **NativeAdapter** is the default engine, wired into `SchemaBuilder::toValidator()`
  and `Validator::fromJsonSchema()`. It ports the FE `constraint.ts`/`validator.ts`
  semantics to PHP with zero third-party dependencies and honors Coercion Contract
  v1, so it accepts form strings (`"42"` for `integer`) exactly like the FE.
  Verified against every `conformance/*.json` fixture
  (`tests/Conformance/NativeConformanceTest.php`).
- **RespectAdapter** is opt-in (pass it to `toValidator()`/`fromJsonSchema()`).
  It is also used implicitly by the Respect escape hatches (SV::respect /
  RespectRules) and by raw `v` schemas. Requires the optional `respect/validation`.
- **OpisAdapter** applies strict JSON Schema semantics (no coercion), so a form
  string `"42"` fails `type: integer`. Intended for already-typed JSON.

### Coercion divergence (important)

`NativeAdapter`/`RespectAdapter` accept form strings per Coercion Contract v1;
`OpisAdapter` does not. Pick `OpisAdapter` only when your input is already typed
JSON, not `$_POST`-style strings.

---

## Optional dependencies

The default path (NativeAdapter + the dependency-free NativeFileValidator +
SV::custom) needs no external validator. Both engine packages are composer
`suggest`s, loaded only when you opt in:

- `respect/validation` — enables `RespectAdapter`, the Respect escape hatches
  (`RespectRules` / `SV::respect` / `postalCode` / `creditCard` / `iban`), and raw
  `v` schemas. Respect's factory is bootstrapped lazily by those paths only, so the
  Native default never loads it.

  ```
  composer require respect/validation
  ```

- `opis/json-schema` — enables `OpisAdapter`. Constructing it without the package
  throws a clear runtime error.

  ```
  composer require opis/json-schema
  ```

---

## Writing a custom adapter

```php
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;

final class MyAdapter implements BackendAdapter {
    public function compile(array $jsonSchema): ExecutableValidator {
        // return something whose validate(array $data) yields
        // [field => ['value' => ..., 'is_valid' => bool, 'errors' => ?string]]
        return new MyExecutableValidator(/* ... */);
    }
}

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new MyAdapter());
```

To keep messages consistent across backends, map your engine's failures to the
neutral rule vocabulary and resolve text via `DefaultMessages` /
`MessageDict::interpolate()` rather than emitting engine-specific strings.

---

## Frontend adapters

The client package ships native validation plus opt-in Zod / Valibot adapters as
subpath exports (`@uuki/schemable-validator-client/zod`, `/valibot`). Third-party
adapters (Svelte, React Hook Form, …) consume the same JSON Schema + `x-*`
contract; see [client adapter docs](./client-adapter.md).

---

## Governance: `x-*` extensions vs `$vocabulary`

The owned extensions (`x-when`, `x-custom-fields`, `x-transform`, inline
`errorMessage`) are deliberately kept as `x-*` rather than promoted to a formal
JSON Schema `$vocabulary`. Rationale:

- `x-*` keys are ignored by generic JSON Schema validators without error, so our
  schemas stay portable.
- Promotion to `$vocabulary` is reserved for when a real external consumer
  validates our schema with a generic validator and **silently under-validates**
  by ignoring `x-when` / `x-custom-fields` — i.e. an adoption-driven trigger.
- When promoted, the `x-*` spellings will be kept as aliases for one major cycle.

Until then, both the PHP backends and the FE evaluator own the semantics of these
extensions directly, which is what makes the engine-neutral message guarantee and
the cross-stack conformance suite possible.
