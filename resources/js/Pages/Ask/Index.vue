<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue'
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
const selectedConversation = ref(props.selectedConversation)
const messages = ref(props.messages)
const conversations = ref(props.conversations)
const localSelectedModel = ref(props.selectedModel)
const loading = ref(false)
const newMessage = ref('')

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

const renderedMarkdown = computed(() =>
    props.flash.message ? md.render(props.flash.message) : ''
)
</script>


<template>
    <AppLayout title="Chat">
      <div class="flex h-screen">
        <!-- Liste des conversations -->
        <aside :class="[
        'fixed inset-y-0 left-0 z-40 bg-gray-100 border-r w-64 transform transition-transform duration-200 ease-in-out',
        showSidebar ? 'translate-x-0' : '-translate-x-full',
        'md:static md:translate-x-0 md:w-1/3 lg:w-1/4 md:block',
    ]"
    style="max-width: 100vw;">
    <div class="p-2 md:p-4 font-bold flex justify-between items-center">
      Conversations
        <button class="bg-blue-500 text-white px-1 py-1 md:px-2 md:py-1 rounded text-xs md:text-base" @click="newConversation">
                + Nouvelle conversation
        </button>
            <!-- Bouton de fermeture sur mobile -->
        <button class="md:hidden text-2xl ml-2" @click="showSidebar = false">×</button>
    </div>
        <ul>
            <li
                v-for="conv in conversations"
                :key="conv.id"
                :class="['p-2 md:p-4 cursor-pointer flex justify-between items-center relative group transition-all duration-200 rounded-lg border-2', selectedConversation && selectedConversation.id === conv.id ? 'bg-blue-200' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50']">

                <div @click="selectConversation(conv); showSidebar = false" class="flex-1">
                {{ conv.title || 'Nouvelle conversation' }}
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
        <select v-model="localSelectedModel" @change="saveModel" class="p-2 border rounded text-xs">
            <option v-for="model in models" :key="model.id" :value="model.id">
            {{ model.name }}
            </option>
        </select>
        </div>
        <!-- Barre du haut sur desktop -->
        <div class="hidden md:flex p-4 border-b items-center justify-between">
        <select v-model="localSelectedModel" @change="saveModel" class="p-2 border rounded">
            <option v-for="model in models" :key="model.id" :value="model.id">
            {{ model.name }}
            </option>
        </select>
        </div>

        <!-- Liste des messages -->
        <div class="flex-1 overflow-y-auto p-2 md:p-4 bg-white pb-36 md:pb-32">
            <div
                v-for="msg in messages"
                :key="msg.id"
                :class="msg.role === 'user' ? 'text-right' : 'text-left'"
                class="mb-2">
                <div
                    class="inline-block px-2 py-1 md:px-4 md:py-2 rounded-lg"
                    :class="msg.role === 'user' ? 'bg-blue-100' : 'bg-gray-200'"
                    v-html="renderMarkdown(msg.content)">
                </div>
            </div>
        <div v-if="loading" class="text-center text-gray-500 mt-4">Chargement...</div>
        </div>

        <!-- Saisie du message -->
        <<div class="fixed bottom-0 left-0 right-0 md:left-64 lg:left-1/4 bg-gray-50 border-t p-2 md:p-4 z-30">
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
    </AppLayout>
  </template>


