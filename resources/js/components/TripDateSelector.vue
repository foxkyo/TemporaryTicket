<script setup>
const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:modelValue"]);

function handleDateChange(event) {
    emit("update:modelValue", event.target.value);
}
</script>

<template>
    <div class="flex w-[120px] items-center gap-2">
        <input
            :value="modelValue"
            type="date"
            lang="zh-TW"
            class="h-9 w-full rounded-md border bg-background px-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
            @input="handleDateChange"
        />
    </div>
</template>
