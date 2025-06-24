<!-- MessagesList.vue -->
<script setup>
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css'
import { ref, nextTick } from 'vue'

const props = defineProps({
  messages: Array,
  loading: Boolean,
  isStreaming: Boolean
})

const messagesContainer = ref()

// Configuration Markdown avec syntax highlighting pour le code dans les réponses IA
const md = new MarkdownIt({
  html: true,
  highlight: function (str, lang) {
    if (lang && hljs.getLanguage(lang)) {
      try {
        return `<pre class="hljs"><code>${hljs.highlight(str, { language: lang }).value}</code></pre>`
      } catch (__) { }
    }
    return `<pre class="hljs"><code>${md.utils.escapeHtml(str)}</code></pre>`
  }
})

function renderMarkdown(content) {
  return md.render(content)
}

// Pattern defineExpose : expose des méthodes internes au composant parent
defineExpose({
  scrollToBottom: async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  }
})
</script>

<template>
  <!-- Container avec padding-bottom fixe pour éviter chevauchement avec MessageInput -->
  <div ref="messagesContainer" class="flex-1 overflow-y-auto p-2 md:p-4 bg-white pb-36 md:pb-32">
    <div v-for="msg in messages" :key="msg.id"
         :class="msg.role === 'user' ? 'text-right' : 'text-left'"
         class="mb-2">
      <!-- Distinction visuelle user (droite, bleu) vs assistant (gauche, gris) -->
      <div class="inline-block px-2 py-1 md:px-4 md:py-2 rounded-lg max-w-prose"
           :class="msg.role === 'user' ? 'bg-blue-100' : 'bg-gray-200'"
           v-html="renderMarkdown(msg.content)">
      </div>
    </div>

    <!-- Feedback visuel pendant les opérations asynchrones -->
    <div v-if="loading || isStreaming" class="text-center text-gray-500 mt-4">
      <span v-if="isStreaming">Assistant en cours de réponse...</span>
      <span v-else>Chargement...</span>
    </div>
  </div>
</template>
