import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
export default defineConfig(() => {
  const dockerEnv = loadEnv('docker', fileURLToPath(new URL('.', import.meta.url)), '')
  const apiPort = process.env.HTTP_PORT || dockerEnv.HTTP_PORT || '8080'
  return {
    base: '/',
    plugins: [vue()],
    resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
    server: {
      proxy: { '/api': `http://127.0.0.1:${apiPort}` },
    },
  }
})
