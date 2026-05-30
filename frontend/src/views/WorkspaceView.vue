<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { DataBoard, Finished, Money, Tickets } from '@element-plus/icons-vue'

const route = useRoute()

const title = computed(() => String(route.meta.title ?? '工作台'))
const badge = computed(() => String(route.meta.badge ?? '彩排节点'))
const primaryAction = computed(() => String(route.meta.primaryAction ?? '继续处理'))
</script>

<template>
  <section class="workspace-page">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">{{ badge }}</el-tag>
        <h2>{{ title }}</h2>
        <p>这里已经接入真实登录与角色路由，下一步会继续接申请、派单、审核、打款等业务接口。</p>
      </div>
      <el-button type="danger" size="large">{{ primaryAction }}</el-button>
    </div>

    <div class="summary-grid">
      <article>
        <el-icon><Tickets /></el-icon>
        <strong>待处理</strong>
        <span>3 单</span>
      </article>
      <article>
        <el-icon><Finished /></el-icon>
        <strong>今日完成</strong>
        <span>8 单</span>
      </article>
      <article>
        <el-icon><Money /></el-icon>
        <strong>待放款</strong>
        <span>￥21,450</span>
      </article>
      <article>
        <el-icon><DataBoard /></el-icon>
        <strong>演示数据</strong>
        <span>一致</span>
      </article>
    </div>

    <div class="placeholder-board">
      <div class="board-toolbar">
        <h3>流程彩排看板</h3>
        <el-tag>占位页面</el-tag>
      </div>
      <div class="flow-lanes">
        <div>提交申请</div>
        <div>指派验机</div>
        <div>审核资料</div>
        <div>确认打款</div>
        <div>推送凭证</div>
      </div>
    </div>
  </section>
</template>
