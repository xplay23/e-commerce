<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { getCategories } from '@/services/products'
import {
  createProduct,
  deleteProduct,
  getAdminProducts,
  updateProduct,
  type ProductPayload,
} from '@/services/admin'
import { formatPrice, placeholderImage } from '@/utils/format'
import type { Category, Product } from '@/types'

const products = ref<Product[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const editingId = ref<string | null>(null)
const formOpen = ref(false)

const emptyForm = (): ProductPayload => ({
  category_id: '',
  name: '',
  slug: '',
  description: '',
  short_description: '',
  price: 0,
  old_price: null,
  stock: 0,
  image_url: '',
  is_active: true,
  is_featured: false,
})
const form = reactive<ProductPayload>(emptyForm())

async function load() {
  loading.value = true
  error.value = ''
  try {
    ;[products.value, categories.value] = await Promise.all([getAdminProducts(), getCategories()])
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Could not load admin data'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  Object.assign(form, emptyForm())
  editingId.value = null
  formOpen.value = false
  error.value = ''
}

function openCreate() {
  Object.assign(form, emptyForm())
  editingId.value = null
  formOpen.value = true
}

function openEdit(product: Product) {
  editingId.value = product.id
  Object.assign(form, {
    category_id: product.category_id ?? '',
    name: product.name,
    slug: product.slug,
    description: product.description,
    short_description: product.short_description ?? '',
    price: product.price,
    old_price: product.old_price,
    stock: product.stock,
    image_url: product.image_url ?? '',
    is_active: product.is_active,
    is_featured: product.is_featured,
  })
  formOpen.value = true
}

function makeSlug() {
  if (!editingId.value || !form.slug) {
    form.slug = form.name
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '')
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editingId.value) await updateProduct(editingId.value, form)
    else await createProduct(form)
    resetForm()
    await load()
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Could not save product'
  } finally {
    saving.value = false
  }
}

async function remove(product: Product) {
  if (!window.confirm(`Delete “${product.name}”? This action cannot be undone.`)) return
  try {
    await deleteProduct(product.id)
    products.value = products.value.filter((item) => item.id !== product.id)
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Could not delete product'
  }
}

onMounted(load)
</script>

<template>
  <div class="container admin-page">
    <header class="admin-heading">
      <div>
        <p class="eyebrow">Administration</p>
        <h1>Products</h1>
      </div>
      <BaseButton @click="openCreate"><Plus :size="18" /> Add product</BaseButton>
    </header>

    <p v-if="error" class="alert">{{ error }}</p>
    <p v-if="loading" class="page-loader">Loading products…</p>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td>
              <div class="admin-product">
                <img :src="product.image_url || placeholderImage" :alt="product.name" />
                <div>
                  <b>{{ product.name }}</b
                  ><small>{{ product.slug }}</small>
                </div>
              </div>
            </td>
            <td>{{ product.category?.name || '—' }}</td>
            <td>{{ formatPrice(product.price) }}</td>
            <td>{{ product.stock }}</td>
            <td>
              <span :class="['status-dot', { off: !product.is_active }]">{{
                product.is_active ? 'Active' : 'Draft'
              }}</span>
            </td>
            <td class="admin-actions">
              <button class="icon-button" aria-label="Edit" @click="openEdit(product)">
                <Pencil :size="17" /></button
              ><button class="icon-button danger" aria-label="Delete" @click="remove(product)">
                <Trash2 :size="17" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="formOpen" class="modal-backdrop" @click.self="resetForm">
      <section class="admin-form">
        <header>
          <div>
            <p class="eyebrow">{{ editingId ? 'Edit product' : 'New product' }}</p>
            <h2>{{ editingId ? form.name : 'Add an object' }}</h2>
          </div>
          <button class="icon-button" @click="resetForm"><X /></button>
        </header>
        <form @submit.prevent="save">
          <div class="form-row">
            <BaseInput v-model="form.name" label="Name" @blur="makeSlug" /><BaseInput
              v-model="form.slug"
              label="Slug"
            />
          </div>
          <label class="field"
            ><span>Category</span
            ><select v-model="form.category_id">
              <option value="">No category</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select></label
          >
          <BaseInput v-model="form.short_description" label="Short description" />
          <label class="field"
            ><span>Description</span><textarea v-model="form.description" rows="5" required />
          </label>
          <div class="form-row">
            <BaseInput v-model="form.price" label="Price" type="number" :min="0" /><BaseInput
              v-model="form.old_price"
              label="Old price"
              type="number"
              :min="0"
            /><BaseInput v-model="form.stock" label="Stock" type="number" :min="0" />
          </div>
          <BaseInput v-model="form.image_url" label="Image URL" />
          <div class="admin-checks">
            <label><input v-model="form.is_active" type="checkbox" /> Active</label
            ><label><input v-model="form.is_featured" type="checkbox" /> Featured</label>
          </div>
          <p v-if="error" class="alert">{{ error }}</p>
          <div class="admin-form__actions">
            <BaseButton variant="ghost" @click="resetForm">Cancel</BaseButton
            ><BaseButton type="submit" :loading="saving">{{
              editingId ? 'Save changes' : 'Create product'
            }}</BaseButton>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
