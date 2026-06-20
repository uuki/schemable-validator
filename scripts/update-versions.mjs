import { readFileSync, writeFileSync } from 'fs'

const version = process.argv[2]
if (!version) {
  console.error('Usage: node scripts/update-versions.mjs <version>')
  process.exit(1)
}

// ── WP plugin header ──────────────────────────────────────────────────────────
const pluginPath = 'packages/wp-schemable-validator/index.php'
const pluginContent = readFileSync(pluginPath, 'utf8')
const updated = pluginContent.replace(
  /(\* Version:\s*)[\d.]+/,
  `$1${version}`,
)
writeFileSync(pluginPath, updated)
console.log(`Updated ${pluginPath} → ${version}`)

// ── Core composer.json ────────────────────────────────────────────────────────
const corePath = 'packages/core/composer.json'
const corePkg = JSON.parse(readFileSync(corePath, 'utf8'))
corePkg.version = version
writeFileSync(corePath, JSON.stringify(corePkg, null, 2) + '\n')
console.log(`Updated ${corePath} → ${version}`)

// ── Client package.json ───────────────────────────────────────────────────────
const clientPath = 'packages/client/package.json'
const pkg = JSON.parse(readFileSync(clientPath, 'utf8'))
pkg.version = version
writeFileSync(clientPath, JSON.stringify(pkg, null, 2) + '\n')
console.log(`Updated ${clientPath} → ${version}`)
