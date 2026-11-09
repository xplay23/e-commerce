import { spawn } from 'node:child_process'
import { mkdir, unlink } from 'node:fs/promises'
import { createWriteStream } from 'node:fs'
import { pipeline } from 'node:stream/promises'
import { backupKey, encryptBackup } from './backup-crypto.mjs'

process.loadEnvFile(new URL('../.env.docker', import.meta.url))
const key = backupKey(process.env.BACKUP_ENCRYPTION_KEY)

const directory = new URL('../backups/', import.meta.url)
await mkdir(directory, { recursive: true })
const filename = `nord-${new Date().toISOString().replaceAll(':', '-')}.sql.enc`
const target = new URL(filename, directory)
const child = spawn(
  'docker',
  [
    'compose',
    '--env-file',
    '.env.docker',
    'exec',
    '-T',
    'db',
    'sh',
    '-c',
    'exec env MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --single-transaction --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"',
  ],
  { stdio: ['ignore', 'pipe', 'inherit'] },
)
const completion = new Promise((resolve, reject) => {
  child.on('error', reject)
  child.on('close', (code) =>
    code === 0 ? resolve() : reject(new Error(`mysqldump exited ${code}`)),
  )
})
try {
  await Promise.all([
    pipeline(
      child.stdout,
      encryptBackup(key),
      createWriteStream(target, { flags: 'wx', mode: 0o600 }),
    ),
    completion,
  ])
  console.log(`Backup saved: backups/${filename}`)
} catch (error) {
  child.kill()
  await unlink(target).catch(() => {})
  throw error
}
