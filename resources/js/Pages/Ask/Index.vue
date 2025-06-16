<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css'

// Markdown-it + highlight.js config (reste identique)
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

// Props Inertia
const props = defineProps({
    conversations: Array,
    selectedConversation: Object,
    messages: Array,
    models: Array,
    selectedModel: String,
    flash: Object
})

const showSidebar = ref(false)
const sidebarCollapsed = ref(true)
const isMobile = ref(false)
const selectedConversation = ref(props.selectedConversation)
const messages = ref(props.messages)
const conversations = ref(props.conversations)
const localSelectedModel = ref(props.selectedModel)
const loading = ref(false)
const newMessage = ref('')
const showCustomInstructions = ref(false)
const customInstructions = ref(props.auth?.user?.custom_instructions || '')
const customResponseStyle = ref(props.auth?.user?.custom_response_style || '')
const enableForNewChats = ref(props.auth?.user?.enable_custom_instructions ?? true)
const customCommands = ref('')
const saving = ref(false)

// Détecter la taille d'écran au montage
onMounted(() => {
  const checkMobile = () => {
    isMobile.value = window.innerWidth < 768
  }

  checkMobile()
  window.addEventListener('resize', checkMobile)

  // Nettoyage
  return () => window.removeEventListener('resize', checkMobile)
})

// Variables pour les onglets
const activeTab = ref('instructions')

function openCustomInstructions() {
    fetch(route('profile.get-custom-instructions'))
        .then(response => response.json())
        .then(data => {
            customInstructions.value = data.custom_instructions
            customResponseStyle.value = data.custom_response_style
            enableForNewChats.value = data.enable_custom_instructions
            customCommands.value = data.custom_commands || ''

            console.log('Données chargées:', data)
        })
        .catch(error => {
            console.error('Erreur chargement:', error)
            // Valeurs par défaut
            customInstructions.value = ''
            customResponseStyle.value = ''
            enableForNewChats.value = true
            customCommands.value = ''
        })

    activeTab.value = 'instructions'
    showCustomInstructions.value = true
}

// Sélection d'une conversation avec Inertia
function selectConversation(conv) {
    loading.value = true

    // Utiliser router.visit au lieu de fetch
    router.visit(route('conversations.messages', conv.id), {
        preserveState: false, // Important : recharger l'état
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages
            localSelectedModel.value = page.props.selectedModel
        },
        onFinish: () => loading.value = false
    })
    }

// Nouvelle conversation avec Inertia
function newConversation() {
    loading.value = true
    router.post(route('conversations.store'), {}, {
        only: ['conversations', 'selectedConversation', 'messages'],
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages || []
            // Supprime complètement la logique de rappel automatique

            if (page.props.conversations) {
                conversations.value = page.props.conversations
            }
        },
        onFinish: () => loading.value = false
    })
}

// Envoi d'un message avec Inertia
function sendMessage() {
    if (!newMessage.value) return

    // Si pas de conversation sélectionnée, arrêter
    if (!selectedConversation.value) {
        alert('Aucune conversation sélectionnée')
        return
    }

    loading.value = true

    router.post(route('messages.store', selectedConversation.value.id), {
        content: newMessage.value,
        model: localSelectedModel.value
    }, {
        only: ['messages', 'selectedConversation', 'conversations'],
        onSuccess: (page) => {
            messages.value = page.props.messages
            // On met a jour le titre s'il a changé
            if (page.props.selectedConversation) {
                selectedConversation.value = page.props.selectedConversation
                if (page.props.conversations) {
                    conversations.value = page.props.conversations
                }
            }
            newMessage.value = ''
        },
        onFinish: () => loading.value = false
    })
}

// Changement de modèle avec Inertia
function saveModel() {
    router.patch(route('conversations.updateModel', selectedConversation.value.id), {
        model: localSelectedModel.value
    }, {
        only: [], // Pas besoin de recharger des données
        preserveState: true
    })
}

function deleteConversation(conv) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
        loading.value = true

        router.delete(route('conversations.destroy', conv.id), {
            onSuccess: (page) => {
                // Mettre à jour toutes les données localement
                conversations.value = page.props.conversations
                selectedConversation.value = page.props.selectedConversation
                messages.value = page.props.messages
                localSelectedModel.value = page.props.selectedModel

                // Log pour vérification
                console.log('Conversation supprimée, nouvelle liste:', conversations.value.length)
            },
            onError: (errors) => {
                console.error('Erreur suppression:', errors)
            },
            onFinish: () => loading.value = false
        })
    }
}


