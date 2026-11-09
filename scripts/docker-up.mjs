import { spawn, spawnSync } from 'node:child_process'
import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../', import.meta.url))
const compose = [
  'compose',
  '--env-file',
  '.env.docker',
  '-f',
  'compose.yaml',
  '-f',
  'compose.local.yaml',
]

function dockerReady() {
  return (
    spawnSync('docker', ['info', '--format', '{{.ServerVersion}}'], {
      cwd: root,
      encoding: 'utf8',
      windowsHide: true,
      timeout: 10000,
      maxBuffer: 65536,
    }).status === 0
  )
}

async function needsWindowsRestart() {
  try {
    const status = JSON.parse(
      await readFile(new URL('../logs/docker-setup/status.json', import.meta.url), 'utf8'),
    )
    if (!status.restartRequired) return false
    const boot = spawnSync(
      'powershell.exe',
      [
        '-NoProfile',
        '-NonInteractive',
        '-Command',
        '(Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToUniversalTime().ToString("o")',
      ],
      { encoding: 'utf8', windowsHide: true, timeout: 10000 },
    )
    const bootTime = Date.parse(boot.stdout?.trim())
    return Number.isFinite(bootTime) && bootTime < Date.parse(status.startedAt)
  } catch {
    return false
  }
}

export function localApiUrl() {
  const port = process.env.HTTP_PORT || '8080'
  if (!/^\d+$/.test(port) || Number(port) < 1 || Number(port) > 65535)
    throw new Error('Invalid HTTP_PORT in .env.docker')
  const bind = process.env.HTTP_BIND || '127.0.0.1'
  const host = ['0.0.0.0', '::'].includes(bind) ? '127.0.0.1' : bind
  return `http://${host.includes(':') ? `[${host}]` : host}:${port}`
}

export async function apiReady() {
  try {
    const response = await fetch(`${localApiUrl()}/api/health`, {
      signal: AbortSignal.timeout(3000),
    })
    return response.ok && (await response.json()).status === 'ok'
  } catch {
    return false
  }
}

export async function startLocalStack() {
  await import('./docker-setup.mjs')
  process.loadEnvFile(new URL('../.env.docker', import.meta.url))
  if (!dockerReady()) {
    if (process.platform !== 'win32')
      throw new Error('Docker Engine is unavailable. Start Docker Engine and retry.')
    if (await needsWindowsRestart()) {
      throw new Error(
        'Windows components for Docker were enabled. Restart Windows first, then run npm run docker:up again.',
      )
    }
    console.log('Starting Docker Desktop...')
    const start = spawnSync('docker', ['desktop', 'start', '--detach'], {
      cwd: root,
      encoding: 'utf8',
      windowsHide: true,
      timeout: 20000,
      maxBuffer: 65536,
    })
    if (start.status !== 0)
      throw new Error(
        'Docker Desktop could not start. Run scripts/windows-docker-setup.ps1 and check logs/docker-setup/status.json.',
      )
    const deadline = Date.now() + 120000
    while (!dockerReady()) {
      if (Date.now() > deadline)
        throw new Error(
          'Docker Linux Engine did not become ready. Check WSL and restart Windows after installing its components.',
        )
      console.log('Waiting for Docker Linux Engine...')
      await new Promise((resolve) => setTimeout(resolve, 2000))
    }
  }
  console.log('Building and starting the local application, database and mail service...')
  await new Promise((resolve, reject) => {
    const child = spawn(
      'docker',
      [...compose, 'up', '-d', '--build', '--wait', '--wait-timeout', '180'],
      {
        cwd: root,
        stdio: 'inherit',
        windowsHide: true,
      },
    )
    child.on('error', reject)
    child.on('exit', (code) =>
      code === 0
        ? resolve()
        : reject(new Error(`Docker Compose exited ${code}. Run npm run docker:logs for details.`)),
    )
  })
  if (!(await apiReady()))
    throw new Error('Containers started, but /api/health is unavailable. Run npm run docker:logs.')
  console.log(`API ready: ${localApiUrl()}/api/health`)
  console.log(`Storefront: ${process.env.APP_URL || localApiUrl()}`)
  console.log('Local test mail: http://localhost:8025')
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    await startLocalStack()
  } catch (error) {
    console.error(error.message)
    process.exitCode = 1
  }
}
