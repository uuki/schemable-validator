// Injecting a JSON Schema response into Zod via the built-in adapter.
//
// toZodSchema converts the server-defined ObjectSchema to a z.ZodObject,
// keeping client-side rules automatically in sync with the PHP definition.

import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

async function fetchSchema(url: string): Promise<ObjectSchema> {
  const res = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`schema fetch failed: ${res.status}`)
  return res.json() as Promise<ObjectSchema>
}

async function handleSubmit(form: HTMLFormElement): Promise<void> {
  const jsonSchema = await fetchSchema('/api/schema/contact')
  const schema = toZodSchema(jsonSchema)

  const data = Object.fromEntries(
    [...new FormData(form).entries()].flatMap(([k, v]) =>
      typeof v === 'string' ? [[k, v]] : [],
    ),
  )

  const result = schema.safeParse(data)

  if (result.success) {
    form.submit()
    return
  }

  console.error(result.error.flatten().fieldErrors)
}

document.querySelector('form')?.addEventListener('submit', (e) => {
  e.preventDefault()
  handleSubmit(e.currentTarget as HTMLFormElement)
})
