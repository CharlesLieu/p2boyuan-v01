import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AppShell from '../components/layout/AppShell.vue'
import LoginView from '../views/LoginView.vue'
import StoreWorkspace from '../views/store/StoreWorkspace.vue'
import SalesWorkspace from '../views/sales/SalesWorkspace.vue'
import AuditWorkspace from '../views/audit/AuditWorkspace.vue'
import CashierWorkspace from '../views/cashier/CashierWorkspace.vue'
import AdminWorkspace from '../views/admin/AdminWorkspace.vue'
import { useAuthStore, type UserRole } from '../stores/auth'

const workspaceRoutes: RouteRecordRaw[] = [
  {
    path: '/store',
    component: StoreWorkspace,
    meta: {
      title: '商家工作台',
      role: 'STORE',
      badge: '商家入驻与凭证',
      primaryAction: '查看凭证',
    },
  },
  {
    path: '/sales',
    component: SalesWorkspace,
    meta: {
      title: '业务员工作台',
      role: 'SALES',
      badge: '到店验机',
      primaryAction: '查看验机任务',
    },
  },
  {
    path: '/audit',
    component: AuditWorkspace,
    meta: {
      title: '审核员工作台',
      role: 'AUDITOR',
      badge: '审核与派单',
      primaryAction: '处理待审核',
    },
  },
  {
    path: '/cashier',
    component: CashierWorkspace,
    meta: {
      title: '出纳工作台',
      role: 'CASHIER',
      badge: '打款凭证',
      primaryAction: '上传凭证',
    },
  },
  {
    path: '/admin',
    component: AdminWorkspace,
    meta: {
      title: '超级管理员',
      role: 'SUPER_ADMIN',
      badge: '管理控制台',
      primaryAction: '商家与凭证管理',
    },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/login',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/',
      component: AppShell,
      children: workspaceRoutes,
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.path === '/login') {
    if (!auth.user && auth.token) {
      await auth.fetchMe().catch(() => auth.clearSession())
    }

    return auth.user ? auth.homePath : true
  }

  if (!auth.user && auth.token) {
    await auth.fetchMe().catch(() => auth.clearSession())
  }

  if (!auth.user) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  const requiredRole = to.meta.role as UserRole | undefined

  if (requiredRole && auth.user.role !== requiredRole && auth.user.role !== 'SUPER_ADMIN') {
    return auth.homePath
  }

  return true
})
