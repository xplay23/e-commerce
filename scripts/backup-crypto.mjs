import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto'
import { Transform } from 'node:stream'

export function backupKey(value) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9+/]{43}=$/.test(value))
    throw new Error('Configure BACKUP_ENCRYPTION_KEY using npm run docker:setup')
  const key = Buffer.from(value, 'base64')
  if (key.length !== 32) throw new Error('BACKUP_ENCRYPTION_KEY must decode to 32 bytes')
  return key
}

export function encryptBackup(key) {
  const iv = randomBytes(12)
  const cipher = createCipheriv('aes-256-gcm', key, iv)
  let started = false
  return new Transform({
    transform(chunk, encoding, callback) {
      try {
        if (!started) {
          this.push(Buffer.concat([Buffer.from('NORD1'), iv]))
          started = true
        }
        this.push(cipher.update(chunk))
        callback()
      } catch (error) {
        callback(error)
      }
    },
    flush(callback) {
      try {
        if (!started) this.push(Buffer.concat([Buffer.from('NORD1'), iv]))
        this.push(cipher.final())
        this.push(cipher.getAuthTag())
        callback()
      } catch (error) {
        callback(error)
      }
    },
  })
}

export function decryptBackup(contents, key) {
  if (contents.length < 33 || contents.subarray(0, 5).toString() !== 'NORD1')
    throw new Error('Invalid NORD encrypted backup')
  const decipher = createDecipheriv('aes-256-gcm', key, contents.subarray(5, 17))
  decipher.setAuthTag(contents.subarray(-16))
  return Buffer.concat([decipher.update(contents.subarray(17, -16)), decipher.final()])
}
