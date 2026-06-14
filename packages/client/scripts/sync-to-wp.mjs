// Copy client dist to the WordPress plugin's assets directory so it can be
// served via plugins_url('dist/client/index.mjs', ...) in PHP examples.

import { copyFileSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'

const root = new URL('../../', import.meta.url).pathname
const src  = join(root, 'client/dist')
const dest = join(root, 'wp-schemable-validator/dist/client')

mkdirSync(dest, { recursive: true })

for (const file of ['index.mjs', 'index.mjs.map', 'index.cjs', 'index.cjs.map', 'index.d.mts', 'index.d.cts']) {
  copyFileSync(join(src, file), join(dest, file))
}

console.log(`synced dist/client → wp-schemable-validator/dist/client`)
