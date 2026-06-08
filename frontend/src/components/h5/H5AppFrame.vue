<script setup lang="ts">
export interface H5TabItem {
  key: string
  label: string
}

defineProps<{ title: string; tabs: H5TabItem[]; activeTab: string }>()

const emit = defineEmits<{ tabChange: [key: string] }>()
</script>

<template>
  <section class="h5-app-frame">
    <header class="h5-titlebar">
      <h1>{{ title }}</h1>
    </header>

    <main class="h5-app-main">
      <slot />
    </main>

    <nav class="h5-bottom-tabs" aria-label="H5 navigation">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="h5-bottom-tab"
        :class="{ active: tab.key === activeTab }"
        type="button"
        @click="emit('tabChange', tab.key)"
      >
        <span>{{ tab.label }}</span>
      </button>
    </nav>
  </section>
</template>

<style scoped>
.h5-app-frame {
  --h5-bottom-nav-height: 64px;
  min-height: 100dvh;
  padding: 0 14px calc(var(--h5-bottom-nav-height) + env(safe-area-inset-bottom, 0px) + 18px);
  background: var(--h5-bg);
  color: var(--h5-ink);
  font-family: "Microsoft YaHei", "PingFang SC", "Noto Sans CJK SC", Arial, sans-serif;
}

.h5-titlebar {
  position: sticky;
  top: 0;
  z-index: 12;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 54px;
  margin: 0 -14px;
  padding: calc(env(safe-area-inset-top, 0px) + 10px) 18px 10px;
  background: rgba(234, 241, 255, 0.92);
  backdrop-filter: blur(14px);
}

.h5-titlebar h1 {
  min-width: 0;
  margin: 0;
  overflow: hidden;
  color: var(--h5-ink);
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 0;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-app-main {
  display: grid;
  gap: 14px;
  width: min(100%, 560px);
  margin: 0 auto;
  padding-top: 12px;
}

.h5-app-main :deep(.el-button--primary) {
  --el-button-bg-color: var(--h5-blue);
  --el-button-border-color: var(--h5-blue);
  --el-button-hover-bg-color: #4865e8;
  --el-button-hover-border-color: #4865e8;
  --el-button-active-bg-color: #3651cb;
  --el-button-active-border-color: #3651cb;
}

.h5-app-main :deep(.h5-logout-card) {
  display: grid;
  gap: 12px;
  min-width: 0;
  padding: 18px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.h5-app-main :deep(.h5-logout-card span) {
  color: var(--h5-muted);
  font-size: 13px;
  line-height: 1.45;
}

.h5-app-main :deep(.h5-logout-card .el-button) {
  width: 100%;
  min-height: 44px;
  margin-left: 0;
  border-radius: 999px;
  font-weight: 900;
}

.h5-bottom-tabs {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 16;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(0, 1fr));
  gap: 8px;
  width: min(100%, 560px);
  min-height: var(--h5-bottom-nav-height);
  margin: 0 auto;
  padding: 9px 14px calc(env(safe-area-inset-bottom, 0px) + 9px);
  border-top: 1px solid var(--h5-border);
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 -10px 28px rgba(61, 86, 150, 0.1);
  backdrop-filter: blur(16px);
}

.h5-bottom-tab {
  display: grid;
  place-items: center;
  min-width: 0;
  min-height: 42px;
  padding: 0 8px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--h5-muted);
  cursor: pointer;
}

.h5-bottom-tab span {
  overflow: hidden;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-bottom-tab.active {
  background: var(--h5-soft);
  color: var(--h5-blue);
}
</style>
