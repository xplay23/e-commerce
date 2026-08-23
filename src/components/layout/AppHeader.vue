<script setup lang="ts">
import { ref } from 'vue'
import { Menu, X, Search, Heart, UserRound, ShoppingBag } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
const open = ref(false),
  cart = useCartStore()
</script>
<template>
  <header class="header">
    <div class="header__inner container">
      <button class="menu-button" @click="open = !open"><X v-if="open" /><Menu v-else /></button
      ><RouterLink to="/" class="logo">NORD<span>®</span></RouterLink>
      <nav :class="{ open }">
        <RouterLink to="/catalog" @click="open = false">Shop</RouterLink
        ><RouterLink to="/catalog?category=living" @click="open = false">Living</RouterLink
        ><RouterLink to="/catalog?category=workspace" @click="open = false">Workspace</RouterLink
        ><RouterLink to="/about" @click="open = false">Our story</RouterLink>
      </nav>
      <div class="header__actions">
        <RouterLink to="/catalog" aria-label="Search"><Search /></RouterLink
        ><RouterLink to="/account/favorites" aria-label="Favorites"><Heart /></RouterLink
        ><RouterLink to="/account" aria-label="Account"><UserRound /></RouterLink
        ><RouterLink to="/cart" class="bag" aria-label="Cart"
          ><ShoppingBag /><b v-if="cart.count">{{ cart.count }}</b></RouterLink
        >
      </div>
    </div>
  </header>
</template>
