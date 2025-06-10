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

const selectedConversation = ref(props.selectedConversation)
const messages = ref(props.messages)
const localSelectedModel = ref(props.selectedModel)
const loading = ref(false)
const newMessage = ref('')

// Sélection d'une conversation avec Inertia
function selectConversation(conv) {
    // Ne pas changer d'URL, juste mettre à jour les données localement
    selectedConversation.value = conv
    messages.value = conv.messages || []
    localSelectedModel.value = conv.model || props.selectedModel

    // Si tu veux charger les messages depuis le serveur :
    loading.value = true
    fetch(route('conversations.messages', conv.id))
        .then(response => response.json())
        .then(data => {
            messages.value = data.messages
        })
        .finally(() => loading.value = false)
    }

// Nouvelle conversation avec Inertia
function newConversation() {
    loading.value = true
    router.post(route('conversations.store'), {}, {
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages || []
            // Supprime complètement la logique de rappel automatique
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
        only: ['messages'],
        onSuccess: (page) => {
            messages.value = page.props.messages
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
        router.delete(route('conversations.destroy', conv.id), {
            onSuccess: () => {
                // Recharger la page pour mettre à jour la liste
                router.reload()
            }
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
        <aside class="w-1/4 bg-gray-100 border-r overflow-y-auto">
          <div class="p-4 font-bold">Conversations
            <button class="m-4 bg-blue-500 text-white px-4 py-2 rounded" @click="newConversation">
                    + Nouvelle conversation
            </button>
          </div>
          <ul>
            <li
                v-for="conv in conversations"
                :key="conv.id"
                :class="['p-4 cursor-pointer flex justify-between items-center', selectedConversation && selectedConversation.id === conv.id ? 'bg-blue-200' : '']">
                <div @click="selectConversation(conv)" class="flex-1">
                    {{ conv.title || 'Nouvelle conversation' }}
                    <div class="text-xs text-gray-500">{{ conv.updated_at }}</div>
                </div>
                <button
                    @click.stop="deleteConversation(conv)"
                    class="text-red-500 hover:text-red-700 ml-2"
                    title="Supprimer">
                    ✕
                </button>
            </li>

          </ul>
        </aside>

        <!-- Affichage de la conversation sélectionnée -->
        <main class="flex-1 flex flex-col">
          <div class="p-4 border-b flex items-center justify-between">
            <div>
              <strong>{{ selectedConversation?.title || 'Nouvelle conversation' }}</strong>
            </div>
            <select v-model="localSelectedModel" @change="saveModel" class="p-2 border rounded">
              <option v-for="model in models" :key="model.id" :value="model.id">
                {{ model.name }}
              </option>
            </select>
          </div>

          <!-- Liste des messages -->
          <div class="flex-1 overflow-y-auto p-4 bg-white">
            <div
              v-for="msg in messages"
              :key="msg.id"
              :class="msg.role === 'user' ? 'text-right' : 'text-left'"
              class="mb-2"
            >
              <div
                class="inline-block px-4 py-2 rounded-lg"
                :class="msg.role === 'user' ? 'bg-blue-100' : 'bg-gray-200'"
                v-html="renderMarkdown(msg.content)"
              ></div>
            </div>
            <div v-if="loading" class="text-center text-gray-500 mt-4">Chargement...</div>
          </div>

          <!-- Saisie du message -->
          <div class="p-4 border-t bg-gray-50">
            <form @submit.prevent="sendMessage" class="flex gap-2">
              <textarea v-model="newMessage" rows="2" class="flex-1 p-2 border rounded" placeholder="Écris un message..." :disabled="loading"></textarea>
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded" :disabled="loading || !newMessage">
                Envoyer
              </button>
            </form>
          </div>
        </main>
      </div>
    </AppLayout>
  </template>


