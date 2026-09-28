<script setup>
import { computed } from 'vue';

import TripActionGroup from '@/components/TripActionGroup.vue';
import TripDateSelector from '@/components/TripDateSelector.vue';
import TripPeriodButtonGroup from '@/components/TripPeriodButtonGroup.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            date: '',
            periods: [],
        }),
    },
    selectedStation: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'check', 'remove']);

const selectedDate = computed({
    get: () => props.modelValue?.date ?? '',
    set: (value) => {
        emit('update:modelValue', {
            ...props.modelValue,
            date: value,
        });
    },
});

const selectedPeriods = computed({
    get: () => props.modelValue?.periods ?? [],
    set: (value) => {
        emit('update:modelValue', {
            ...props.modelValue,
            periods: value,
        });
    },
});

const canCheck = computed(() => {
    return Boolean(
        selectedDate.value &&
        selectedPeriods.value.length &&
        props.selectedStation,
    );
});

function removeSelection() {
    emit('remove');
}

function handleCheck() {
    emit('check');
}
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-lg border bg-card p-2 shadow-sm"
    >
        <TripDateSelector v-model="selectedDate" />

        <TripPeriodButtonGroup v-model="selectedPeriods" />
    </div>
</template>
