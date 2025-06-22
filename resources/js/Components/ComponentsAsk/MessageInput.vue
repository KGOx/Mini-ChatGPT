<script setup>
const props = defineProps({
  newMessage: String,
  loading: Boolean,
  isStreaming: Boolean,
  sidebarCollapsed: Boolean,
  disabled: Boolean
})

const emit = defineEmits([
  'update:newMessage',
  'send-message'
])

function handleSubmit() {
  if (!props.newMessage.trim() || props.loading || props.isStreaming) return
  emit('send-message')
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSubmit()
  }
}
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 bg-gray-50 border-t p-2 md:p-4 z-30 transition-all duration-300"
       :class="{
         'md:ml-16': sidebarCollapsed,
         'md:ml-80': !sidebarCollapsed
       }">
    <form @submit.prevent="handleSubmit" class="flex flex-col md:flex-row gap-2 max-w-4xl mx-auto">
      <textarea
        :value="newMessage"
        @input="$emit('update:newMessage', $event.target.value)"
        @keydown="handleKeydown"
        rows="2"
        class="flex-1 p-2 border rounded resize-none"
        placeholder="Écris un message..."
        :disabled="loading || disabled" />

      <button type="submit"
              class="bg-blue-600 text-white px-2 py-1 md:px-4 md:py-2 rounded"
              :disabled="(loading || isStreaming) || !newMessage">
        <span v-if="isStreaming">Streaming...</span>
        <span v-else-if="loading">Envoi...</span>
        <span v-else>Envoyer</span>
      </button>
    </form>
  </div>
</template>
