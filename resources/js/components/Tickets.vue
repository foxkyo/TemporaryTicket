<script setup>
import { computed, onMounted, nextTick, ref, watch } from 'vue';
import { buildTicketPages } from '@/lib/ticketPageGroups.js';
import JsBarcode from 'jsbarcode';

const props = defineProps({
    tickets: {
        type: Array,
        default: () => [],
    },
});

const pages = computed(() => buildTicketPages(props.tickets));
const barcodeRefs = ref([]);

function formatPrice(price) {
    if (price === null || price === undefined) {
        return '0';
    }
    return Number(price).toLocaleString('zh-TW');
}

function renderBarcodes() {
    nextTick(() => {
        pages.value.forEach((page, index) => {
            const canvas = barcodeRefs.value[index];
            if (canvas && page.barcode) {
                try {
                    JsBarcode(canvas, page.barcode, {
                        displayValue: false,
                        width: 2,
                        height: 40,
                        margin: 0,
                    });
                } catch (error) {
                    console.error('Barcode generation error:', error, 'Barcode:', page.barcode);
                }
            }
        });
    });
}

onMounted(() => {
    renderBarcodes();
});

watch(() => props.tickets, () => {
    renderBarcodes();
}, { deep: true });

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
    <div id="ticket-print-container" class="space-y-6 bg-white">
        <div
            v-for="(page, index) in pages"
            :key="`${page.studentKey}-${page.pageNumber}`"
            class="break-before-page bg-white"
        >
            <header class="pb-1 text-center">
                <div class="text-xl font-bold tracking-wide">
                    {{ new Date().getFullYear() }} 協同中學臨時校車票
                </div>
            </header>

            <div class="rounded border border-black">
                <table class="w-full border-collapse text-sm font-bold">
                    <thead class="bg-slate-100">
                        <tr>
                            <th
                                class="border border-black px-1 text-left"
                            >
                                日期 / 時段
                            </th>

                            <th
                                class="border border-black px-1 text-left"
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
                                class="border border-black px-1 align-top"
                            >
                                <div class="font-medium">
                                    {{ getTicketDateLabel(item) }}
                                </div>

                                <div class="">
                                    {{ getPeriodText(item) }}
                                </div>
                            </td>

                            <td
                                class="border border-black px-1 align-top"
                            >
                                <div class="">
                                    {{
                                        item.車別 ??
                                        item.station?.vehicle ??
                                        '未選擇'
                                    }}
                                </div>

                                <div class="">
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
                class="mt-3 text-left text-[13.5px] leading-relaxed whitespace-pre-wrap"
            >
                <div class="text-lg font-bold">
                    ★ 注意事項 ★
                </div>

                <div class="pl-3">1. 本臨時車票為車票查驗之依據。</div>
                <div class="pl-3">2. 未經總務處蓋章無效。</div>
            </div>

            <footer
                class="border-t border-black pt-1 pb-4 font-bold text-sm"
            >
                <div class="flex items-center justify-between">
                    <div>
                        搭乘者：{{ page.studentDisplay }}
                    </div>

                    <div>
                        總票價：NT$ <span class="text-lg">{{ formatPrice(page.totalPrice) }}</span> 元
                    </div>
                </div>
                <div class="flex flex-col items-center justify-center">
                    <canvas
                        :ref="(el) => { if (el) barcodeRefs[index] = el }"
                        class="mx-auto max-w-full"
                        style="min-height: 50px;"
                    ></canvas>
                    <div class="mt-1 text-xs">
                        {{ page.barcode }}
                    </div>
                </div>
                
                <div class="mt-1 text-right" v-if="page.totalPages > 1">
                    第 {{ page.pageNumber }} / {{ page.totalPages }} 頁
                </div>
            </footer>
        </div>
    </div>
</template>