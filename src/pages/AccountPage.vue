<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>
<template>
  <div class="container narrow-page">
    <p class="eyebrow">Private space</p>
    <h1>My account</h1>
    <div class="account-grid">
      <section>
        <h2>Profile</h2>
        <p>
          <b>{{ auth.profile?.name || auth.user?.user_metadata.name || 'NORD member' }}</b
          ><br />{{ auth.user?.email }}<br />{{ auth.profile?.phone || 'No phone added' }}
        </p>
        <button class="text-link" @click="auth.logout()">Sign out →</button>
      </section>
      <section>
        <h2>Orders</h2>
        <p>Your order history will appear here after your first purchase.</p>
      </section>
      <RouterLink class="account-tile" to="/account/favorites"
        ><span>Saved objects</span><b>View favorites →</b></RouterLink
      >
    </div>
  </div>
</template>
