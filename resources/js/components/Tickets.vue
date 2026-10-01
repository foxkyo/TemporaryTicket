<script setup>
import { computed } from 'vue';
import { buildTicketPages } from '@/lib/ticketPageGroups.js';

const props = defineProps({
    tickets: {
        type: Array,
        default: () => [],
    },
});

const pages = computed(() => buildTicketPages(props.tickets));

function getTicketDateLabel(ticket) {
    const rawDate = ticket?.日期 ?? ticket?.date ?? '';

    if (!rawDate) {
        return '未指定日期';
    }

    const date = new Date(`${rawDate}T00:00:00`);
    const weekday = ['日', '一', '二', '三', '四', '五', '六'][date.getDay()];
    const formatted = rawDate.slice(5);

    return `${formatted} (${weekday})`;
}

function getPeriodText(ticket) {
    const periods = ticket?.時段 ?? ticket?.periods ?? [];

    if (!Array.isArray(periods) || periods.length === 0) {
        return '未選擇時段';
    }

    return periods.join(' / ');
}
</script>

<template>
    <div class="space-y-6 bg-white p-2">
        <div
            v-for="page in pages"
            :key="`${page.studentKey}-${page.pageNumber}`"
            class="break-before-page rounded-md border border-slate-300 bg-white p-4 shadow-sm"
        >
            <header class="border-b border-slate-400 pb-2 text-center">
                <div class="text-xl font-semibold tracking-wide">
                    {{ new Date().getFullYear() }} 協同中學臨時校車票
                </div>
                <div class="mt-1 text-sm text-slate-600">
                    學生：{{ page.studentDisplay }}
                </div>
            </header>

            <div class="mt-3 overflow-hidden rounded border border-slate-300">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th
                                class="border-b border-slate-300 px-2 py-2 text-left font-semibold"
                            >
                                日期 / 時段
                            </th>
                            <th
                                class="border-b border-slate-300 px-2 py-2 text-left font-semibold"
                            >
                                車別 / 站別
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in page.items"
                            :key="
                                item.id ??
                                `${page.studentKey}-${item.日期}-${item.車別}`
                            "
                        >
                            <td
                                class="border-b border-slate-200 px-2 py-2 align-top"
                            >
                                <div class="font-medium">
                                    {{ getTicketDateLabel(item) }}
                                </div>
                                <div class="mt-1 text-slate-700">
                                    {{ getPeriodText(item) }}
                                </div>
                            </td>
                            <td
                                class="border-b border-slate-200 px-2 py-2 align-top"
                            >
                                <div class="font-semibold">
                                    {{
                                        item.車別 ??
                                        item.station?.vehicle ??
                                        '未選擇'
                                    }}
                                </div>
                                <div class="mt-1 text-slate-700">
                                    {{
                                        item.站別 ??
                                        item.station?.station ??
                                        '未選擇'
                                    }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-3 text-left text-[13.5px] leading-relaxed whitespace-pre-wrap text-slate-700"
            >
                <div class="text-lg font-semibold text-slate-900">
                    ★ 注意事項 ★
                </div>
                1. 本臨時車票為車票查驗之依據。 2. 未經總務處蓋章無效。
            </div>

            <footer
                class="mt-4 border-t border-slate-400 pt-2 text-center text-xs text-slate-600"
            >
                {{ page.studentDisplay }} · 第 {{ page.pageNumber }} /
                {{ page.totalPages }} 頁
            </footer>
        </div>
    </div>
</template>
