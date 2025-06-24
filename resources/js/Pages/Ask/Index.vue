<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
// Architecture modulaire : composants spécialisés pour chaque zone fonctionnelle
import ChatSidebar from '@/Components/ComponentsAsk/ChatSidebar.vue'
import ChatHeader from '@/Components/ComponentsAsk/ChatHeader.vue'
import MessagesList from '@/Components/ComponentsAsk/MessagesList.vue'
import MessageInput from '@/Components/ComponentsAsk/MessageInput.vue'
import CustomInstructionsModal from '@/Components/ComponentsAsk/CustomInstructionsModal.vue'

const props = defineProps({
    conversations: Array,
    selectedConversation: Object,
    messages: Array,
    models: Array,
    selectedModel: String,
    auth: Object, // Nécessaire pour user_id dans les messages streaming
    flash: Object
})

// État local réactif pour gérer l'interface sans dépendre uniquement des props Inertia
const showSidebar = ref(false)
const sidebarCollapsed = ref(true)
const isMobile = ref(false)
const selectedConversation = ref(props.selectedConversation)
const messages = ref(props.messages)
const conversations = ref(props.conversations)
const localSelectedModel = ref(props.selectedModel)
const loading = ref(false)
const newMessage = ref('')
// Double mode : streaming temps réel vs classique avec rechargement
const isStreamingMode = ref(true)
const isStreaming = ref(false)

// État pour modal instructions personnalisées
const showCustomInstructions = ref(false)
const customInstructions = ref('')
const customResponseStyle = ref('')
const enableForNewChats = ref(true)
const customCommands = ref('')
const saving = ref(false)

// Référence pour contrôler le scroll depuis le parent
const messagesListRef = ref()

// Détection responsive pour adapter l'interface mobile/desktop
onMounted(() => {
    const checkMobile = () => {
        isMobile.value = window.innerWidth < 768
    }

    checkMobile()
    window.addEventListener('resize', checkMobile)

    return () => window.removeEventListener('resize', checkMobile)
})

// Délégation du scroll au composant MessagesList via ref
const scrollToBottom = async () => {
    await nextTick()
    if (messagesListRef.value?.scrollToBottom) {
        messagesListRef.value.scrollToBottom()
    }
}

// Rechargement partiel pour maintenir l'état local tout en synchronisant les données serveur
const reloadConversations = () => {
    router.reload({
        only: ['conversations', 'selectedConversation'],
        preserveState: true
    })
}

// Streaming SSE : implémentation native sans dépendance externe
function sendMessageStream() {
    if (!newMessage.value.trim() || isStreaming.value) return

    if (!selectedConversation.value?.id) {
        console.error('Aucune conversation sélectionnée')
        return
    }

    isStreaming.value = true

    // Ajout optimiste : affichage immédiat avant validation serveur
    const userMessage = {
        id: Date.now(), // ID temporaire pour l'affichage
        user_id: props.auth?.user?.id,
        role: 'user',
        content: newMessage.value,
        created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)

    // Message assistant vide pour recevoir le contenu en streaming
    const assistantMessage = {
        id: Date.now() + 1,
        user_id: null, // Messages assistant sans user_id
        role: 'assistant',
        content: '', // Rempli progressivement via SSE
        created_at: new Date().toISOString(),
    }
    messages.value.push(assistantMessage)

    scrollToBottom()

    const messageToSend = newMessage.value
    newMessage.value = '' // Nettoyage immédiat pour UX fluide

    // Connexion SSE native avec ReadableStream
    fetch(`/conversations/${selectedConversation.value.id}/stream`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'text/event-stream',
        },
        body: JSON.stringify({ content: messageToSend })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)

        const reader = response.body.getReader()
        const decoder = new TextDecoder()

        // Traitement récursif du stream pour gérer la fragmentation des chunks
        function processStream() {
            return reader.read().then(({ done, value }) => {
                if (done) {
                    isStreaming.value = false
                    reloadConversations()
                    return
                }

                const chunk = decoder.decode(value, { stream: true })
                const lines = chunk.split('\n')

                // Parsing des événements SSE ligne par ligne
                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6).trim()

                        // Signal de fin de stream
                        if (data === '[DONE]') {
                            isStreaming.value = false
                            reloadConversations()
                            return
                        }

                        if (data) {
                            try {
                                const parsed = JSON.parse(data)

                                // Accumulation progressive du contenu
                                if (parsed.content) {
                                    const lastMessage = messages.value[messages.value.length - 1]
                                    if (lastMessage && lastMessage.role === 'assistant') {
                                        lastMessage.content += parsed.content
                                        nextTick(() => scrollToBottom())
                                    }
                                }

                                // Mise à jour temps réel du titre généré automatiquement
                                if (parsed.title) {
                                    if (selectedConversation.value) {
                                        selectedConversation.value.title = parsed.title
                                    }
                                    // Synchronisation avec la sidebar
                                    const convIndex = conversations.value.findIndex(c => c.id === selectedConversation.value?.id)
                                    if (convIndex !== -1) {
                                        conversations.value[convIndex].title = parsed.title
                                    }
                                }
                            } catch (error) {
                                console.log('Chunk ignoré:', data)
                            }
                        }
                    }
                }

                return processStream()
            })
        }

        return processStream()
    })
    .catch(error => {
        console.error('Erreur streaming:', error)
        isStreaming.value = false
        // Nettoyage en cas d'erreur : suppression du message assistant vide
        if (messages.value.length > 0) {
            const lastMessage = messages.value[messages.value.length - 1]
            if (lastMessage.role === 'assistant' && lastMessage.content === '') {
                messages.value.pop()
            }
        }
    })
}

