// Engine-neutral canonical default messages, keyed by a neutral rule vocabulary.
// Single source of truth for FE default error text. MUST stay byte-identical to
// packages/core/I18n/DefaultMessages.php — cross-stack drift is caught by the
// conformance/parity/error-message-default-*.json fixtures.
//
// Placeholders use the {var} / {var, type} ICU subset resolved by substituteVars().
// Vocabulary keys:
//   string|integer|number|boolean         (type)
//   minLength|maxLength|minimum|maximum    (size / range)
//   email|uri|date|date-time|time|uuid|ipv4|ipv6|hostname  (format, kept distinct)
//   pattern|enum|required

export const DEFAULT_MESSAGES = {
  string: 'must be a string',
  integer: 'must be an integer',
  number: 'must be a number',
  boolean: 'must be a boolean',
  minLength: 'must be at least {min} character{plural} long',
  maxLength: 'must be no more than {max} character{plural} long',
  minimum: 'must be at least {min}',
  maximum: 'must be no more than {max}',
  email: 'must be a valid email',
  uri: 'must be a valid uri',
  date: 'must be a valid date',
  'date-time': 'must be a valid date-time',
  time: 'must be a valid time',
  uuid: 'must be a valid uuid',
  ipv4: 'must be a valid ipv4',
  ipv6: 'must be a valid ipv6',
  hostname: 'must be a valid hostname',
  pattern: 'must match the required format',
  enum: 'must be one of: {values}',
  required: 'is required',
} as const
