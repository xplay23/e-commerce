import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import HomePage from '@/pages/HomePage.vue'
import CatalogPage from '@/pages/CatalogPage.vue'
import ProductPage from '@/pages/ProductPage.vue'
import CartPage from '@/pages/CartPage.vue'
import AuthPage from '@/pages/AuthPage.vue'
import SecurityPage from '@/pages/SecurityPage.vue'
import AccountPage from '@/pages/AccountPage.vue'
import FavoritesPage from '@/pages/FavoritesPage.vue'
import CheckoutPage from '@/pages/CheckoutPage.vue'
import AdminProductsPage from '@/pages/AdminProductsPage.vue'
import SimplePage from '@/pages/SimplePage.vue'
import { useAuthStore } from '@/stores/auth'
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
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
        ...[
          'forgot-password',
          'reset-password',
          'resend-verification',
          'verify-email',
          'confirm-login',
        ].map((name) => ({ path: name, name, component: SecurityPage })),
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
          alias: ['/admin/products'],
          name: 'admin',
          component: AdminProductsPage,
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
