<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowUpRight, LoaderCircle, MessageCircle, RotateCcw, Send, X } from 'lucide-vue-next'
import { api } from '@/lib/api'
import { formatPrice } from '@/utils/format'

type ChatProduct = {
  id: string
  slug: string
  name: string
  price: number
  stock: number
  short_description: string | null
  is_test: boolean
}
type Message = { id: number; role: 'user' | 'assistant'; content: string; products?: ChatProduct[] }
type Answer = { message: string; products: ChatProduct[] }
const route = useRoute()
const open = ref(false),
  draft = ref(''),
  messages = ref<Message[]>([]),
  busy = ref(false),
  error = ref('')
const available = ref<boolean | null>(null),
  checking = ref(false)
const input = ref<HTMLTextAreaElement>(),
  launcher = ref<HTMLButtonElement>(),
  timeline = ref<HTMLDivElement>()
let nextId = 0
let controller: AbortController | undefined
const productSlug = computed(() =>
  route.name === 'product' && typeof route.params.slug === 'string' ? route.params.slug : '',
)
const prompts = ['Подберите лампу до $30', 'Нужна лампа для чтения', 'Как оформить заказ?']

async function checkAvailability() {
  checking.value = true
  error.value = ''
  try {
    available.value = (
      await api<{ available: boolean }>('chat/status', { signal: AbortSignal.timeout(8000) })
    ).available
    if (available.value) {
      await nextTick()
      input.value?.focus()
    }
  } catch {
    available.value = null
    error.value = 'Не удалось подключиться к чату. Проверьте соединение и попробуйте ещё раз.'
  } finally {
    checking.value = false
  }
}

async function scrollToLatest() {
  await nextTick()
  timeline.value?.scrollTo({ top: timeline.value.scrollHeight, behavior: 'instant' })
}

async function toggle() {
  open.value = !open.value
  if (open.value) {
    await nextTick()
    input.value?.focus()
    void scrollToLatest()
    if (available.value !== true && !checking.value) void checkAvailability()
  } else {
    launcher.value?.focus()
  }
}

function history() {
  let bytes = 0
  const items: { role: 'user' | 'assistant'; content: string }[] = []
  for (const message of messages.value.slice(-12).reverse()) {
    const content =
      message.content +
      (message.products?.length
        ? '\nТовары в этом ответе: ' + message.products.map((product) => product.slug).join(', ')
        : '')
    bytes += new TextEncoder().encode(content).length
    if (bytes > 26000) break
    items.unshift({ role: message.role, content })
  }
  return items
}

