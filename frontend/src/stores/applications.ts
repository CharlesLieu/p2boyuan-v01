import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  getApplication,
  getApplicationLogs,
  listApplications,
  type ApplicationItem,
  type ApplicationLog,
} from '../api/modules/applications'

function toErrorMessage(error: unknown) {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { error?: { message?: string } } } }).response
    return response?.data?.error?.message ?? '请求失败，请稍后重试。'
  }

  return '请求失败，请稍后重试。'
}

export const useApplicationsStore = defineStore('applications', () => {
  const items = ref<ApplicationItem[]>([])
  const selected = ref<ApplicationItem | null>(null)
  const logs = ref<ApplicationLog[]>([])
  const loading = ref(false)
  const detailLoading = ref(false)
  const logsLoading = ref(false)
  const error = ref<string | null>(null)

  const selectedId = computed(() => selected.value?.id ?? null)

  async function fetch(limit = 50) {
    loading.value = true
    error.value = null

    try {
      items.value = await listApplications(limit)

      if (items.value.length === 0) {
        selected.value = null
        logs.value = []
        return
      }

      if (!selected.value || !items.value.some((item) => item.id === selected.value?.id)) {
        await select(items.value[0].id)
      }
    } catch (requestError) {
      error.value = toErrorMessage(requestError)
    } finally {
      loading.value = false
    }
  }

  async function select(applicationId: string) {
    detailLoading.value = true
    error.value = null

    try {
      selected.value = await getApplication(applicationId)
      logs.value = []
    } catch (requestError) {
      error.value = toErrorMessage(requestError)
    } finally {
      detailLoading.value = false
    }
  }

  async function loadLogs(applicationId = selectedId.value) {
    if (!applicationId) {
      logs.value = []
      return
    }

    logsLoading.value = true
    error.value = null

    try {
      logs.value = await getApplicationLogs(applicationId)
    } catch (requestError) {
      error.value = toErrorMessage(requestError)
    } finally {
      logsLoading.value = false
    }
  }

  return {
    items,
    selected,
    selectedId,
    logs,
    loading,
    detailLoading,
    logsLoading,
    error,
    fetch,
    select,
    loadLogs,
  }
})
