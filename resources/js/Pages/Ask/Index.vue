<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css' // ou un autre thème

// Markdown-it + highlight.js config
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
    models: Array,
    selectedModel: String,
    flash: Object
})

const form = useForm({
    message: '',
    model: props.selectedModel
})

const processing = ref(false)

function submit() {
    processing.value = true
    form.post(route('ask.post'), {
        onFinish: () => (processing.value = false)
    })
}

// Rendu markdown de la réponse
const renderedMarkdown = computed(() =>
    props.flash.message ? md.render(props.flash.message) : ''
)
</script>


<template>
    <AppLayout title="Dashboard">
        <div class="max-w-2xl mx-auto py-8">
            <form @submit.prevent="submit" class="mb-6 flex flex-col gap-4">
                <textarea v-model="form.message" rows="4" class="w-full p-3 border rounded"
                    placeholder="Posez votre question…" required></textarea>
                <select v-model="form.model" class="p-2 border rounded">
                    <option v-for="model in models" :key="model.id" :value="model.id">
                        {{ model.name }}
                    </option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                    :disabled="processing">
                    Envoyer
                </button>
            </form>

            <div v-if="flash.error" class="text-red-600 mb-4">{{ flash.error }}</div>

            <div v-if="flash.message" class="prose dark:prose-invert prose-slate max-w-none">
                <!-- Rendu markdown ici -->
                <div v-html="renderedMarkdown"></div>
            </div>
        </div>
    </AppLayout>
</template>


