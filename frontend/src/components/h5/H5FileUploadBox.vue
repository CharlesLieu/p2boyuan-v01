<script setup lang="ts">
defineProps<{
  label: string
  description?: string
  fileName?: string | null
  previewable?: boolean
}>()

const emit = defineEmits<{ upload: []; preview: [] }>()
</script>

<template>
  <section class="h5-upload-box">
    <div class="h5-upload-copy">
      <strong>{{ label }}</strong>
      <p v-if="description">{{ description }}</p>
      <span v-if="fileName">{{ fileName }}</span>
    </div>

    <div class="h5-upload-actions">
      <button type="button" class="h5-upload-button primary" @click="emit('upload')">
        {{ fileName ? '重新上传' : '上传文件' }}
      </button>
      <button
        v-if="fileName && previewable"
        type="button"
        class="h5-upload-button"
        @click="emit('preview')"
      >
        预览
      </button>
    </div>
  </section>
</template>

<style scoped>
.h5-upload-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-width: 0;
  padding: 16px;
  border: 1px dashed var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.h5-upload-copy {
  display: grid;
  min-width: 0;
  gap: 5px;
}

.h5-upload-copy strong,
.h5-upload-copy p,
.h5-upload-copy span {
  min-width: 0;
}

.h5-upload-copy strong {
  color: var(--h5-ink);
  font-size: 15px;
  font-weight: 900;
  line-height: 1.35;
}

.h5-upload-copy p {
  margin: 0;
  color: var(--h5-muted);
  font-size: 12px;
  line-height: 1.5;
}

.h5-upload-copy span {
  overflow: hidden;
  color: var(--h5-blue);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.h5-upload-actions {
  flex: 0 0 auto;
  display: flex;
  gap: 8px;
  align-items: center;
}

.h5-upload-button {
  min-height: 34px;
  padding: 0 13px;
  border: 1px solid var(--h5-border);
  border-radius: 999px;
  background: var(--h5-soft);
  color: var(--h5-blue);
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
  cursor: pointer;
}

.h5-upload-button.primary {
  border-color: var(--h5-blue);
  background: var(--h5-blue);
  color: #fff;
}

@media (max-width: 420px) {
  .h5-upload-box {
    align-items: stretch;
    flex-direction: column;
  }

  .h5-upload-actions {
    justify-content: flex-end;
  }
}
</style>
