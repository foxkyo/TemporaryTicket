<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
    show: boolean
    message: string
    type?: 'success' | 'error' | 'info'
}>()

const emit = defineEmits<{
    close: []
}>()

const isVisible = ref(props.show)

watch(() => props.show, (newVal) => {
    isVisible.value = newVal
})

const close = () => {
    isVisible.value = false
    emit('close')
}

// 自動關閉
let timeout: number
watch(isVisible, (newVal) => {
    if (newVal) {
        timeout = window.setTimeout(() => {
            close()
        }, 3000)
    } else {
        clearTimeout(timeout)
    }
})
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="transform opacity-0 translate-y-2"
        enter-to-class="transform opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="transform opacity-100 translate-y-0"
        leave-to-class="transform opacity-0 translate-y-2"
    >
        <div
            v-if="isVisible"
            class="fixed bottom-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg"
            :class="{
                'bg-green-50 text-green-900 border border-green-200': type === 'success',
                'bg-red-50 text-red-900 border border-red-200': type === 'error',
                'bg-sky-50 text-sky-900 border border-sky-200': type === 'info' || !type
            }"
        >
            <div class="flex items-center gap-2">
                <span class="text-sm">{{ message }}</span>
                <button
                    type="button"
                    class="ml-2 text-slate-400 hover:text-slate-600"
                    @click="close"
                >
                    ✕
                </button>
            </div>
        </div>
    </Transition>
</template>
