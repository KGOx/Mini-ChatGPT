<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import ChatSidebar from '@/Components/ComponentsAsk/ChatSidebar.vue'
import ChatHeader from '@/Components/ComponentsAsk/ChatHeader.vue'
import MessagesList from '@/Components/ComponentsAsk/MessagesList.vue'
import MessageInput from '@/Components/ComponentsAsk/MessageInput.vue'
import CustomInstructionsModal from '@/Components/ComponentsAsk/CustomInstructionsModal.vue'

// Props corrigées
const props = defineProps({
    conversations: Array,
    selectedConversation: Object,
    messages: Array,
    models: Array,
    selectedModel: String,
    auth: Object, // ← AJOUTÉ
    flash: Object
})

// Variables principales
const showSidebar = ref(false)
const sidebarCollapsed = ref(true)
const isMobile = ref(false)
const selectedConversation = ref(props.selectedConversation)
const messages = ref(props.messages)
const conversations = ref(props.conversations)
const localSelectedModel = ref(props.selectedModel)
const loading = ref(false)
const newMessage = ref('')
const isStreamingMode = ref(true)
const isStreaming = ref(false)

// Variables instructions personnalisées
const showCustomInstructions = ref(false)
const customInstructions = ref('')
const customResponseStyle = ref('')
const enableForNewChats = ref(true)
const customCommands = ref('')
const saving = ref(false)

// Référence pour scroll
const messagesListRef = ref()

// Détecter mobile
onMounted(() => {
    const checkMobile = () => {
        isMobile.value = window.innerWidth < 768
    }

    checkMobile()
    window.addEventListener('resize', checkMobile)

    return () => window.removeEventListener('resize', checkMobile)
})

// Scroll automatique
const scrollToBottom = async () => {
    await nextTick()
    if (messagesListRef.value?.scrollToBottom) {
        messagesListRef.value.scrollToBottom()
    }
}

// Recharger conversations
const reloadConversations = () => {
    router.reload({
        only: ['conversations', 'selectedConversation'],
        preserveState: true
    })
}

// Streaming
function sendMessageStream() {
    if (!newMessage.value.trim() || isStreaming.value) return

    if (!selectedConversation.value?.id) {
        console.error('Aucune conversation sélectionnée')
        return
    }

    isStreaming.value = true

    // Ajouter message utilisateur
    const userMessage = {
        id: Date.now(),
        user_id: props.auth?.user?.id,
        role: 'user',
        content: newMessage.value,
        created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)

    // Ajouter message assistant vide
    const assistantMessage = {
        id: Date.now() + 1,
        user_id: null,
        role: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
    }
    messages.value.push(assistantMessage)

    scrollToBottom()

    const messageToSend = newMessage.value
    newMessage.value = ''

    // Fetch streaming
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

        function processStream() {
            return reader.read().then(({ done, value }) => {
                if (done) {
                    isStreaming.value = false
                    reloadConversations()
                    return
                }

                const chunk = decoder.decode(value, { stream: true })
                const lines = chunk.split('\n')

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6).trim()

                        if (data === '[DONE]') {
                            isStreaming.value = false
                            reloadConversations()
                            return
                        }

                        if (data) {
                            try {
                                const parsed = JSON.parse(data)

                                if (parsed.content) {
                                    const lastMessage = messages.value[messages.value.length - 1]
                                    if (lastMessage && lastMessage.role === 'assistant') {
                                        lastMessage.content += parsed.content
                                        nextTick(() => scrollToBottom())
                                    }
                                }

                                if (parsed.title) {
                                    if (selectedConversation.value) {
                                        selectedConversation.value.title = parsed.title
                                    }
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
        if (messages.value.length > 0) {
            const lastMessage = messages.value[messages.value.length - 1]
            if (lastMessage.role === 'assistant' && lastMessage.content === '') {
                messages.value.pop()
            }
        }
    })
}

// Mode classique
function sendMessageClassic() {
    if (!newMessage.value || !selectedConversation.value) return

    loading.value = true

    router.post(route('messages.store', selectedConversation.value.id), {
        content: newMessage.value,
        model: localSelectedModel.value
    }, {
        only: ['messages', 'selectedConversation', 'conversations'],
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

// Basculer mode
function sendMessage() {
    if (isStreamingMode.value) {
        sendMessageStream()
    } else {
        sendMessageClassic()
    }
}

// Sélection conversation
function selectConversation(conv) {
    loading.value = true

    router.visit(route('conversations.messages', conv.id), {
        preserveState: false,
        onSuccess: (page) => {
            selectedConversation.value = page.props.selectedConversation
            messages.value = page.props.messages
            localSelectedModel.value = page.props.selectedModel
        },
        onFinish: () => loading.value = false
    })
}

// Nouvelle conversation
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

// Supprimer conversation
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

// Sauvegarder modèle
function saveModel() {
    router.patch(route('conversations.updateModel', selectedConversation.value.id), {
        model: localSelectedModel.value
    }, {
        only: [],
        preserveState: true
    })
}

// Instructions personnalisées
function openCustomInstructions() {
    fetch(route('profile.get-custom-instructions'))
        .then(response => response.json())
        .then(data => {
            customInstructions.value = data.custom_instructions || ''
            customResponseStyle.value = data.custom_response_style || ''
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

            <!-- Sidebar -->
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

            <!-- Overlay mobile -->
            <div v-if="showSidebar"
                 class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"
                 @click="showSidebar = false"></div>

            <!-- Zone principale -->
            <main class="flex-1 flex flex-col h-full">

                <!-- Header -->
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

                <!-- Messages -->
                <MessagesList
                    ref="messagesListRef"
                    :messages="messages"
                    :loading="loading"
                    :is-streaming="isStreaming" />

                <!-- Zone saisie -->
                <MessageInput
                    v-model:new-message="newMessage"
                    :loading="loading"
                    :is-streaming="isStreaming"
                    :sidebar-collapsed="sidebarCollapsed"
                    @send-message="sendMessage" />

            </main>
        </div>

        <!-- Modal -->
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
