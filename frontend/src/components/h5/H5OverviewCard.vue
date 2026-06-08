<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
  stats: Array<{ label: string; value: string | number }>
}>()
</script>

<template>
  <article class="h5-overview-card">
    <div class="h5-overview-head">
      <div>
        <p>{{ eyebrow }}</p>
        <h2>{{ title }}</h2>
      </div>
      <div v-if="$slots.action" class="h5-overview-action">
        <slot name="action" />
      </div>
    </div>

    <dl class="h5-overview-stats">
      <div v-for="item in stats" :key="item.label" class="h5-overview-stat">
        <dt>{{ item.label }}</dt>
        <dd>{{ item.value }}</dd>
      </div>
    </dl>
  </article>
</template>

<style scoped>
.h5-overview-card {
  display: grid;
  gap: 18px;
  padding: 20px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.h5-overview-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.h5-overview-head p,
.h5-overview-head h2,
.h5-overview-stats,
.h5-overview-stat dd {
  margin: 0;
}

.h5-overview-head p {
  color: var(--h5-blue);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.4;
}

.h5-overview-head h2 {
  margin-top: 6px;
  color: var(--h5-ink);
  font-size: 24px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.25;
}

.h5-overview-action {
  flex: 0 0 auto;
}

.h5-overview-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(86px, 1fr));
  gap: 10px;
}

.h5-overview-stat {
  min-width: 0;
  padding: 12px;
  border-radius: 16px;
  background: var(--h5-soft);
}

.h5-overview-stat dt {
  overflow: hidden;
  color: var(--h5-muted);
  font-size: 12px;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-overview-stat dd {
  margin-top: 7px;
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-size: 20px;
  font-weight: 900;
  line-height: 1.15;
}
</style>
