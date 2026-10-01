<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const dateValue = (date) => {
    const offset = date.getTimezoneOffset();
    const normalized = new Date(date.getTime() - offset * 60 * 1000);

    return normalized.toISOString().slice(0, 10);
};

const minDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return dateValue(today);
});

const maxDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    today.setDate(today.getDate() + 30);

    return dateValue(today);
});

const weekdayLabel = computed(() => {
    if (!props.modelValue) {
        return '—';
    }

    const parsed = new Date(`${props.modelValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('zh-TW', {
        weekday: 'short',
        timeZone: 'Asia/Taipei',
    })
        .format(parsed)
        .replace('週', '')
        .replace('星期', '');
});

const isWeekend = computed(() => {
    if (!props.modelValue) {
        return false;
    }

    const parsed = new Date(`${props.modelValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return false;
    }

    const day = parsed.getDay();

    return day === 0 || day === 6;
});

function handleDateChange(event) {
    const nextValue = event.target.value;

    if (!nextValue) {
        emit('update:modelValue', '');
        return;
    }

    const value = new Date(`${nextValue}T00:00:00`);
    const min = new Date(`${minDate.value}T00:00:00`);
    const max = new Date(`${maxDate.value}T00:00:00`);

    if (value < min) {
        emit('update:modelValue', minDate.value);
        return;
    }

    if (value > max) {
        emit('update:modelValue', maxDate.value);
        return;
    }

    emit('update:modelValue', nextValue);
}
</script>

<template>
    <div class="flex w-[155px] items-center justify-center gap-1.5">
        <input
            :value="modelValue"
            type="date"
            :min="minDate"
            :max="maxDate"
            lang="zh-TW"
            class="h-8 w-full min-w-0 rounded-md border bg-background px-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
            @input="handleDateChange"
        />

        <span
            class="inline-flex shrink-0 items-center justify-center rounded-full border px-1.5 py-0.5 text-[9px] font-medium"
            :class="
                isWeekend
                    ? 'border-red-200 bg-red-100 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                    : 'border-green-200 bg-green-100 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-200'
            "
        >
            {{ weekdayLabel }}
        </span>
    </div>
</template>
