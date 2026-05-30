import axios from 'axios'

export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message?: string | null
  requestId: string
}

export interface ApiErrorEnvelope {
  success: false
  error: {
    code: string
    message: string
  }
  requestId: string
}

export const authTokenKey = 'p2boyuan.v01.token'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1',
  timeout: 15000,
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(authTokenKey)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(authTokenKey)

      if (window.location.pathname !== '/login') {
        window.location.assign('/login')
      }
    }

    return Promise.reject(error)
  },
)
