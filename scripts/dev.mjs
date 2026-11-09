import { spawn } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { apiReady, startLocalStack } from './docker-up.mjs'

try {
  try {
    process.loadEnvFile(new URL('../.env.docker', import.meta.url))
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
  if (!(await apiReady())) await startLocalStack()
  const child = spawn(
    process.execPath,
    [
      fileURLToPath(new URL('../node_modules/vite/bin/vite.js', import.meta.url)),
      ...process.argv.slice(2),
    ],
    {
      cwd: fileURLToPath(new URL('../', import.meta.url)),
      stdio: 'inherit',
      windowsHide: true,
    },
  )
  child.on('error', (error) => {
    console.error(error.message)
    process.exitCode = 1
  })
  child.on('exit', (code) => {
    process.exitCode = code ?? 1
  })
} catch (error) {
  console.error(error.message)
  process.exitCode = 1
}
