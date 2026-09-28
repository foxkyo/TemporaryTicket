<script setup>
import { computed } from 'vue';
import { Check, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    date: {
        type: String,
        default: '',
    },
    periods: {
        type: Array,
        default: () => [],
    },
    vehicle: {
        type: String,
        default: '',
    },
    remaining: {
        type: Number,
        default: null,
    },
    checkState: {
        type: String,
        default: 'disabled',
        validator: (value) => ['disabled', 'valid', 'invalid'].includes(value),
    },
    showDelete: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['check', 'remove']);

const isInvalid = computed(() => props.checkState === 'invalid');
const isValid = computed(() => props.checkState === 'valid');

const remainingDisplay = computed(() => {
    if (props.remaining === null || Number.isNaN(props.remaining)) {
        return 'xx';
    }

    return String(props.remaining);
});

const checkTitle = computed(() => {
    const periodText =
        Array.isArray(props.periods) && props.periods.length
            ? props.periods.join(' / ')
            : '未選擇';

    const vehicleText = props.vehicle || '未選擇';
    const remainderText =
        props.remaining === null || Number.isNaN(props.remaining)
            ? '已無票可購'
            : `剩餘票數: ${remainingDisplay.value}`;

    const stateText =
        props.checkState === 'invalid'
            ? '檢查狀態: invalid'
            : props.checkState === 'valid'
              ? '檢查狀態: valid'
              : '檢查狀態: disabled';

    return `檢查 ${props.date || '日期'}｜時段: ${periodText}｜車別: ${vehicleText}｜${remainderText}｜${stateText}`;
});
</script>

<template>
    <div class="ml-auto flex items-center gap-2">
        <Button
            type="button"
            variant="outline"
            size="icon"
            class="h-9 w-9"
            :class="
                isInvalid
                    ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400'
                    : isValid
                      ? 'border-green-200 bg-green-50 text-green-600 hover:bg-green-100 dark:border-green-900 dark:bg-green-950/30 dark:text-green-400'
                      : 'border-muted bg-muted/50 text-muted-foreground'
            "
            :disabled="disabled"
            :title="checkTitle"
            @click="emit('check')"
        >
            <Check v-if="isValid" class="h-4 w-4" />
            <span v-else-if="isInvalid" class="text-sm leading-none font-bold">
                ×
            </span>
            <span v-else class="text-xs leading-none">—</span>
        </Button>

        <Button
            v-if="showDelete"
            type="button"
            variant="outline"
            size="icon"
            class="h-9 w-9 border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"
            title="刪除"
            @click="emit('remove')"
        >
            <Trash2 class="h-4 w-4" />
        </Button>
    </div>
</template>
