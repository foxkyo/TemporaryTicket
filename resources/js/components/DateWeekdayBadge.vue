<script setup>
import { computed } from 'vue';

const props = defineProps({
    date: {
        type: String,
        default: '',
    },
});

const weekdayLabel = computed(() => {
    if (!props.date) {
        return '未選擇日期';
    }

    const parsed = new Date(`${props.date}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return '未選擇日期';
    }

    return new Intl.DateTimeFormat('zh-TW', {
        weekday: 'long',
        timeZone: 'Asia/Taipei',
    }).format(parsed);
});
</script>

<template>
    <div
        class="inline-flex items-center rounded-full border border-primary/30 bg-primary/10 px-3 py-1.5 text-sm font-medium text-primary"
    >
        {{ weekdayLabel }}
    </div>
</template>
