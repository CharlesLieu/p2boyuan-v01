import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiClient, authTokenKey, type ApiEnvelope } from '../api/client'

export type UserRole = 'STORE' | 'SALES' | 'AUDITOR' | 'CASHIER' | 'SUPER_ADMIN'

export interface DemoUser {
  id: number
  username: string
  display_name: string
  role: UserRole
  status: string
  store_id: number | null
  sales_agent_id: number | null
  last_login_at: string | null
}

interface LoginResponse {
  token: string
  user: DemoUser
}

const roleHomePath: Record<UserRole, string> = {
  STORE: '/store',
  SALES: '/sales',
  AUDITOR: '/audit',
  CASHIER: '/cashier',
  SUPER_ADMIN: '/admin',
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(authTokenKey))
  const user = ref<DemoUser | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const homePath = computed(() => (user.value ? roleHomePath[user.value.role] : '/login'))

  async function login(username: string, password: string) {
    loading.value = true

    try {
      const response = await apiClient.post<ApiEnvelope<LoginResponse>>('/auth/login', {
        username,
        password,
      })

      token.value = response.data.data.token
      user.value = response.data.data.user
      localStorage.setItem(authTokenKey, token.value)

      return user.value
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) {
      return null
    }

    const response = await apiClient.get<ApiEnvelope<{ user: DemoUser }>>('/auth/me')
    user.value = response.data.data.user

    return user.value
  }

  async function logout() {
    try {
      if (token.value) {
        await apiClient.post('/auth/logout')
      }
    } finally {
      clearSession()
    }
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(authTokenKey)
  }

  return {
    token,
    user,
    loading,
    isAuthenticated,
    homePath,
    login,
    fetchMe,
    logout,
    clearSession,
  }
})
