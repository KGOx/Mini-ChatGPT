<!-- ChatHeader.vue -->
<script setup>
import StreamingToggle from './StreamingToggle.vue'

const props = defineProps({
  selectedConversation: Object,
  models: Array,
  selectedModel: String,
  isStreamingMode: Boolean,
  isMobile: Boolean
})

const emit = defineEmits([
  'update:selectedModel',
  'update:isStreamingMode',
  'open-custom-instructions',
  'save-model'
])

// Déclenchement immédiat de la sauvegarde lors du changement de modèle
function handleModelChange() {
  emit('save-model')
}
</script>

<template>
  <!-- Interface adaptative : mobile et desktop complètement différentes -->
  <div v-if="isMobile" class="md:hidden flex items-center justify-between p-2 border-b bg-gray-50">
    <!-- Pattern slot pour injection de contenu spécialisé depuis le parent -->
    <slot name="mobile-menu-button" />

    <div class="flex items-center space-x-2">
      <!-- Truncature intelligente pour les noms de modèles longs -->
      <select
        :value="selectedModel"
        @change="$emit('update:selectedModel', $event.target.value); handleModelChange()"
        class="p-1 border rounded text-xs max-w-32 truncate">
        <option v-for="model in models" :key="model.id" :value="model.id">
          {{ model.name.length > 15 ? model.name.substring(0, 15) + '...' : model.name }}
        </option>
      </select>

      <!-- Icône settings avec SVG inline pour performance -->
      <button
        @click="$emit('open-custom-instructions')"
        class="p-2 text-gray-500 hover:text-gray-700 rounded-md transition-colors"
        title="Instructions personnalisées">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>
    </div>
  </div>

  <!-- Version desktop avec plus d'espace et fonctionnalités étendues -->
  <div v-else class="hidden md:flex p-4 border-b items-center justify-between">
    <!-- Affichage dynamique du titre ou fallback pour nouvelles conversations -->
    <strong>{{ selectedConversation?.title || 'Nouvelle conversation' }}</strong>

    <div class="flex items-center space-x-3">
      <!-- Délégation du toggle streaming à un composant spécialisé -->
      <StreamingToggle
        :modelValue="isStreamingMode"
        @update:modelValue="$emit('update:isStreamingMode', $event)" />

      <!-- Limite plus généreuse pour desktop (20 vs 15 caractères) -->
      <select
        :value="selectedModel"
        @change="$emit('update:selectedModel', $event.target.value); handleModelChange()"
        class="p-2 border rounded text-sm max-w-48 truncate">
        <option v-for="model in models" :key="model.id" :value="model.id">
          {{ model.name.length > 20 ? model.name.substring(0, 20) + '...' : model.name }}
        </option>
      </select>

      <!-- Effet hover plus sophistiqué pour desktop -->
      <button
        @click="$emit('open-custom-instructions')"
        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
        title="Instructions personnalisées">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>
    </div>
  </div>
</template>
