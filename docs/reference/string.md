# SV::string() - String Type

The base type for text input. Supports chaining constraints for length, format, and regular expressions.

```php
SV::string()
```

**JSON Schema output:**
```json
{ "type": "string" }
```

---

## .min(n) {#min}

Sets the **minimum character length**.

```php
SV::string()->min(int $n)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$n` | `int` | Minimum number of characters allowed (inclusive) |

**JSON Schema keyword:** `minLength`

**Use case:** Prevent empty strings on required inputs, enforce a minimum body length.

```php
// At least 1 character (effectively equivalent to required)
SV::string()->min(1)

// Body with at least 10 characters
SV::string()->min(10)
```

```json
{ "type": "string", "minLength": 10 }
```

---

## .max(n) {#max}

Sets the **maximum character length**.

```php
SV::string()->max(int $n)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$n` | `int` | Maximum number of characters allowed (inclusive) |

**JSON Schema keyword:** `maxLength`

**Use case:** Match a database column length, fit within a display area.

```php
// Name up to 100 characters
SV::string()->min(1)->max(100)

// Title up to 255 characters
SV::string()->max(255)
```

```json
{ "type": "string", "minLength": 1, "maxLength": 100 }
```

---

## .email() {#email}

Validates email address format.

```php
SV::string()->email()
```

**JSON Schema keyword:** `format: "email"`

**Use case:** Email address field on a contact form.

```php
SV::string()->email()
```

```json
{ "type": "string", "format": "email" }
```

> The client's `checkFormat` performs a pre-check using `^[^\s@]+@[^\s@]+\.[^\s@]+$`. Stricter validation is handled server-side (Respect `v::email()`).

---

## .url() {#url}

Validates URL format (must start with `https://` or `http://`).

```php
SV::string()->url()
```

**JSON Schema keyword:** `format: "uri"`

**Use case:** Website URL, social media profile URL input.

```php
SV::string()->url()->optional()
```

```json
{ "type": "string", "format": "uri" }
```

---

## .pattern(p) {#pattern}

Validates format using a **regular expression**.

```php
SV::string()->pattern(string $p)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$p` | `string` | Regular expression pattern (without delimiters) |

**JSON Schema keyword:** `pattern`

**Use case:** Inputs with a defined format such as phone numbers, postal codes, or usernames.

```php
// Japanese phone number
SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional()

// Alphanumeric and underscore (username)
SV::string()->min(3)->max(20)->pattern('^[a-zA-Z0-9_]+$')

// Japanese postal code
SV::string()->pattern('^\d{3}-\d{4}$')
```

```json
{ "type": "string", "pattern": "^[a-zA-Z0-9_]+$", "minLength": 3, "maxLength": 20 }
```

> Pass the pattern without delimiters (`/`). The `u` flag is applied in JSON Schema.

### ReDoS (Regular Expression DoS) Prevention Guidelines

The client-side validator evaluates patterns on every keystroke. Patterns with **catastrophic backtracking** can block the browser for an extended period (ReDoS).

**Examples of patterns to avoid:**

| Dangerous pattern | Problem |
|:--|:--|
| `(a+)+b` | Nested quantifiers (exponential backtracking) |
| `(x|x)+y` | Repeated overlapping alternatives |
| `(\w+\s)+\w+` | Chained variable-length groups |
| `.*foo.*bar.*` | Multiple chained `.*` |

**Safe alternatives:**

```
# Bad: nested repetition
(a+)+$

# Good: use a character class instead
[a]+$

# Bad: overlapping alternatives
(\w|\d)+

# Good: combine into one
[\w\d]+
```

**Client-side safety net:** The client implementation skips pattern evaluation and defers to the server when input exceeds `PATTERN_MAX_INPUT_LENGTH` (default: 500 characters). To lower this threshold, call `checkPattern(pattern, limit)` directly or refer to `PATTERN_MAX_INPUT_LENGTH`.

> Server-side validation is always authoritative. Client validation is only a UX aid.

---

## .date() {#date}

Validates a **date** in `YYYY-MM-DD` format.

```php
SV::string()->date()
```

**JSON Schema keyword:** `format: "date"`

**Use case:** Date of birth, reservation date, expiration date.

```json
{ "type": "string", "format": "date" }
```

---

## .dateTime() {#datetime}

Validates a **date-time** in RFC 3339 format (`YYYY-MM-DDTHH:mm:ssZ`).

```php
SV::string()->dateTime()
```

**JSON Schema keyword:** `format: "date-time"`

**Use case:** Event start time, timestamp input.

```json
{ "type": "string", "format": "date-time" }
```

---

## .time() {#time}

Validates a **time** in `HH:mm:ss` format.

```php
SV::string()->time()
```

**JSON Schema keyword:** `format: "time"`

**Use case:** Business hours, reservation time.

```json
{ "type": "string", "format": "time" }
```

---

## .uuid() {#uuid}

Validates **UUID** format (`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`).

```php
SV::string()->uuid()
```

**JSON Schema keyword:** `format: "uuid"`

**Use case:** ID fields, receiving foreign key values.

```json
{ "type": "string", "format": "uuid" }
```

---

## .ipv4() {#ipv4}

Validates an **IPv4 address**.

```php
SV::string()->ipv4()
```

**JSON Schema keyword:** `format: "ipv4"`

**Use case:** IP address input, access control configuration.

```json
{ "type": "string", "format": "ipv4" }
```

---

## .ipv6() {#ipv6}

Validates an **IPv6 address**.

```php
SV::string()->ipv6()
```

**JSON Schema keyword:** `format: "ipv6"`

```json
{ "type": "string", "format": "ipv6" }
```

---

## .slug() {#slug}

Validates a **URL slug** (lowercase alphanumeric characters and hyphens only).

```php
SV::string()->slug()
```

**JSON Schema keyword:** `pattern: "^[a-z0-9]+(?:-[a-z0-9]+)*$"`

**Use case:** Permalink, category slug input.

```json
{ "type": "string", "pattern": "^[a-z0-9]+(?:-[a-z0-9]+)*$" }
```

---

## .domain() {#domain}

Validates a **domain name** in `example.com` format.

```php
SV::string()->domain()
```

**JSON Schema keyword:** `format: "hostname"`

**Use case:** Allowed domain registration form, subdomain configuration.

```json
{ "type": "string", "format": "hostname" }
```

---

## Combination Examples

```php
$schema = SV::object([
  // Name: 1–100 characters
  'name'    => SV::string()->min(1)->max(100),

  // Email address
  'email'   => SV::string()->email(),

  // Phone number: optional input
  'tel'     => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),

  // Website: nullable
  'website' => SV::string()->url()->nullable()->optional(),

  // Body: 10–1000 characters
  'body'    => SV::string()->min(10)->max(1000),
]);
```
