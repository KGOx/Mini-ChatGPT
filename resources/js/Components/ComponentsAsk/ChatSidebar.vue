<script setup>
import { ref } from 'vue'
import ConversationItem from './ConversationItem.vue'

const props = defineProps({
  conversations: Array,
  selectedConversation: Object,
  sidebarCollapsed: Boolean,
  showSidebar: Boolean,
  isMobile: Boolean
})

const emit = defineEmits([
  'select-conversation',
  'new-conversation',
  'delete-conversation',
  'close-sidebar',
  'update:sidebarCollapsed'
])

function selectConversation(conv) {
  emit('select-conversation', conv)
  if (props.isMobile) {
    emit('close-sidebar')
  }
}
</script>

<template>
  <aside
    @mouseenter="$emit('update:sidebarCollapsed', false)"
    @mouseleave="$emit('update:sidebarCollapsed', true)"
    :class="[
      'fixed inset-y-0 left-0 z-40 bg-gray-100 border-r transform transition-all duration-300 ease-in-out flex flex-col',
      'w-64', showSidebar ? 'translate-x-0' : '-translate-x-full',
      'md:static md:translate-x-0',
      sidebarCollapsed ? 'md:w-16' : 'md:w-80',
    ]">

    <!-- Header -->
    <div class="p-2 md:p-4 font-bold flex justify-between items-center border-b flex-shrink-0">
      <span :class="{ 'md:hidden': sidebarCollapsed, 'md:block': !sidebarCollapsed }">
        Conversations
      </span>

      <!-- Icône collapsed -->
      <div v-if="sidebarCollapsed" class="hidden md:flex w-full justify-center">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      </div>

      <button class="md:hidden text-2xl" @click="$emit('close-sidebar')">×</button>
    </div>

    <!-- Liste conversations -->
    <div class="flex-1 overflow-y-auto min-h-0">
      <!-- Mode collapsed -->
      <div v-if="sidebarCollapsed" class="hidden md:block p-2 space-y-3">
        <div v-for="(conv, index) in conversations.slice(0, 6)" :key="conv.id"
             class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 cursor-pointer hover:bg-blue-200 transition-colors"
             :title="conv.title || 'Nouvelle conversation'"
             @click="selectConversation(conv)">
          {{ index + 1 }}
        </div>

        <div v-if="conversations.length > 6"
             class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-xs text-gray-600">
          +{{ conversations.length - 6 }}
        </div>
      </div>

      <!-- Mode normal -->
      <ul :class="{ 'md:hidden': sidebarCollapsed, 'md:block': !sidebarCollapsed }">
        <ConversationItem
          v-for="conv in conversations"
          :key="conv.id"
          :conversation="conv"
          :isSelected="selectedConversation?.id === conv.id"
          @select="selectConversation"
          @delete="$emit('delete-conversation', conv)" />
      </ul>
    </div>

    <!-- Bouton nouvelle conversation -->
    <div class="border-t bg-gray-50 p-3 flex-shrink-0">
      <button @click="$emit('new-conversation')"
              :class="[
                'w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors font-medium',
                sidebarCollapsed ? 'md:p-2 md:rounded-full' : 'text-sm md:text-base'
              ]">
        <span v-if="!sidebarCollapsed || isMobile">+ Nouvelle conversation</span>
        <svg v-else class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
      </button>
    </div>
  </aside>
</template>
