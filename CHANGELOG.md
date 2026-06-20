## [0.20.1](https://github.com/uuki/schemable-validator/compare/v0.20.0...v0.20.1) (2026-06-20)


### Bug Fixes

* **docs:** align README badges with Packagist package name ([42e4c79](https://github.com/uuki/schemable-validator/commit/42e4c7923e977a43e3befee787b78c6112b5c38c))

# [0.20.0](https://github.com/uuki/schemable-validator/compare/v0.19.0...v0.20.0) (2026-06-20)


* feat(core)!: NativeAdapter is the default engine; respect/validation is optional (scope 4) ([c482355](https://github.com/uuki/schemable-validator/commit/c4823558c7ef8e894cbcff614e53ed568d99dc8a))


### Bug Fixes

* add packages/client to pnpm workspace and pin vite for vitest peer compat ([802d32c](https://github.com/uuki/schemable-validator/commit/802d32c8bc98cebf0690883279f2956e4eaf4f1e))
* align FE/BE date format-assertions on shared calendar-validity + codepoint length ([16d0ca4](https://github.com/uuki/schemable-validator/commit/16d0ca4b3ae3f7c4c2b5293142e3cb1d94e4811d))
* align time/date-time format validation on RFC3339-full with leap seconds ([55e0770](https://github.com/uuki/schemable-validator/commit/55e0770597ba5122f4b371cc4487ccad11c3381f))
* align TS constraint numeric parsing with Coercion Contract v1 ([0c9f130](https://github.com/uuki/schemable-validator/commit/0c9f130182a8bad7d11ca84d1bac64b8de248428))
* **docs:** swap heading labels between overview and schema-builder; fix VitePress build ([70fb43d](https://github.com/uuki/schemable-validator/commit/70fb43d293ec7890212865a2aae9dc38d25d556a))
* handle type:array in jsonSchemaPropertyToDescriptors for fromJsonSchema() ([e444f79](https://github.com/uuki/schemable-validator/commit/e444f797b42266f970a268696294c406695173b9))
* handle uri/ipv4/ipv6/hostname formats in jsonSchemaPropertyToDescriptors ([9a7bf6e](https://github.com/uuki/schemable-validator/commit/9a7bf6efc827034321b0420040d3bb26a2f38c32))
* **i18n:** align BE multi-rule failure order with FE contract ([3700434](https://github.com/uuki/schemable-validator/commit/3700434f11c930a5345bb690cedad40ee4b47bb3))
* reject non-AbstractFieldSchema values in SchemaBuilder constructor ([5ecf10f](https://github.com/uuki/schemable-validator/commit/5ecf10f0587422545056cb8b192e069088602c28))
* **security:** harden CAPTCHA, CSRF, and SSRF defences ([3e48c54](https://github.com/uuki/schemable-validator/commit/3e48c545df57aeb2537533bc9711341b05b98284))
* update WP examples to SV API; add mirror-core for Playground ([3e3ddb4](https://github.com/uuki/schemable-validator/commit/3e3ddb45d5bbefd32cf6d76f3c2c728ee2de0214))
* **wp:** generate .mo file for Japanese translation ([7835def](https://github.com/uuki/schemable-validator/commit/7835defd5a683ce01603f7743e3a575938141fba))
* **wp:** promote Schemable Validator to top-level admin menu ([af2846c](https://github.com/uuki/schemable-validator/commit/af2846c30505006579816d27bea2336ab8686747))


### Documentation

* **i18n:** rekey MessageDict vocabulary docs to neutral rule ids (W4) ([38d8342](https://github.com/uuki/schemable-validator/commit/38d8342cc5fe07e649ffc833abb6d7ffab067a2b))


### Features

* add Coercion Contract v1 (Integer/Number/BooleanCoercion) and OpisAdapter ([95b5171](https://github.com/uuki/schemable-validator/commit/95b517176b4c534ebab6ec80dfadfc11de7dcb63))
* add optional BackendAdapter param to Validator::fromJsonSchema() ([33266fd](https://github.com/uuki/schemable-validator/commit/33266fd564fe53ca2a95e19e41f1a6fb91729dc3))
* add Validator::fromJsonSchema() for raw JSON Schema input ([c9b7dec](https://github.com/uuki/schemable-validator/commit/c9b7dec341014caa3c85f048c1bfffbe9ea771ba))
* **core:** add ImageDriver and CaptchaDriver; restructure docs ([d67266c](https://github.com/uuki/schemable-validator/commit/d67266c92132bc2d579c7eabfd6548810eaf819e))
* **core:** add mergeJsonSchema() to SchemaBuilder; WP example and docs ([71af211](https://github.com/uuki/schemable-validator/commit/71af211d7e20ba2d01b9ea936ab91209d59667bc))
* **core:** CustomField escape-hatch port + SV::custom() (scope 2) ([cd18caa](https://github.com/uuki/schemable-validator/commit/cd18caa2dc96dbf4d0f8a9bb3cbd04c7c39ec845))
* **core:** dependency-free FE-faithful NativeAdapter (W2) ([4e76c7e](https://github.com/uuki/schemable-validator/commit/4e76c7e3e535c8a693fb9dd4ad9d8717066a9206))
* **core:** dependency-free file validation via FileValidationDriver (scope 1) ([9b764a1](https://github.com/uuki/schemable-validator/commit/9b764a156f65ed34b0b8ce8d2efd569d6025e550))
* **core:** Respect driver namespace (RespectRules); deprecate SV escape-hatch shims (scope 3) ([d88203c](https://github.com/uuki/schemable-validator/commit/d88203ca2fc4c395ab7fb82450b26d5a354cc2a2))
* **i18n:** conformance verifies cross-stack error-message parity (option A) ([e010a0d](https://github.com/uuki/schemable-validator/commit/e010a0dd177e310a1145d2741511fd568fb2015f))
* **i18n:** engine-neutral canonical default messages (2-msg) ([7eb40ea](https://github.com/uuki/schemable-validator/commit/7eb40ea2e87081393531f19d66db36d9bf5868f7))
* **i18n:** neutralize Opis adapter messages (engine-independent message layer) ([b45b8c1](https://github.com/uuki/schemable-validator/commit/b45b8c1879d81effa0763822802db6d8b43e6543))
* **i18n:** Step 5 — errorMessage template substitution ({min}/{max} ICU subset) ([d7cae1b](https://github.com/uuki/schemable-validator/commit/d7cae1bdf493027f36b7821a8ec11189f6badc51))
* **schema:** add schemable meta-schema for IDE completion of x-* extensions ([fd3d0cf](https://github.com/uuki/schemable-validator/commit/fd3d0cf696fafb0d40ba8b9179efa2fa901dfc93))
* **step2:** replace x-when with JSONLogic format (native evaluators, no external deps) ([91799ac](https://github.com/uuki/schemable-validator/commit/91799ac40e5f481383954e78daa2c7750bfb1c53))
* **step3:** add x-custom-fields — declare BE-only validation fields in schema ([a8dc60b](https://github.com/uuki/schemable-validator/commit/a8dc60ba2e0ee081bbafad10e3c04ac172869e3d))
* **step4:** add x-transform (trim/toLowerCase/toUpperCase) with BE/FE parity ([82a4f47](https://github.com/uuki/schemable-validator/commit/82a4f47038d74771160b2cd9c877856d0a1bb2bc))
* **W3-step1:** add UISchema output and errorMessage inline vocabulary ([b635ca9](https://github.com/uuki/schemable-validator/commit/b635ca99d2af943496c8346cbf250a33ac1076cb))
* **wp:** add Schema Editor admin UI with StoredSchemaProvider ([6a14ba3](https://github.com/uuki/schemable-validator/commit/6a14ba3520b42b8b2fcb4b7a4d0bee034654cb8e))
* **wp:** improve Schema Editor UI; add merge-schema docs ([7246ded](https://github.com/uuki/schemable-validator/commit/7246dedd3050071fe41b479d85c77242f4b5b775))


### BREAKING CHANGES

* the default backend engine is now NativeAdapter, not Respect,
and respect/validation is an optional (suggest) dependency. Code relying on
Respect-specific behavior, the Respect escape hatches (SV::respect / postalCode /
creditCard / iban / RespectRules), raw `v` field schemas, or the RespectAdapter
must install respect/validation (composer require respect/validation). Default
scalar validation results are unchanged (conformance-verified equivalent).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
* **i18n:** MessageDict definition/preset keys are now neutral rule ids,
not Respect rule ids. Rename custom keys: stringType→string, intType→integer,
numeric→number, length→minLength/maxLength, url→uri, regex→pattern, in/anyOf→
enum, notEmpty/notOptional→required. `email` is unchanged. See docs/message-dict.md.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>

## [0.12.3](https://github.com/uuki/schemable-validator/compare/v0.12.2...v0.12.3) (2026-06-19)


### Bug Fixes

* **client:** resolve TypeScript 6.0 breaking changes ([acd2fdd](https://github.com/uuki/schemable-validator/commit/acd2fdd55f0b6395fee6a64972aa519bae888d47))

## [0.12.2](https://github.com/uuki/schemable-validator/compare/v0.12.1...v0.12.2) (2026-06-14)


### Bug Fixes

* **deps:** upgrade esbuild to 0.28.1 to resolve GHSA-gv7w-rqvm-qjhr ([844e420](https://github.com/uuki/schemable-validator/commit/844e420f8d147bf2f497381240822cdf8275cf8b))

## [0.12.1](https://github.com/uuki/schemable-validator/compare/v0.12.0...v0.12.1) (2026-06-14)


### Bug Fixes

* **client:** migrate deprecated Zod v4 format APIs and extract shared adapter base ([767c30a](https://github.com/uuki/schemable-validator/commit/767c30af802fe0ba4ccb0223db57388c7d142fa4))

# [0.12.0](https://github.com/uuki/schemable-validator/compare/v0.11.0...v0.12.0) (2026-06-14)


### Features

* **client:** add Zod/Valibot adapter sub-paths with onUnknown support ([9c647cb](https://github.com/uuki/schemable-validator/commit/9c647cb4209810d18238d73c8a7752df3821d5d6))

# [0.11.0](https://github.com/uuki/schemable-validator/compare/v0.10.1...v0.11.0) (2026-06-14)


### Features

* rename client package to @uuki/schemable-validator-client ([e065bd4](https://github.com/uuki/schemable-validator/commit/e065bd4c2d7cddf962e58792f21e2251e9c9a7ce))

## [0.10.1](https://github.com/uuki/schemable-validator/compare/v0.10.0...v0.10.1) (2026-06-14)


### Bug Fixes

* correct dead link in reference/extended.md ([e7f430f](https://github.com/uuki/schemable-validator/commit/e7f430f5cb439b447d18763fe05bde78bdc34d29))

# [0.10.0](https://github.com/uuki/schemable-validator/compare/v0.9.1...v0.10.0) (2026-06-14)


### Features

* add MessageDict for i18n error messages ([0d4ca1c](https://github.com/uuki/schemable-validator/commit/0d4ca1c4fb65212f1e28bbdf05e62c468cef302e))
