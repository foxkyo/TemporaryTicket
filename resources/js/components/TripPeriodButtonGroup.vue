<script setup>
import { Button } from '@/components/ui/button';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

function togglePeriod(period) {
    const next = props.modelValue.includes(period)
        ? props.modelValue.filter((item) => item !== period)
        : [...props.modelValue, period];

    emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-for="period in ['上學', '放學']"
            :key="period"
            type="button"
            size="sm"
            variant="outline"
            class="min-w-[72px] justify-center"
            :class="
                modelValue.includes(period)
                    ? 'border-primary bg-primary/10 text-primary'
                    : ''
            "
            @click="togglePeriod(period)"
        >
            {{ period }}
        </Button>
    </div>
</template>
