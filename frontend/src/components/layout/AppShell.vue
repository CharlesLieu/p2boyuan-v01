<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, SwitchButton } from '@element-plus/icons-vue'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const navItems = [
  { path: '/store', label: '商家', role: 'STORE' },
  { path: '/sales', label: '业务员', role: 'SALES' },
  { path: '/audit', label: '审核', role: 'AUDITOR' },
  { path: '/cashier', label: '出纳', role: 'CASHIER' },
  { path: '/admin', label: '超管', role: 'SUPER_ADMIN' },
]

const visibleNavItems = computed(() => {
  if (auth.user?.role === 'SUPER_ADMIN') {
    return navItems
  }

  return navItems.filter((item) => item.role === auth.user?.role)
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-shell">
    <aside class="shell-sidebar">
      <div class="brand-block">
        <div class="brand-mark">B</div>
        <div>
          <p>博远业务系统</p>
          <strong>业务工作台</strong>
        </div>
      </div>

      <nav class="shell-nav">
        <router-link
          v-for="item in visibleNavItems"
          :key="item.path"
          :class="{ active: route.path === item.path }"
          :to="item.path"
        >
          <span>{{ item.label }}</span>
          <el-icon><ArrowRight /></el-icon>
        </router-link>
      </nav>
    </aside>

    <main class="shell-main">
      <header class="shell-header">
        <div>
          <p>当前登录</p>
          <h1>{{ auth.user?.display_name }}</h1>
        </div>
        <div class="user-panel">
          <el-tag effect="dark" type="danger">{{ auth.user?.role }}</el-tag>
          <span>{{ auth.user?.username }}</span>
          <el-button :icon="SwitchButton" plain @click="handleLogout">退出</el-button>
        </div>
      </header>

      <router-view />
    </main>
  </div>
</template>