async function send(text = draft.value) {
  text = text.trim()
  if (!text || text.length > 2000 || busy.value || available.value !== true) return
  const previous = history()
  const userMessage: Message = { id: ++nextId, role: 'user', content: text }
  messages.value.push(userMessage)
  draft.value = ''
  error.value = ''
  busy.value = true
  const requestController = new AbortController()
  controller = requestController
  const timeout = window.setTimeout(() => requestController.abort(), 65000)
  void scrollToLatest()
  try {
    const answer = await api<Answer>('chat', {
      method: 'POST',
      body: JSON.stringify({ message: text, history: previous, product_slug: productSlug.value }),
      signal: requestController.signal,
    })
    messages.value.push({
      id: ++nextId,
      role: 'assistant',
      content: answer.message,
      products: answer.products,
    })
  } catch (cause) {
    messages.value = messages.value.filter((message) => message.id !== userMessage.id)
    draft.value = text
    error.value = requestController.signal.aborted
      ? 'Ответ занял слишком много времени. Вопрос сохранён — попробуйте отправить ещё раз.'
      : cause instanceof Error
        ? cause.message
        : 'Не удалось отправить сообщение. Попробуйте ещё раз.'
  } finally {
    window.clearTimeout(timeout)
    busy.value = false
    controller = undefined
    void scrollToLatest()
    if (open.value && document.activeElement?.closest('.chat-widget')) {
      await nextTick()
      input.value?.focus()
    }
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    void send()
  }
}
function reset() {
  if (busy.value) return
  messages.value = []
  draft.value = ''
  error.value = ''
  input.value?.focus()
}
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="chat-widget" @keydown.esc.stop="open && toggle()">
    <Transition name="chat-panel">
      <section
        v-if="open"
        id="nord-chat"
        class="chat-panel"
        role="region"
        aria-labelledby="chat-title"
      >
        <header class="chat-header">
          <div class="chat-identity">
            <MessageCircle aria-hidden="true" :size="22" />
            <div>
              <h2 id="chat-title">Помощник NORD</h2>
              <p>О товарах и вашем выборе</p>
            </div>
          </div>
          <div class="chat-header-actions">
            <button
              v-if="messages.length"
              class="chat-icon"
              type="button"
              aria-label="Начать новый диалог"
              title="Новый диалог"
              :disabled="busy"
              @click="reset"
            >
              <RotateCcw :size="17" aria-hidden="true" />
            </button>
            <button class="chat-icon" type="button" aria-label="Закрыть чат" @click="toggle">
              <X :size="21" aria-hidden="true" />
            </button>
          </div>
        </header>
        <div ref="timeline" class="chat-timeline">
          <div v-if="!messages.length" class="chat-welcome">
            <h3>Найдём подходящий свет.</h3>
            <p>Расскажите, для чего нужна лампа. Помогу сравнить характеристики, цены и наличие.</p>
            <p v-if="productSlug" class="chat-context">
              Можем обсудить товар, который вы сейчас смотрите.
            </p>
            <div class="chat-prompts" aria-label="Примеры вопросов">
              <button
                v-for="prompt in prompts"
                :key="prompt"
                type="button"
                :disabled="busy || available !== true"
                @click="send(prompt)"
              >
                {{ prompt }}<ArrowUpRight :size="16" aria-hidden="true" />
              </button>
            </div>
          </div>
          <div class="chat-messages" role="log" aria-live="polite" aria-label="История диалога">
            <article
              v-for="message in messages"
              :key="message.id"
              class="chat-message"
              :class="`chat-message--${message.role}`"
              :aria-label="message.role === 'user' ? 'Вы' : 'Помощник NORD'"
            >
              <span class="chat-author">{{ message.role === 'user' ? 'Вы' : 'NORD' }}</span>
              <p>{{ message.content }}</p>
              <ul
                v-if="message.products?.length"
                class="chat-products"
                aria-label="Товары из каталога"
              >
                <li v-for="product in message.products" :key="product.id">
                  <RouterLink :to="`/product/${product.slug}`" @click="open = false">
                    <span class="chat-product-title">{{ product.name }}</span>
                    <span v-if="product.short_description" class="chat-product-description">{{
                      product.short_description
                    }}</span>
                    <span class="chat-product-meta"
                      ><b>{{ formatPrice(product.price) }}</b
                      ><span>{{
                        product.stock > 0 ? `В наличии: ${product.stock}` : 'Нет в наличии'
                      }}</span
                      ><ArrowUpRight :size="16" aria-hidden="true"
                    /></span>
                    <small v-if="product.is_test">Вымышленный товар · тестовые данные</small>
                  </RouterLink>
                </li>
              </ul>
            </article>
          </div>
          <p v-if="busy" class="chat-thinking" role="status">
            <LoaderCircle :size="15" aria-hidden="true" /> Проверяю информацию…
          </p>
        </div>
        <div class="chat-bottom">
          <div v-if="available === false" class="chat-notice" role="status">
            <p>Помощник пока недоступен. Товары можно найти в каталоге.</p>
            <button type="button" :disabled="checking" @click="checkAvailability">
              Проверить снова
            </button>
          </div>
          <div v-if="error" class="chat-notice chat-notice--error" role="alert">
            <p>{{ error }}</p>
            <button
              v-if="available === null"
              type="button"
              :disabled="checking"
              @click="checkAvailability"
            >
              Подключиться снова
            </button>
          </div>
          <nav class="chat-links" aria-label="Разделы магазина">
            <RouterLink to="/catalog" @click="open = false">Каталог</RouterLink>
            <RouterLink to="/cart" @click="open = false">Корзина</RouterLink>
            <RouterLink to="/account" @click="open = false">Мои заказы</RouterLink>
          </nav>
          <form class="chat-composer" @submit.prevent="send()">
            <label class="chat-sr-only" for="chat-message">Ваш вопрос</label>
            <textarea
              id="chat-message"
              ref="input"
              v-model="draft"
              rows="2"
              maxlength="2000"
              placeholder="Спросите о товаре или сайте…"
              :disabled="busy || available !== true"
              aria-describedby="chat-privacy"
              @keydown="onKeydown"
            />
            <button
              type="submit"
              class="chat-send"
              aria-label="Отправить сообщение"
              :disabled="busy || available !== true || !draft.trim()"
            >
              <Send :size="18" aria-hidden="true" />
            </button>
          </form>
          <p id="chat-privacy" class="chat-privacy">
            Ответы создаёт ИИ OpenAI. Не указывайте личные данные.
          </p>
        </div>
      </section>
    </Transition>
    <button
      ref="launcher"
      class="chat-launcher"
      type="button"
      :aria-expanded="open"
      aria-controls="nord-chat"
      :aria-label="open ? 'Закрыть помощника NORD' : 'Открыть помощника NORD'"
      @click="toggle"
    >
      <X v-if="open" :size="22" aria-hidden="true" /><MessageCircle
        v-else
        :size="22"
        aria-hidden="true"
      /><span>{{ open ? 'Закрыть' : 'Помочь с выбором?' }}</span>
    </button>
  </div>
