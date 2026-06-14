// Injecting a JSON Schema response into Valibot (v1.x) via the built-in adapter.
//
// toValibotSchema converts the server-defined ObjectSchema to a v.ObjectSchema,
// keeping client-side rules automatically in sync with the PHP definition.

import * as v from 'valibot'
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

async function fetchSchema(url: string): Promise<ObjectSchema> {
  const res = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`schema fetch failed: ${res.status}`)
  return res.json() as Promise<ObjectSchema>
}

async function handleSubmit(form: HTMLFormElement): Promise<void> {
  const jsonSchema = await fetchSchema('/api/schema/contact')
  const schema = toValibotSchema(jsonSchema)

  const data = Object.fromEntries(
    [...new FormData(form).entries()].flatMap(([k, vs]) =>
      typeof vs === 'string' ? [[k, vs]] : [],
    ),
  )

  const result = v.safeParse(schema, data)

  if (result.success) {
    form.submit()
    return
  }

  console.error(v.flatten(result.issues).nested)
}

document.querySelector('form')?.addEventListener('submit', (e) => {
  e.preventDefault()
  handleSubmit(e.currentTarget as HTMLFormElement)
})
