<!-- ConversationItem.vue -->
<script setup>
const props = defineProps({
  conversation: Object,
  isSelected: Boolean
})

const emit = defineEmits(['select', 'delete'])
</script>

<template>
  <!-- Pattern d'item avec révélation d'action au hover et gestion de l'état sélectionné -->
  <li :class="[
    'p-2 md:p-4 cursor-pointer flex justify-between items-center relative group transition-all duration-200 rounded-lg border-2',
    isSelected ? 'bg-blue-200' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50'
  ]">
    <!-- Zone de sélection principale avec gestion de l'overflow -->
    <div @click="$emit('select', conversation)" class="flex-1 min-w-0">
      <!-- Fallback intelligent pour conversations sans titre généré -->
      <div class="truncate">{{ conversation.title || 'Nouvelle conversation' }}</div>
      <div class="text-xs text-gray-500">{{ conversation.updated_at }}</div>
    </div>

    <!-- Bouton de suppression avec révélation au hover et stop de propagation -->
    <button @click.stop="$emit('delete', conversation)"
            class="absolute right-2 top-1/2 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-400 transition-all duration-200"
            title="Supprimer">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
      </svg>
    </button>
  </li>
</template>
