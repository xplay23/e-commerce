import { randomBytes } from 'node:crypto'
import { chmod, readFile, writeFile } from 'node:fs/promises'

const template = await readFile(new URL('../.env.docker.example', import.meta.url), 'utf8')
const target = new URL('../.env.docker', import.meta.url)
let contents
try {
  contents = await readFile(target, 'utf8')
} catch (error) {
  if (error.code !== 'ENOENT') throw error
  contents = template
}
for (const [key, encoding] of Object.entries({
  DB_PASSWORD: 'hex',
  DB_ROOT_PASSWORD: 'hex',
  APP_KEY: 'base64',
  BACKUP_ENCRYPTION_KEY: 'base64',
})) {
  const line = new RegExp(`^${key}=(.*)$`, 'm')
  const existing = contents.match(line)
  if (existing && existing[1].trim() !== '') continue
  const value = randomBytes(32).toString(encoding)
  contents = existing
    ? contents.replace(line, `${key}=${value}`)
    : `${contents.trimEnd()}\n${key}=${value}\n`
}
await writeFile(target, contents, { mode: 0o600 })
await chmod(target, 0o600)
console.log('.env.docker ready. Existing non-empty passwords and encryption keys were preserved.')
