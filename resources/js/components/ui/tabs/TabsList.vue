<script setup lang="ts">
import { TabsList, type TabsListProps } from "reka-ui";
import { type HTMLAttributes } from "vue";

const props = defineProps<
    TabsListProps & { class?: HTMLAttributes["class"] }
>();

const { class: _, ...delegatedProps } = props;
</script>

<template>
    <TabsList
        v-bind="delegatedProps"
        class="inline-flex h-9 items-center justify-center rounded-lg bg-slate-100 p-1 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
    >
        <slot />
    </TabsList>
</template>
