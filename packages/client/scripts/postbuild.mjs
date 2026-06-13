// Rename content-hashed .d.ts/.d.cts files to stable names that package.json can reference.
// rolldown emits shared declaration chunks as index-<hash>.d.ts — this script normalises them.

import { readdirSync, renameSync } from 'node:fs'
import { join } from 'node:path'

const dist = new URL('../dist/', import.meta.url).pathname

for (const file of readdirSync(dist)) {
  if (/^index-[^.]+\.d\.ts$/.test(file)) {
    renameSync(join(dist, file), join(dist, 'index.d.ts'))
    console.log(`renamed ${file} → index.d.ts`)
  }
  if (/^index-[^.]+\.d\.cts$/.test(file)) {
    renameSync(join(dist, file), join(dist, 'index.d.cts'))
    console.log(`renamed ${file} → index.d.cts`)
  }
}
