import { test } from 'node:test'
import assert from 'node:assert/strict'
import { randomBytes } from 'node:crypto'
import { Readable } from 'node:stream'
import { backupKey, decryptBackup, encryptBackup } from './backup-crypto.mjs'

test('encrypted backups authenticate multi-chunk Unicode SQL and reject tampering/wrong keys', async () => {
  const key = backupKey(randomBytes(32).toString('base64'))
  const sql = Buffer.from("INSERT INTO users(name) VALUES('Тест 🛒');\n".repeat(1000))
  const chunks = []
  for await (const chunk of Readable.from([sql.subarray(0, 17), sql.subarray(17)]).pipe(
    encryptBackup(key),
  ))
    chunks.push(chunk)
  const encrypted = Buffer.concat(chunks)
  assert.deepEqual(decryptBackup(encrypted, key), sql)
  assert.equal(encrypted.includes(Buffer.from('INSERT INTO')), false)
  assert.throws(() => decryptBackup(encrypted, randomBytes(32)))
  encrypted[20] ^= 1
  assert.throws(() => decryptBackup(encrypted, key))
  assert.throws(() => backupKey('weak'))
})
