// Combining the SDK with a fetch call.
//
// The SDK has no opinion on transport. Here we use the browser's fetch API,
// but any HTTP client (axios, ky, wretch, …) works the same way.

import { validateObject, isAllValid, extractErrors } from '../src/index.js'
import type { ObjectSchema } from '../src/index.js'

// Fetch the schema once, cache it yourself — the SDK does not own this concern.
async function fetchSchema(url: string): Promise<ObjectSchema> {
  const res = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`schema fetch failed: ${res.status}`)
  return res.json() as Promise<ObjectSchema>
}

// Example: validate on form submit
async function handleSubmit(form: HTMLFormElement): Promise<void> {
  // PHP helper schv_schema_url('/contact') returns this URL
  const schema = await fetchSchema('/wp-json/schv/v1/contact')

  // FormData → plain object (File entries are excluded; handled server-side)
  const data = Object.fromEntries(
    [...new FormData(form).entries()].flatMap(([k, v]) =>
      typeof v === 'string' ? [[k, v]] : [],
    ),
  ) as Record<string, string>

  const result = validateObject(data, schema)

  if (isAllValid(result)) {
    form.submit()
    return
  }

  for (const [field, errors] of Object.entries(extractErrors(result))) {
    console.error(`${field}: ${errors.join(', ')}`)
  }
}

// Wire up
document.querySelector('form')?.addEventListener('submit', (e) => {
  e.preventDefault()
  handleSubmit(e.currentTarget as HTMLFormElement)
})