// Fonction pour le rendu markdown
function renderMarkdown(content) {
    return md.render(content)
}

// Ancien code pour compatibilité (si besoin)
const form = useForm({
    message: '',
    model: props.selectedModel
})

const processing = ref(false)

function submit() {
    processing.value = true
    form.post(route('ask.post'), {
        onSuccess: () => {
            form.reset('message')
        },
        onFinish: () => processing.value = false
    })
}

function saveCustomInstructions() {
    saving.value = true

    router.post(route('profile.custom-instructions'), {
        custom_instructions: customInstructions.value,
        custom_response_style: customResponseStyle.value,
        enable_custom_instructions: enableForNewChats.value,
        custom_commands: customCommands.value,
    }, {
        onSuccess: () => {
            showCustomInstructions.value = false
            // Optionnel : afficher un message de succès
            console.log('Instructions et commandes sauvegardées avec succès')
        },
        onError: (errors) => {
            console.error('Erreur lors de la sauvegarde:', errors)
        },
        onFinish: () => saving.value = false
    })
}

const renderedMarkdown = computed(() =>
    props.flash.message ? md.render(props.flash.message) : ''
)
</script>


<template>
    <AppLayout title="Chat">
      <div class="flex h-screen overflow-hidden">
        <!-- Liste des conversations -->
        <aside @mouseenter="sidebarCollapsed = false"
                @mouseleave="sidebarCollapsed = true"
                :class="[
                    'fixed inset-y-0 left-0 z-40 bg-gray-100 border-r transform transition-all duration-300 ease-in-out flex flex-col',
                    // Mobile
                    'w-64', showSidebar ? 'translate-x-0' : '-translate-x-full',
                    // Desktop
                    'md:static md:translate-x-0',
                    sidebarCollapsed ? 'md:w-16' : 'md:w-80',
                ]"
                style="max-width: 100vw;">

                <!-- Header sidebar -->
                <div class="p-2 md:p-4 font-bold flex justify-between items-center border-b flex-shrink-0">
                    <!-- Titre visible seulement si déplié -->
                    <span :class="{ 'md:hidden': sidebarCollapsed, 'md:block': !sidebarCollapsed }">
                    Conversations
                    </span>

                    <!-- Icône collapsed -->
                    <div v-if="sidebarCollapsed" class="hidden md:flex w-full justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    </div>

                    <!-- Bouton fermeture mobile -->
                    <button class="md:hidden text-2xl" @click="showSidebar = false">×</button>
                </div>

                <!-- Liste conversations (scrollable) -->
                <div class="flex-1 overflow-y-auto min-h-0">
                    <!-- Mode collapsed : Montrer seulement des points ou icônes -->
                    <div v-if="sidebarCollapsed" class="hidden md:block p-2 space-y-3">
                    <div
                        v-for="(conv, index) in conversations.slice(0, 6)"
                        :key="conv.id"
                        class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 cursor-pointer hover:bg-blue-200 transition-colors"
                        :title="conv.title || 'Nouvelle conversation'"
                        @click="selectConversation(conv)">
                        {{ index + 1 }}
                    </div>

                    <!-- Indicateur s'il y a plus de conversations -->
                    <div v-if="conversations.length > 6"
                        class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-xs text-gray-600">
                        +{{ conversations.length - 6 }}
                    </div>
                    </div>

                    <!-- Mode normal : Liste complète -->
                    <ul :class="{ 'md:hidden': sidebarCollapsed, 'md:block': !sidebarCollapsed }">
                    <li v-for="conv in conversations" :key="conv.id"
                        :class="['p-2 md:p-4 cursor-pointer flex justify-between items-center relative group transition-all duration-200 rounded-lg border-2',
                        selectedConversation && selectedConversation.id === conv.id ? 'bg-blue-200' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50']">

                        <div @click="selectConversation(conv); showSidebar = false" class="flex-1 min-w-0">
                        <div class="truncate">{{ conv.title || 'Nouvelle conversation' }}</div>
                        <div class="text-xs text-gray-500">{{ conv.updated_at }}</div>
                        </div>

                        <button
                            @click.stop="deleteConversation(conv)"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-400 transition-all duration-200"
                            title="Supprimer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </li>
                    </ul>
                </div>

                <!-- Bouton Nouvelle discussion -->
                <div class="border-t bg-gray-50 p-3 flex-shrink-0">
                    <button
                    @click="newConversation"
                    :class="[
                        'w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors font-medium',
                        sidebarCollapsed ? 'md:p-2 md:rounded-full' : 'text-sm md:text-base'
                    ]">
                    <span v-if="!sidebarCollapsed || isMobile">
                    + Nouvelle conversation
                    </span>
                    <svg v-else class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    </button>
                </div>
        </aside>

    <!-- Overlay noir pour fermer la sidebar sur mobile -->
    <div
        v-if="showSidebar"
        class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"
        @click="showSidebar = false"
    ></div>

    <!-- Main chat -->
    <main class="flex-1 flex flex-col h-full ml-0 md:ml-0">
        <!-- Bouton hamburger sur mobile -->
        <div class="md:hidden flex items-center justify-between p-2 border-b bg-gray-50">
        <button @click="showSidebar = true" class="text-2xl">☰</button>

        <div class="flex items-center space-x-2">
            <!-- Sélecteur plus petit -->
            <select v-model="localSelectedModel" @change="saveModel" class="p-1 border rounded text-xs max-w-32 truncate">
            <option v-for="model in models" :key="model.id" :value="model.id">
                {{ model.name.length > 15 ? model.name.substring(0, 15) + '...' : model.name }}
            </option>
            </select>

            <!-- Bouton paramètres mobile -->
            <button
            @click="openCustomInstructions"
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
        <!-- Barre du haut sur desktop -->
        <div class="hidden md:flex p-4 border-b items-center justify-between">
            <strong>{{ selectedConversation?.title || 'Nouvelle conversation' }}</strong>
            <div class="flex items-center space-x-3">
                <!-- Sélecteur plus compact -->
                <select v-model="localSelectedModel" @change="saveModel" class="p-2 border rounded text-sm max-w-48 truncate">
                <option v-for="model in models" :key="model.id" :value="model.id">
                    {{ model.name.length > 20 ? model.name.substring(0, 20) + '...' : model.name }}
                </option>
                </select>

                <!-- Bouton paramètres -->
                <button
                @click="openCustomInstructions"
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

        <!-- Liste des messages -->
        <div class="flex-1 overflow-y-auto p-2 md:p-4 bg-white"
            :class="{ 'pb-36 md:pb-32': true }">
        <div
            v-for="msg in messages"
            :key="msg.id"
            :class="msg.role === 'user' ? 'text-right' : 'text-left'"
            class="mb-2">
            <div
            class="inline-block px-2 py-1 md:px-4 md:py-2 rounded-lg max-w-prose"
            :class="msg.role === 'user' ? 'bg-blue-100' : 'bg-gray-200'"
            v-html="renderMarkdown(msg.content)">
            </div>
        </div>
        <div v-if="loading" class="text-center text-gray-500 mt-4">Chargement...</div>
        </div>

        <!-- Saisie du message -->
        <div class="fixed bottom-0 left-0 right-0 bg-gray-50 border-t p-2 md:p-4 z-30 transition-all duration-300"
            :class="{
            'md:ml-16': sidebarCollapsed,
            'md:ml-80': !sidebarCollapsed
            }">
        <form @submit.prevent="sendMessage" class="flex flex-col md:flex-row gap-2 max-w-4xl mx-auto">
            <textarea
            v-model="newMessage"
            rows="2"
            class="flex-1 p-2 border rounded resize-none"
            placeholder="Écris un message..."
            :disabled="loading"
            @keydown.enter.prevent="sendMessage">
            </textarea>
            <button
            type="submit"
            class="bg-blue-600 text-white px-2 py-1 md:px-4 md:py-2 rounded"
            :disabled="loading || !newMessage">
            Envoyer
            </button>
        </form>
        </div>
    </main>
    </div>

    <!-- Modal Instructions Personnalisées -->
    <div v-if="showCustomInstructions" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <!-- Overlay -->
    <div class="fixed inset-0 transition-opacity" @click="showCustomInstructions = false">
      <div class="absolute inset-0 bg-black opacity-50"></div>
    </div>

    <!-- Modal -->
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
      <div class="bg-white px-6 pt-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Customize ChatGPT</h3>

        <!-- Onglets -->
        <div class="border-b border-gray-200 mb-6">
          <nav class="-mb-px flex space-x-8">
            <button
              @click="activeTab = 'instructions'"
              :class="[
                'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'instructions'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]">
              Instructions
            </button>
            <button
              @click="activeTab = 'commands'"
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

        <!-- Contenu onglet Instructions -->
        <div v-if="activeTab === 'instructions'" class="space-y-6">
          <!-- Custom Instructions -->
          <div>
            <div class="flex items-center mb-2">
              <label class="text-sm font-medium text-gray-700">Custom Instructions</label>
              <svg class="w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>

            <p class="text-sm text-gray-600 mb-3">
              What would you like ChatGPT to know about you to provide better responses?
            </p>

            <textarea
              v-model="customInstructions"
              class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              rows="6"
              maxlength="1500"
              placeholder="Je m'appelle Thibault et je parle français.&#10;&#10;Je suis informaticien et plus particulièrement programmeur web.&#10;J'utilise les technologies suivantes :&#10;PHP, JavaScript, MySQL, Tailwind CSS, TypeScript, VueJS, Laravel.">
            </textarea>

            <div class="text-right text-sm text-gray-500 mt-1">
              {{ customInstructions.length }}/1500
            </div>
          </div>

          <!-- Response Style -->
          <div>
            <p class="text-sm text-gray-600 mb-3 font-medium">
              How would you like ChatGPT to respond?
            </p>

            <textarea
              v-model="customResponseStyle"
              class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              rows="6"
              maxlength="1500"
              placeholder="Réponds dans la langue dans laquelle je te parle. Tu as choisi de t'appeler Aria.&#10;Veilles à l'orthographe. Soigne ton style. Utilise des synonymes. Ne commence pas tes phrases par 'en tant que modèle de langage'. Si besoin, demande des précisions. N'invente rien si je ne le demande pas explicitement.">
            </textarea>

            <div class="text-right text-sm text-gray-500 mt-1">
              {{ customResponseStyle.length }}/1500
            </div>
          </div>

          <!-- Enable for new chats -->
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700">Enable for new chats</span>
            <button
              @click="enableForNewChats = !enableForNewChats"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                enableForNewChats ? 'bg-blue-600' : 'bg-gray-200'
              ]">
              <span :class="[
                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                enableForNewChats ? 'translate-x-6' : 'translate-x-1'
              ]"></span>
            </button>
          </div>
        </div>

        <!-- Contenu onglet Commandes -->
        <div v-if="activeTab === 'commands'" class="space-y-6">
          <div>
            <div class="flex items-center mb-2">
              <label class="text-sm font-medium text-gray-700">Créer des commandes personnalisées</label>
              <svg class="w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>

            <p class="text-sm text-gray-600 mb-3">
              Ici, vous pouvez définir vos propres commandes pour rendre votre expérience avec votre assistant plus rapide et personnalisée. Les commandes vous permettent de simplifier les actions récurrentes ou d'accéder rapidement à l'information ou aux actions désirées.
            </p>

            <div class="bg-blue-50 p-3 rounded-md mb-4">
              <p class="text-sm text-blue-800 font-medium mb-2">Comment ça marche ?</p>
              <ul class="text-sm text-blue-700 space-y-1">
                <li>• Commencez chaque commande par "/" suivi du nom de la commande</li>
                <li>• Expliquez comment l'action souhaitée après le nom de la commande</li>
                <li>• Les commandes vous permettent de simplifier les actions récurrentes ou d'accéder facilement aux informations désirées ou un besoin ou tâche récurrente</li>
              </ul>
            </div>

            <div class="bg-gray-50 p-3 rounded-md mb-4">
              <p class="text-sm font-medium text-gray-700 mb-2">Exemple : Créez une commande "/resume" pour demander un résumé de texte précédent. Incluez dans la description : "Résume le texte fourni en points clés"</p>
            </div>

            <textarea
              v-model="customCommands"
              class="w-full p-3 border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              rows="10"
              maxlength="2000"
              placeholder="/aide - Donner de l'aide sur les commandes&#10;/recherche - Effectuer une recherche avec une query&#10;/idees - Donner une liste d'idées sur un sujet&#10;/reflexion - Mode réflexion : l'assistant découpe sa réponse en 2 parties. Une partie réflexion pour expliquer à haute voix son processus de pensée. Ensuite penser à ses propres réflexions pour générer une réponse de meilleure qualité.&#10;/amélioration du style - Améliorer le texte fourni. améliorer les phrases, retrouver les tournures de phrases, organiser l'information efficacement avec des titres de différents niveaux, des listes et des tableaux. Le texte doit être agréable à lire">
            </textarea>

            <div class="text-right text-sm text-gray-500 mt-1">
              {{ customCommands.length }}/2000
            </div>
          </div>
        </div>
      </div>

      <!-- Buttons -->
      <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3">
        <button
          @click="showCustomInstructions = false"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
          Cancel
        </button>
        <button
          @click="saveCustomInstructions"
          :disabled="saving"
          class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
        </div>
        </div>
    </div>
    </div>
    </AppLayout>
  </template>


