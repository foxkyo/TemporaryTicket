<script setup lang="ts">
import { ref } from 'vue'

const modelValue = defineModel<string>()

const formatDate = (date: Date): string => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement
    modelValue.value = target.value
}
</script>

<template>
    <input
        type="date"
        :value="modelValue"
        @input="handleInput"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
    />
</template>
