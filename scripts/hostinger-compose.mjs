import { randomBytes, createHash } from 'node:crypto'
import { readFile, writeFile, mkdir } from 'node:fs/promises'
import { execFileSync } from 'node:child_process'
import { resolve } from 'node:path'

// Upload the allowlisted source archive to the release directory over SSH, then
// submit the small Compose definition through the Hostinger connector.
const root = resolve(import.meta.dirname, '..')
const generated = resolve(root, '.hostinger/generated')
await mkdir(generated, { recursive: true })
const pack = (name, directory, paths) => {
  const archive = resolve(generated, name)
  execFileSync('tar', ['-czf', archive, '-C', directory, ...paths])
  return readFile(archive)
}
const archive = await pack('release.tar.gz', root, [
  'Dockerfile',
  '.dockerignore',
  'package.json',
  'package-lock.json',
  'index.html',
  'env.d.ts',
  'tsconfig.json',
  'tsconfig.app.json',
  'tsconfig.node.json',
  'vite.config.ts',
  'src',
  'public',
  'docker',
  'backend/Dockerfile',
  'backend/composer.json',
  'backend/composer.lock',
  'backend/src',
  'backend/public',
  'backend/database',
  'backend/bin',
])
const revision = createHash('sha256').update(archive).digest('hex').slice(0, 16)
const backendImage = `nord-backend:${revision}`
const webImage = `nord-web:${revision}`
const releasePath = `/opt/nord/releases/${revision}`
const logging = { driver: 'json-file', options: { 'max-size': '10m', 'max-file': '3' } }
const health = (test) => ({ test, interval: '5s', timeout: '5s', retries: 40, start_period: '20s' })
const baseEnv = {
  APP_ENV: 'production',
  APP_URL: 'https://${SITE_DOMAIN:?required}',
  FRONTEND_URL: 'https://${SITE_DOMAIN:?required}',
  APP_KEY: '${APP_KEY:?required}',
  DB_HOST: 'db',
  DB_PORT: '3306',
  DB_NAME: 'nord_store',
  DB_USER: 'nord',
  DB_PASSWORD: '${DB_PASSWORD:?required}',
  SMTP_HOST: '${SMTP_HOST:-}',
  SMTP_PORT: '${SMTP_PORT:-587}',
  SMTP_ENCRYPTION: '${SMTP_ENCRYPTION:-tls}',
  SMTP_USERNAME: '${SMTP_USERNAME:-}',
  SMTP_PASSWORD: '${SMTP_PASSWORD:-}',
  MAIL_FROM: '${MAIL_FROM:-noreply@nord.test}',
  TRUSTED_PROXY_HOST: '',
  OPENAI_API_KEY: '${OPENAI_API_KEY:-}',
  OPENAI_CHAT_MODEL: '${OPENAI_CHAT_MODEL:-gpt-6-luna}',
}
function compose(testing) {
  const environment = testing
    ? {
        ...baseEnv,
        APP_ENV: 'development',
        APP_URL: 'http://web',
        FRONTEND_URL: 'http://web',
        SMTP_HOST: 'mailpit',
        SMTP_PORT: '1025',
        SMTP_ENCRYPTION: 'none',
        ALLOW_SMOKE_TESTS: '1',
        SMOKE_BASE_URL: 'http://web',
        MAILPIT_URL: 'http://mailpit:8025',
      }
    : baseEnv
  const runtime = {
    image: backendImage,
    pull_policy: 'never',
    environment,
    networks: ['application', 'database'],
    read_only: true,
    tmpfs: ['/tmp'],
    cap_drop: ['ALL'],
    security_opt: ['no-new-privileges:true'],
    logging,
    mem_limit: '256m',
    labels: { 'traefik.enable': 'false' },
  }
  const services = {
    db: {
      image: 'mysql:8.4',
      restart: 'unless-stopped',
      mem_limit: '768m',
      command: [
        '--innodb-buffer-pool-size=64M',
        '--performance-schema=OFF',
        '--max-connections=50',
      ],
      environment: {
        MYSQL_DATABASE: 'nord_store',
        MYSQL_USER: 'nord',
        MYSQL_PASSWORD: '${DB_PASSWORD:?required}',
        MYSQL_ROOT_PASSWORD: '${DB_ROOT_PASSWORD:?required}',
      },
      volumes: [
        'mysql_data:/var/lib/mysql',
        `${releasePath}/backend/database/schema.sql:/docker-entrypoint-initdb.d/001-schema.sql:ro`,
        `${releasePath}/backend/database/seed.sql:/docker-entrypoint-initdb.d/002-seed.sql:ro`,
      ],
      networks: ['database'],
      logging,
      labels: { 'traefik.enable': 'false' },
      healthcheck: health([
        'CMD-SHELL',
        'MYSQL_PWD="$$MYSQL_PASSWORD" mysql --protocol=TCP -h 127.0.0.1 -u "$$MYSQL_USER" "$$MYSQL_DATABASE" -e "SELECT 1 FROM users LIMIT 1" >/dev/null 2>&1',
      ]),
    },
    migrate: {
      ...runtime,
      build: { context: releasePath, dockerfile: 'backend/Dockerfile' },
      command: ['php', 'bin/migrate.php'],
      depends_on: { db: { condition: 'service_healthy' } },
    },
    backend: {
      ...runtime,
      restart: 'unless-stopped',
      depends_on: { migrate: { condition: 'service_completed_successfully' } },
      healthcheck: health([
        'CMD',
        'php',
        '-r',
        'require "src/bootstrap.php"; db()->query("SELECT 1");',
      ]),
    },
    web: {
      image: webImage,
      pull_policy: 'never',
      build: { context: releasePath, target: 'web' },
      restart: 'unless-stopped',
      networks: ['application'],
      logging,
      mem_limit: '128m',
      depends_on: { backend: { condition: 'service_healthy' } },
      healthcheck: health(['CMD', 'wget', '-q', '-O', '/dev/null', 'http://127.0.0.1/api/health']),
      labels: testing
        ? { 'traefik.enable': 'false' }
        : {
            'traefik.enable': 'true',
            'traefik.docker.network': 'nord_application',
            'traefik.http.routers.nord.rule': 'Host(`${SITE_DOMAIN:?required}`)',
            'traefik.http.routers.nord.entrypoints': 'websecure',
            'traefik.http.routers.nord.tls': 'true',
            'traefik.http.routers.nord.tls.certresolver': 'letsencrypt-http',
            'traefik.http.routers.nord.service': 'nord',
            'traefik.http.routers.nord-www.rule': 'Host(`www.${SITE_DOMAIN:?required}`)',
            'traefik.http.routers.nord-www.entrypoints': 'websecure',
            'traefik.http.routers.nord-www.tls': 'true',
            'traefik.http.routers.nord-www.tls.certresolver': 'letsencrypt-http',
            'traefik.http.routers.nord-www.service': 'nord',
            'traefik.http.routers.nord-www.middlewares': 'nord-noindex,nord-www-redirect',
            'traefik.http.middlewares.nord-www-redirect.redirectregex.regex': '^https?://www\\.(.+)',
            'traefik.http.middlewares.nord-www-redirect.redirectregex.replacement': 'https://$${1}',
            'traefik.http.middlewares.nord-www-redirect.redirectregex.permanent': 'true',
            'traefik.http.services.nord.loadbalancer.server.port': '80',
            'traefik.http.middlewares.nord-noindex.headers.customresponseheaders.X-Robots-Tag':
              'noindex, nofollow, noarchive, nosnippet',
            'traefik.http.routers.nord.middlewares': 'nord-noindex',
          },
    },
  }
  if (testing) {
    services.mailpit = {
      image: 'axllent/mailpit:v1.33.0',
      networks: ['application'],
      labels: { 'traefik.enable': 'false' },
      logging,
      mem_limit: '128m',
    }
    services['mail-worker'] = {
      ...runtime,
      command: ['php', 'bin/mail-worker.php'],
      depends_on: {
        migrate: { condition: 'service_completed_successfully' },
        mailpit: { condition: 'service_started' },
      },
    }
    services.verify = {
      ...runtime,
      command: ['sh', '-c', 'php bin/security-tests.php && php bin/smoke.php'],
      depends_on: {
        web: { condition: 'service_healthy' },
        'mail-worker': { condition: 'service_started' },
      },
    }
  } else {
    services['mail-worker'] = {
      ...runtime,
      profiles: ['smtp'],
      restart: 'unless-stopped',
      command: ['php', 'bin/mail-worker.php'],
      depends_on: { migrate: { condition: 'service_completed_successfully' } },
    }
  }
  return {
    name: testing ? 'nord-verify' : 'nord',
    services,
    volumes: { mysql_data: {} },
    networks: { application: {}, database: { internal: true } },
  }
}
for (const testing of [true, false]) {
  const name = testing ? 'verify' : 'production'
  await writeFile(resolve(generated, `${name}.json`), JSON.stringify(compose(testing)) + '\n')
  const secretsPath = resolve(root, `.env.hostinger.${name}`)
  try {
    await readFile(secretsPath)
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
    await writeFile(
      secretsPath,
      [
        'SITE_DOMAIN=nord.72.60.25.131.sslip.io',
        `APP_KEY=${randomBytes(32).toString('base64')}`,
        `DB_PASSWORD=${randomBytes(32).toString('hex')}`,
        `DB_ROOT_PASSWORD=${randomBytes(32).toString('hex')}`,
        `BACKUP_ENCRYPTION_KEY=${randomBytes(32).toString('base64')}`,
        '',
      ].join('\n'),
      { mode: 0o600 },
    )
  }
}
await writeFile(
  resolve(generated, 'release.json'),
  JSON.stringify({ revision, releasePath, backendImage, webImage }, null, 2) + '\n',
)
console.log(JSON.stringify({ revision, releasePath, backendImage, webImage, generated }))