</template>

<style scoped lang="scss">
.chat-widget {
  --chat-muted: #5e6158;
  position: fixed;
  right: 24px;
  bottom: max(24px, env(safe-area-inset-bottom));
  z-index: 45;
  font-family: var(--sans);
  font-size: 14px;
  line-height: 1.5;
  button:focus-visible,
  a:focus-visible,
  textarea:focus-visible {
    outline: 2px solid var(--ink);
    outline-offset: 4px;
  }
  button:disabled {
    cursor: default;
    opacity: 0.5;
  }
  ::selection {
    background: var(--sage);
    color: var(--ink);
  }
}
.chat-launcher {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 52px;
  padding: 12px 20px;
  background: var(--ink);
  color: var(--white);
  border: 0;
  border-radius: 28px;
  font-weight: 500;
  margin-left: auto;
  box-shadow: 0 6px 20px rgb(21 23 19 / 16%);
  &:hover {
    background: #353a2e;
  }
}
.chat-panel {
  display: flex;
  flex-direction: column;
  width: min(420px, calc(100vw - 48px));
  height: min(660px, calc(100dvh - 116px));
  margin-bottom: 14px;
  background: var(--white);
  border-radius: 16px;
  box-shadow: 0 12px 54px rgb(21 23 19 / 22%);
  overflow: hidden;
}
.chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 20px 16px 18px 22px;
  background: var(--sage);
  flex-shrink: 0;
}
.chat-identity {
  display: flex;
  align-items: center;
  gap: 12px;
}
.chat-header h2 {
  font: 600 16px/1.3 var(--sans);
  letter-spacing: 0;
  margin: 0 0 3px;
}
.chat-header p {
  margin: 0;
  color: #464c3d;
  font-size: 12px;
}
.chat-header-actions {
  display: flex;
}
.chat-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  &:hover {
    background: rgb(21 23 19 / 8%);
  }
}
.chat-timeline {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 24px 22px;
  scrollbar-color: var(--line) transparent;
  scrollbar-width: thin;
}
.chat-welcome h3 {
  font-size: 23px;
  line-height: 1.25;
  font-weight: 500;
  letter-spacing: -0.03em;
  margin-bottom: 12px;
}
.chat-welcome > p {
  color: var(--chat-muted);
  margin-bottom: 20px;
}
.chat-welcome .chat-context {
  color: var(--ink);
  font-size: 12px;
}
.chat-prompts {
  display: grid;
  gap: 8px;
  margin-top: 24px;
}
.chat-prompts button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 12px;
  border: 1px solid var(--line);
  background: transparent;
  text-align: left;
  border-radius: 8px;
  font-size: 13px;
  &:hover:not(:disabled) {
    background: var(--paper);
    border-color: var(--ink);
  }
  svg {
    flex-shrink: 0;
  }
}
.chat-messages {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.chat-message {
  max-width: 100%;
  overflow-wrap: anywhere;
}
.chat-author {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--chat-muted);
  margin-bottom: 6px;
}
.chat-message > p {
  margin: 0;
  white-space: pre-wrap;
}
.chat-message--user {
  align-self: flex-end;
  max-width: 92%;
  background: var(--paper);
  padding: 12px 14px;
  border-radius: 12px 12px 2px 12px;
}
.chat-products {
  list-style: none;
  margin: 16px 0 0;
  padding: 0;
}
.chat-products li {
  border-top: 1px solid var(--line);
}
.chat-products a {
  display: block;
  padding: 15px 0;
}
.chat-products a:hover .chat-product-title {
  text-decoration: underline;
  text-underline-offset: 3px;
}
.chat-product-title {
  display: block;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.45;
}
.chat-product-description {
  display: block;
  color: var(--chat-muted);
  font-size: 12px;
  margin-top: 6px;
}
.chat-product-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
.chat-product-meta b {
  font-size: 14px;
}
.chat-product-meta svg {
  margin-left: auto;
  flex-shrink: 0;
}
.chat-products small {
  display: block;
  margin-top: 5px;
  font-size: 10px;
  color: var(--chat-muted);
}
.chat-thinking {
  display: flex;
  gap: 8px;
  align-items: center;
  margin: 20px 0 0;
  color: var(--chat-muted);
  font-size: 13px;
}
.chat-thinking svg {
  animation: chat-spin 1.5s linear infinite;
}
.chat-bottom {
  padding: 0 18px 14px;
  flex-shrink: 0;
}
.chat-links {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
  padding: 12px 0;
  border-top: 1px solid var(--line);
  font-size: 12px;
  color: var(--chat-muted);
}
.chat-links a:hover {
  color: var(--ink);
  text-decoration: underline;
  text-underline-offset: 3px;
}
.chat-composer {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 8px;
  background: var(--white);
}
.chat-composer:focus-within {
  border-color: var(--ink);
}
.chat-composer textarea {
  width: 100%;
  min-width: 0;
  height: 58px;
  max-height: 120px;
  padding: 5px;
  resize: vertical;
  background: transparent;
  border: 0;
  font-size: 14px;
  line-height: 1.5;
  caret-color: var(--ink);
}
.chat-composer textarea::placeholder {
  color: var(--chat-muted);
}
.chat-widget .chat-composer textarea:focus-visible {
  outline-offset: 0;
}
.chat-send {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 7px;
  color: var(--white);
  background: var(--ink);
}
.chat-send:hover:not(:disabled) {
  background: #353a2e;
}
.chat-privacy {
  color: var(--chat-muted);
  font-size: 10px;
  line-height: 1.4;
  margin: 9px 0 0;
}
.chat-notice {
  margin-bottom: 12px;
  padding: 12px;
  background: var(--paper);
  border-radius: 8px;
  font-size: 12px;
}
.chat-notice p {
  margin: 0;
}
.chat-notice button {
  padding: 4px 0 0;
  border: 0;
  background: none;
  text-decoration: underline;
  text-underline-offset: 3px;
}
.chat-notice--error {
  color: #872f25;
}
.chat-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
  border: 0;
}
.chat-panel-enter-active,
.chat-panel-leave-active {
  transition:
    opacity 160ms ease-out,
    transform 160ms ease-out;
}
.chat-panel-enter-from,
.chat-panel-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
@keyframes chat-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (max-width: 540px) {
  .chat-widget {
    right: 12px;
    bottom: max(12px, env(safe-area-inset-bottom));
  }
  .chat-panel {
    width: calc(100vw - 24px);
    height: min(660px, calc(100dvh - 90px));
    margin-bottom: 10px;
  }
  .chat-header {
    padding: 16px 12px 16px 18px;
  }
  .chat-timeline {
    padding: 22px 18px;
  }
  .chat-launcher {
    min-height: 48px;
    padding: 10px 16px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .chat-panel-enter-active,
  .chat-panel-leave-active {
    transition: none;
  }
  .chat-thinking svg {
    animation: none;
  }
}
</style>
