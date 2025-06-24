<!-- StreamingToggle.vue -->
<script setup>
const props = defineProps({
  modelValue: Boolean
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex items-center space-x-2">
    <span class="text-sm text-gray-600">Streaming:</span>
    <!-- Toggle switch custom avec transition fluide entre états -->
    <button
      @click="$emit('update:modelValue', !modelValue)"
      :class="[
        'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
        modelValue ? 'bg-blue-600' : 'bg-gray-300'
      ]">
      <!-- Indicateur mobile avec animation de translation -->
      <span :class="[
        'inline-block h-3 w-3 transform rounded-full bg-white transition-transform',
        modelValue ? 'translate-x-5' : 'translate-x-1'
      ]"></span>
    </button>
  </div>
</template>
