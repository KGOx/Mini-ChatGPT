<!-- CustomInstructionsModal.vue -->
<script setup>
import { ref} from 'vue'

const props = defineProps({
  show: Boolean,
  customInstructions: String,
  customResponseStyle: String,
  customCommands: String,
  enableForNewChats: Boolean,
  saving: Boolean
})

const emit = defineEmits([
  'close',
  'update:customInstructions',
  'update:customResponseStyle',
  'update:customCommands',
  'update:enableForNewChats',
  'save'
])

// État local pour navigation entre onglets sans impacter le parent
const activeTab = ref('instructions')
</script>

<template>
  <!-- Modal avec rendu conditionnel et gestion de z-index pour overlay -->
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Overlay cliquable pour fermeture intuitive -->
      <div class="fixed inset-0 transition-opacity" @click="$emit('close')">
        <div class="absolute inset-0 bg-black opacity-50"></div>
      </div>

      <!-- Corps de modal avec responsive et transitions -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
        <div class="bg-white px-6 pt-6 pb-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Instructions pour ChatGPT</h3>

          <!-- Système d'onglets avec état visuel actif/inactif -->
          <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
              <button @click="activeTab = 'instructions'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'instructions'
                          ? 'border-blue-500 text-blue-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]">
                Instructions
              </button>
              <button @click="activeTab = 'commands'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'commands'
                          ? 'border-blue-500 text-blue-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]">
                Commandes
              </button>
            </nav>
          </div>

          <!-- Onglet Instructions : personnalisation du comportement de l'IA -->
          <div v-if="activeTab === 'instructions'" class="space-y-6">
            <div>
              <div class="flex items-center mb-2">
                <label class="text-sm font-medium text-gray-700">Instructions personnalisées</label>
                <!-- Icône info pour affordance UX -->
                <svg class="w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>

              <p class="text-sm text-gray-600 mb-3">
                Que souhaiteriez-vous que ChatGPT sache sur vous pour fournir de meilleures réponses ?
              </p>

              <!-- Pattern v-model personnalisé avec émissions d'événements -->
              <textarea
                :value="customInstructions"
                @input="$emit('update:customInstructions', $event.target.value)"
                class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="6"
                maxlength="1500"
                placeholder="Je m'appelle Henri, j'ai 34 ans et je suis développeur informatique débutant">
              </textarea>

              <!-- Compteur de caractères réactif pour feedback utilisateur -->
              <div class="text-right text-sm text-gray-500 mt-1">
                {{ customInstructions.length }}/1500
              </div>
            </div>

            <!-- Section style de réponse pour personnaliser le ton de l'IA -->
            <div>
              <p class="text-sm text-gray-600 mb-3 font-medium">
                Comment souhaitez-vous que ChatGPT réponde ?
              </p>

              <textarea
                :value="customResponseStyle"
                @input="$emit('update:customResponseStyle', $event.target.value)"
                class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="6"
                maxlength="1500"
                placeholder="Répond moi de façon concice, de manière claire et dans la langue dans la quelle je te parle. Ne soit pas amical mais reste professionnel.">
              </textarea>

              <div class="text-right text-sm text-gray-500 mt-1">
                {{ customResponseStyle.length }}/1500
              </div>
            </div>

            <!-- Toggle switch pour activation globale des instructions -->
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700">Activer pour les nouvelles discussions</span>
              <button
                @click="$emit('update:enableForNewChats', !enableForNewChats)"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                  enableForNewChats ? 'bg-blue-600' : 'bg-gray-200'
                ]">
                <!-- Indicateur visuel mobile selon l'état -->
                <span :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                  enableForNewChats ? 'translate-x-6' : 'translate-x-1'
                ]"></span>
              </button>
            </div>
          </div>

          <!-- Onglet Commandes : système de raccourcis personnalisés -->
          <div v-if="activeTab === 'commands'" class="space-y-6">
            <div>
              <div class="flex items-center mb-2">
                <label class="text-sm font-medium text-gray-700">Créer des commandes personnalisées</label>
                <svg class="w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>

              <p class="text-sm text-gray-600 mb-3">
                Ici, vous pouvez définir vos propres commandes pour rendre votre expérience avec votre assistant plus rapide et personnalisée.
              </p>

              <!-- Documentation intégrée pour guider l'utilisateur -->
              <div class="bg-blue-50 p-3 rounded-md mb-4">
                <p class="text-sm text-blue-800 font-medium mb-2">Comment ça marche ?</p>
                <ul class="text-sm text-blue-700 space-y-1">
                  <li>• Commencez chaque commande par "/" suivi du nom de la commande</li>
                  <li>• Expliquez l'action souhaitée après le nom de la commande</li>
                  <li>• Les commandes permettent de simplifier les actions récurrentes</li>
                </ul>
              </div>

              <!-- Zone de texte plus large pour les commandes multiples -->
              <textarea
                :value="customCommands"
                @input="$emit('update:customCommands', $event.target.value)"
                class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="10"
                maxlength="2000"
                placeholder="/aide - Donner de l'aide sur les commandes&#10;/recherche - Effectuer une recherche&#10;/idees - Donner une liste d'idées sur un sujet">
              </textarea>

              <!-- Limite étendue pour les commandes (2000 vs 1500) -->
              <div class="text-right text-sm text-gray-500 mt-1">
                {{ customCommands.length }}/2000
              </div>
            </div>
          </div>
        </div>

        <!-- Footer avec actions primaires/secondaires et état de sauvegarde -->
        <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            Cancel
          </button>
          <!-- Bouton avec état disabled pendant sauvegarde pour éviter double-soumission -->
          <button
            @click="$emit('save')"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50">
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
