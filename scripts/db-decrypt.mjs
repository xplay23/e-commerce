import { readFile, writeFile } from 'node:fs/promises'
import { backupKey, decryptBackup } from './backup-crypto.mjs'

const [source, target] = process.argv.slice(2)
if (!source || !target)
  throw new Error('Usage: npm run db:decrypt -- backups/file.sql.enc backups/restored.sql')
process.loadEnvFile(new URL('../.env.docker', import.meta.url))
const plaintext = decryptBackup(
  await readFile(source),
  backupKey(process.env.BACKUP_ENCRYPTION_KEY),
)
// Authenticate the complete file before writing any plaintext; never overwrite an existing file.
await writeFile(target, plaintext, { flag: 'wx', mode: 0o600 })
console.log(`Authenticated SQL written to ${target}. Protect and remove the plaintext after use.`)
