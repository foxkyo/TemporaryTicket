<script setup lang="ts">
import { TabsContent, type TabsContentProps } from "reka-ui";
import { type HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<
    TabsContentProps & { class?: HTMLAttributes["class"] }
>();

const { class: _, ...delegatedProps } = props;
</script>

<template>
    <TabsContent
        v-bind="delegatedProps"
        :class="
            cn(
                'mt-2 ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 dark:ring-offset-slate-950 dark:focus-visible:ring-slate-300',
                props.class,
            )
        "
    >
        <slot />
    </TabsContent>
</template>
