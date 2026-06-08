<script setup lang="ts">
defineProps<{
  code: string
  title: string
  subtitle: string
  amount: string
  status: string
  image?: string | null
}>()
</script>

<template>
  <article class="h5-order-card h5-order-main" :class="{ 'no-media': !image }">
    <img v-if="image" class="h5-order-image" :src="image" :alt="title" />

    <div class="h5-order-content">
      <div class="h5-order-topline">
        <span>{{ code }}</span>
        <strong>{{ status }}</strong>
      </div>

      <h3>{{ title }}</h3>
      <p>{{ subtitle }}</p>

      <div class="h5-order-footer">
        <strong class="h5-order-amount">{{ amount }}</strong>
        <div v-if="$slots.default" class="h5-order-actions">
          <slot />
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.h5-order-card {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr);
  gap: 14px;
  min-width: 0;
  padding: 14px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.h5-order-main.no-media {
  grid-template-columns: minmax(0, 1fr);
}

.h5-order-image {
  width: 92px;
  height: 108px;
  align-self: stretch;
  border-radius: 18px;
  background: var(--h5-soft);
  object-fit: contain;
}

.h5-order-content {
  display: grid;
  min-width: 0;
  align-content: start;
  gap: 8px;
}

.h5-order-topline,
.h5-order-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-width: 0;
}

.h5-order-topline span {
  min-width: 0;
  overflow: hidden;
  color: var(--h5-muted);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-order-topline strong {
  flex: 0 0 auto;
  padding: 4px 9px;
  border-radius: 999px;
  background: rgba(240, 122, 74, 0.12);
  color: var(--h5-orange);
  font-size: 12px;
  line-height: 1.2;
}

.h5-order-card h3,
.h5-order-card p {
  margin: 0;
}

.h5-order-card h3 {
  overflow: hidden;
  color: var(--h5-ink);
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-order-card p {
  display: -webkit-box;
  overflow: hidden;
  color: var(--h5-muted);
  font-size: 12px;
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.h5-order-footer {
  margin-top: 4px;
}

.h5-order-amount {
  overflow-wrap: anywhere;
  color: var(--h5-blue);
  font-size: 18px;
  font-weight: 900;
  line-height: 1.2;
}

.h5-order-actions {
  flex: 0 0 auto;
  display: flex;
  gap: 8px;
  align-items: center;
}
</style>