// Mode classique : soumission traditionnelle avec rechargement via Inertia
function sendMessageClassic() {
    if (!newMessage.value || !selectedConversation.value) return

    loading.value = true

    router.post(route('messages.store', selectedConversation.value.id), {
        content: newMessage.value,
        model: localSelectedModel.value
    }, {
        only: ['messages', 'selectedConversation', 'conversations'], // Rechargement partiel
        onSuccess: (page) => {
            messages.value = page.props.messages
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

// Pattern Strategy : basculement entre les deux modes selon préférence utilisateur
function sendMessage() {
    if (isStreamingMode.value) {
        sendMessageStream()
    } else {
        sendMessageClassic()
    }
}

// Navigation entre conversations avec préservation de l'état
function selectConversation(conv) {
    loading.value = true

    router.visit(route('conversations.messages', conv.id), {
        preserveState: false, // Reset complet pour nouvelle conversation
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages
            localSelectedModel.value = page.props.selectedModel
        },
        onFinish: () => loading.value = false
    })
}

// Création de nouvelle conversation avec nettoyage automatique des conversations vides
function newConversation() {
    loading.value = true
    router.post(route('conversations.store'), {}, {
        only: ['conversations', 'selectedConversation', 'messages'],
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages || []
            if (page.props.conversations) {
                conversations.value = page.props.conversations
            }
        },
        onFinish: () => loading.value = false
    })
}

// Suppression avec recréation automatique si plus de conversations
function deleteConversation(conv) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
        loading.value = true

        router.delete(route('conversations.destroy', conv.id), {
            onSuccess: (page) => {
                conversations.value = page.props.conversations
                selectedConversation.value = page.props.selectedConversation
                messages.value = page.props.messages
                localSelectedModel.value = page.props.selectedModel
            },
            onFinish: () => loading.value = false
        })
    }
}

// Synchronisation modèle conversation + préférence utilisateur
function saveModel() {
    router.patch(route('conversations.updateModel', selectedConversation.value.id), {
        model: localSelectedModel.value
    }, {
        only: [], // Pas de rechargement, juste sauvegarde
        preserveState: true
    })
}

// Chargement des instructions depuis l'API avec conversion de types
function openCustomInstructions() {
    fetch(route('profile.get-custom-instructions'))
        .then(response => response.json())
        .then(data => {
            customInstructions.value = data.custom_instructions || ''
            customResponseStyle.value = data.custom_response_style || ''
            // Conversion explicite Boolean pour éviter erreurs Vue.js (1/0 → true/false)
            enableForNewChats.value = Boolean(data.enable_custom_instructions)
            customCommands.value = data.custom_commands || ''
        })
        .catch(error => {
            console.error('Erreur chargement:', error)
            customInstructions.value = ''
            customResponseStyle.value = ''
            enableForNewChats.value = true
            customCommands.value = ''
        })

    showCustomInstructions.value = true
}

// Sauvegarde des instructions avec feedback utilisateur
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
            console.log('Instructions sauvegardées')
        },
        onError: (errors) => {
            console.error('Erreur sauvegarde:', errors)
        },
        onFinish: () => saving.value = false
    })
}
</script>

<template>
    <AppLayout title="Chat">
        <div class="flex h-screen overflow-hidden">
            <!-- Communication parent-enfant via props/events pour architecture modulaire -->
            <ChatSidebar
                :conversations="conversations"
                :selected-conversation="selectedConversation"
                :sidebar-collapsed="sidebarCollapsed"
                :show-sidebar="showSidebar"
                :is-mobile="isMobile"
                @update:sidebar-collapsed="sidebarCollapsed = $event"
                @select-conversation="selectConversation"
                @new-conversation="newConversation"
                @delete-conversation="deleteConversation"
                @close-sidebar="showSidebar = false" />

            <!-- Overlay mobile pour fermeture tactile -->
            <div v-if="showSidebar"
                 class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"
                 @click="showSidebar = false"></div>

            <main class="flex-1 flex flex-col h-full">
                <!-- Pattern de slot nommé pour injection de contenu spécialisé -->
                <ChatHeader
                    :selected-conversation="selectedConversation"
                    :models="models"
                    :selected-model="localSelectedModel"
                    :is-streaming-mode="isStreamingMode"
                    :is-mobile="isMobile"
                    @update:selected-model="localSelectedModel = $event"
                    @update:is-streaming-mode="isStreamingMode = $event"
                    @open-custom-instructions="openCustomInstructions"
                    @save-model="saveModel">

                    <template #mobile-menu-button>
                        <button @click="showSidebar = true" class="text-2xl">☰</button>
                    </template>
                </ChatHeader>

                <!-- Référence pour contrôle du scroll depuis le parent -->
                <MessagesList
                    ref="messagesListRef"
                    :messages="messages"
                    :loading="loading"
                    :is-streaming="isStreaming" />

                <!-- v-model bidirectionnel avec nomenclature Vue 3 -->
                <MessageInput
                    v-model:new-message="newMessage"
                    :loading="loading"
                    :is-streaming="isStreaming"
                    :sidebar-collapsed="sidebarCollapsed"
                    @send-message="sendMessage" />
            </main>
        </div>

        <!-- Modal avec bindings bidirectionnels pour synchronisation état -->
        <CustomInstructionsModal
            :show="showCustomInstructions"
            :custom-instructions="customInstructions"
            :custom-response-style="customResponseStyle"
            :custom-commands="customCommands"
            :enable-for-new-chats="enableForNewChats"
            :saving="saving"
            @close="showCustomInstructions = false"
            @update:custom-instructions="customInstructions = $event"
            @update:custom-response-style="customResponseStyle = $event"
            @update:custom-commands="customCommands = $event"
            @update:enable-for-new-chats="enableForNewChats = $event"
            @save="saveCustomInstructions" />
    </AppLayout>
</template>
