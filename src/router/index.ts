import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import HomePage from '@/pages/HomePage.vue'
import CatalogPage from '@/pages/CatalogPage.vue'
import ProductPage from '@/pages/ProductPage.vue'
import CartPage from '@/pages/CartPage.vue'
import AuthPage from '@/pages/AuthPage.vue'
import AccountPage from '@/pages/AccountPage.vue'
import FavoritesPage from '@/pages/FavoritesPage.vue'
import CheckoutPage from '@/pages/CheckoutPage.vue'
import SimplePage from '@/pages/SimplePage.vue'
import { useAuthStore } from '@/stores/auth'
const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    {
      path: '/',
      component: DefaultLayout,
      children: [
        { path: '', name: 'home', component: HomePage },
        { path: 'catalog', name: 'catalog', component: CatalogPage },
        { path: 'product/:slug', name: 'product', component: ProductPage },
        { path: 'cart', name: 'cart', component: CartPage },
        { path: 'checkout', name: 'checkout', component: CheckoutPage },
        { path: 'login', name: 'login', component: AuthPage },
        { path: 'register', name: 'register', component: AuthPage },
        { path: 'account', name: 'account', component: AccountPage, meta: { auth: true } },
        {
          path: 'account/favorites',
          name: 'favorites',
          component: FavoritesPage,
          meta: { auth: true },
        },
        {
          path: 'order-success/:id',
          name: 'success',
          component: SimplePage,
          props: { kind: 'success' },
        },
        { path: 'about', name: 'about', component: SimplePage },
        {
          path: 'admin',
          alias: ['/admin/products', '/admin/categories', '/admin/orders'],
          name: 'admin',
          component: SimplePage,
          props: { kind: 'admin' },
          meta: { auth: true, admin: true },
        },
      ],
    },
  ],
})
router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.admin && auth.profile?.role !== 'admin') return { name: 'home' }
})
export default router
