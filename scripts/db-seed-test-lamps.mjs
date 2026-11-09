import { spawn } from 'node:child_process'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../', import.meta.url))
const source = (relative) => fileURLToPath(new URL(relative, import.meta.url)).replaceAll('\\', '/')
const args = process.argv.slice(2)
if (args.some((arg) => arg !== '--dry-run')) {
  console.error('Usage: npm run db:seed:lamps -- [--dry-run]')
  process.exit(1)
}

// Mount just the fixture and importer so an existing local image works without rebuilding.
const child = spawn(
  'docker',
  [
    'compose',
    '--env-file',
    '.env.docker',
    '-f',
    'compose.yaml',
    '-f',
    'compose.local.yaml',
    'run',
    '--rm',
    '--no-deps',
    '-T',
    '--volume',
    `${source('../backend/bin/seed-test-lamps.php')}:/var/www/html/bin/seed-test-lamps.php:ro`,
    '--volume',
    `${source('../backend/database/test-lamps.php')}:/var/www/html/database/test-lamps.php:ro`,
    'backend',
    'php',
    'bin/seed-test-lamps.php',
    ...args,
  ],
  { cwd: root, stdio: 'inherit', windowsHide: true },
)
child.on('error', (error) => {
  console.error(error.message)
  process.exitCode = 1
})
child.on('exit', (code) => {
  process.exitCode = code ?? 1
})
