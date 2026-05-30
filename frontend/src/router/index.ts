import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AppShell from '../views/AppShell.vue'
import LoginView from '../views/LoginView.vue'
import WorkspaceView from '../views/WorkspaceView.vue'
import { useAuthStore, type UserRole } from '../stores/auth'

const workspaceRoutes: RouteRecordRaw[] = [
  {
    path: '/store',
    component: WorkspaceView,
    meta: {
      title: '店家工作台',
      role: 'STORE',
      badge: '提交验机申请',
      primaryAction: '新建申请',
    },
  },
  {
    path: '/sales',
    component: WorkspaceView,
    meta: {
      title: '业务员工作台',
      role: 'SALES',
      badge: '到店验机',
      primaryAction: '查看验机任务',
    },
  },
  {
    path: '/audit',
    component: WorkspaceView,
    meta: {
      title: '审核员工作台',
      role: 'AUDITOR',
      badge: '审核与派单',
      primaryAction: '处理待审核',
    },
  },
  {
    path: '/cashier',
    component: WorkspaceView,
    meta: {
      title: '出纳工作台',
      role: 'CASHIER',
      badge: '打款凭证',
      primaryAction: '上传凭证',
    },
  },
  {
    path: '/admin',
    component: WorkspaceView,
    meta: {
      title: '超级管理员',
      role: 'SUPER_ADMIN',
      badge: '彩排控制台',
      primaryAction: '重置演示数据',
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
