import assert from 'node:assert/strict'

const base = new URL(process.argv[2] || 'https://nord.72.60.25.131.sslip.io')
let checks = 0
const check = (condition, label) => {
  assert.ok(condition, label)
  checks++
  console.log(`OK: ${label}`)
}
async function request(path, status = 200, options = {}) {
  const response = await fetch(new URL(path, base), {
    ...options,
    signal: AbortSignal.timeout(15000),
  })
  check(response.status === status, `${path}: HTTP ${status}`)
  check(/noindex/i.test(response.headers.get('x-robots-tag') || ''), `${path}: noindex`)
  return response
}
const home = await request('/')
const html = await home.text()
check(html.includes('<div id="app">'), 'Vue application served')
check(/name="robots" content="[^"]*noindex/.test(html), 'HTML robots meta')
const script = html.match(/src="(\/assets\/[^\"]+\.js)"/)
check(Boolean(script), 'Built JavaScript linked')
await request(script[1])
const css = html.match(/href="(\/assets\/[^\"]+\.css)"/)
check(Boolean(css), 'Built CSS linked')
await request(css[1])
for (const path of [
  '/catalog',
  '/login',
  '/register',
  '/cart',
  '/checkout',
  '/account',
  '/account/favorites',
  '/admin',
]) {
  const response = await request(path)
  check((await response.text()).includes('<div id="app">'), `${path}: SPA deep link`)
}
const robots = await (await request('/robots.txt')).text()
check(/User-agent:\s*\*/i.test(robots) && /Disallow:\s*\//i.test(robots), 'All crawlers disallowed')
const health = await (await request('/api/health')).json()
check(health.status === 'ok', 'Production API connects to MySQL')
const categories = await (await request('/api/categories')).json()
check(Array.isArray(categories) && categories.length > 0, 'Categories loaded from MySQL')
const products = await (await request('/api/products')).json()
check(products.count > 0 && products.items.length > 0, 'Catalog loaded from MySQL')
const product = products.items[0]
const detail = await (await request(`/api/products/${product.slug}`)).json()
check(detail.id === product.id && detail.price === product.price, 'Product detail matches catalog')
await request('/api/orders', 401)
await request('/api/admin/products', 401)
await request('/api/does-not-exist', 404)
await request('/assets/does-not-exist.js', 404)
await request('/.env.docker', 403)
const csrfResponse = await request('/api/auth/csrf')
const csrf = await csrfResponse.json()
check(typeof csrf.csrf_token === 'string' && csrf.csrf_token.length === 64, 'CSRF endpoint works')
const cookies = csrfResponse.headers.getSetCookie()
check(
  cookies.some((cookie) => /nord_csrf_context=.*;.*secure;.*httponly;.*samesite=lax/i.test(cookie)),
  'Secure HttpOnly SameSite cookie over HTTPS',
)
await request('/api/auth/login', 403, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Origin: base.origin },
  body: '{}',
})
await request('/api/auth/login', 403, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Origin: 'https://invalid.example',
    Cookie: cookies.map((cookie) => cookie.split(';')[0]).join('; '),
    'X-CSRF-Token': csrf.csrf_token,
  },
  body: '{}',
})
console.log(`Passed ${checks} public deployment checks against ${base.origin}.`)
