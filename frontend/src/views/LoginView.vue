<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Key, User, View } from '@element-plus/icons-vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'

const quickAccounts = [
  { username: 'store001', label: '店家', description: '提交申请与补资料' },
  { username: 'sales001', label: '业务员', description: '到店验机与资料协助' },
  { username: 'audit001', label: '审核员', description: '派单、审核、驳回' },
  { username: 'cashier001', label: '出纳', description: '打款与上传凭证' },
  { username: 'admin001', label: '超管', description: '账号、重置、状态调整' },
]

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const form = reactive({
  username: 'store001',
  password: '123456',
})
const submitError = ref('')

const redirectPath = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' ? redirect : ''
})

function fillAccount(username: string) {
  form.username = username
  form.password = '123456'
}

async function submitLogin() {
  submitError.value = ''

  try {
    await auth.login(form.username, form.password)
    router.push(redirectPath.value || auth.homePath)
  } catch (error) {
    const message = axios.isAxiosError(error)
      ? error.response?.data?.error?.message
      : undefined

    submitError.value = message ?? '登录失败，请检查账号或后端服务。'
    ElMessage.error(submitError.value)
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-hero">
      <div class="hero-copy">
        <p class="eyebrow">v0.1 远程彩排版 MVP</p>
        <h1>把回收金融流程跑通给领导看</h1>
        <p class="hero-subtitle">
          店家、业务员、审核、出纳、超级管理员五个角色使用同一套演示数据，完整彩排从申请到打款凭证推送的业务闭环。
        </p>
        <div class="hero-metrics">
          <div>
            <strong>5</strong>
            <span>角色账号</span>
          </div>
          <div>
            <strong>1</strong>
            <span>闭环流程</span>
          </div>
          <div>
            <strong>0.1</strong>
            <span>演示版本</span>
          </div>
        </div>
      </div>

      <div class="login-panel">
        <div class="panel-title">
          <div>
            <p>账号登录</p>
            <h2>选择角色开始彩排</h2>
          </div>
          <el-icon><View /></el-icon>
        </div>

        <el-form label-position="top" @submit.prevent="submitLogin">
          <el-form-item label="账号">
            <el-input v-model="form.username" size="large" :prefix-icon="User" />
          </el-form-item>
          <el-form-item label="密码">
            <el-input
              v-model="form.password"
              size="large"
              type="password"
              show-password
              :prefix-icon="Key"
            />
          </el-form-item>
          <el-alert
            v-if="submitError"
            :title="submitError"
            type="error"
            show-icon
            :closable="false"
          />
          <el-button
            class="login-submit"
            type="danger"
            size="large"
            native-type="submit"
            :loading="auth.loading"
          >
            登录进入系统
          </el-button>
        </el-form>

        <div class="quick-login">
          <button
            v-for="account in quickAccounts"
            :key="account.username"
            type="button"
            :class="{ active: form.username === account.username }"
            @click="fillAccount(account.username)"
          >
            <strong>{{ account.label }}</strong>
            <span>{{ account.username }} / 123456</span>
            <small>{{ account.description }}</small>
          </button>
        </div>
      </div>
    </section>
  </main>
</template>
