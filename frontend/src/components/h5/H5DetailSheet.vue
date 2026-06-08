<script setup lang="ts">
defineProps<{ visible: boolean; title: string }>()

const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <Transition name="h5-sheet">
      <div v-if="visible" class="h5-sheet-mask" @click.self="emit('close')">
        <section class="h5-detail-sheet" role="dialog" aria-modal="true" :aria-label="title">
          <header class="h5-sheet-header">
            <h2>{{ title }}</h2>
            <button type="button" aria-label="关闭" @click="emit('close')">×</button>
          </header>

          <div class="h5-sheet-body">
            <slot />
          </div>

          <footer v-if="$slots.footer" class="h5-sheet-footer">
            <slot name="footer" />
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.h5-sheet-mask {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-top: 24px;
  background: rgba(23, 35, 77, 0.38);
}

.h5-detail-sheet {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: min(100%, 560px);
  max-height: 88dvh;
  overflow: hidden;
  border-radius: var(--h5-radius) var(--h5-radius) 0 0;
  background: var(--h5-card);
  box-shadow: 0 -18px 36px rgba(23, 35, 77, 0.18);
}

.h5-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px 12px;
  border-bottom: 1px solid var(--h5-border);
}

.h5-sheet-header h2 {
  min-width: 0;
  margin: 0;
  overflow: hidden;
  color: var(--h5-ink);
  font-size: 17px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-sheet-header button {
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 50%;
  background: var(--h5-soft);
  color: var(--h5-muted);
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
}

.h5-sheet-body {
  min-height: 0;
  padding: 16px 18px;
  overflow-y: auto;
  background: linear-gradient(180deg, #fff, var(--h5-soft));
}

.h5-sheet-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 18px calc(env(safe-area-inset-bottom, 0px) + 14px);
  border-top: 1px solid var(--h5-border);
  background: var(--h5-card);
}

.h5-sheet-enter-active,
.h5-sheet-leave-active {
  transition: opacity 0.2s ease;
}

.h5-sheet-enter-active .h5-detail-sheet,
.h5-sheet-leave-active .h5-detail-sheet {
  transition: transform 0.2s ease;
}

.h5-sheet-enter-from,
.h5-sheet-leave-to {
  opacity: 0;
}

.h5-sheet-enter-from .h5-detail-sheet,
.h5-sheet-leave-to .h5-detail-sheet {
  transform: translateY(18px);
}
</style>
