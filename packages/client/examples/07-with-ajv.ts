// Validating with AJV using the JSON Schema output directly.
//
// AJV consumes the ObjectSchema as-is — no adapter needed.
// Custom keywords (x-when, x-unmapped-fields) are passed through
// but ignored by AJV unless you register them explicitly.

import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

const ajv = new Ajv({ allErrors: true })
addFormats(ajv)

async function fetchSchema(url: string): Promise<ObjectSchema> {
  const res = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`schema fetch failed: ${res.status}`)
  return res.json() as Promise<ObjectSchema>
}

async function handleSubmit(form: HTMLFormElement): Promise<void> {
  const jsonSchema = await fetchSchema('/api/schema/contact')
  const validate = ajv.compile(jsonSchema)

  const data = Object.fromEntries(
    [...new FormData(form).entries()].flatMap(([k, v]) =>
      typeof v === 'string' ? [[k, v]] : [],
    ),
  )

  const valid = validate(data)

  if (valid) {
    form.submit()
    return
  }

  console.error(validate.errors)
}

document.querySelector('form')?.addEventListener('submit', (e) => {
  e.preventDefault()
  handleSubmit(e.currentTarget as HTMLFormElement)
})
