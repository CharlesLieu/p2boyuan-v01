<script setup lang="ts">
defineProps<{
  tabs: Array<{ key: string; label: string }>
  active: string
}>()

const emit = defineEmits<{ change: [key: string] }>()
</script>

<template>
  <div class="h5-status-tabs" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      class="h5-status-tab"
      :class="{ active: tab.key === active }"
      type="button"
      role="tab"
      :aria-selected="tab.key === active"
      @click="emit('change', tab.key)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<style scoped>
.h5-status-tabs {
  display: flex;
  gap: 8px;
  width: 100%;
  min-width: 0;
  padding: 5px;
  overflow-x: auto;
  border: 1px solid var(--h5-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
  scrollbar-width: none;
}

.h5-status-tabs::-webkit-scrollbar {
  display: none;
}

.h5-status-tab {
  flex: 1 0 max-content;
  min-width: 72px;
  min-height: 36px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--h5-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
}

.h5-status-tab.active {
  background: var(--h5-blue);
  color: #fff;
  box-shadow: 0 8px 18px rgba(93, 120, 255, 0.25);
}
</style>
