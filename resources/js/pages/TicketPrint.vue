<script setup>
import { computed, onMounted, nextTick, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { invoke } from "@tauri-apps/api/core";
import { buildTicketPages } from "@/lib/ticketPageGroups.js";
import JsBarcode from "jsbarcode";
import Tickets from "@/components/Tickets.vue";

const page = usePage();

// 從 Inertia props 獲取 tickets
const tickets = computed(() => page.props.tickets || []);
const autoPrint = computed(() => page.props.autoPrint ?? true);

const barcodeRefs = ref([]);

function formatPrice(price) {
    if (price === null || price === undefined) {
        return "0";
    }
    return Number(price).toLocaleString("zh-TW");
}

function renderBarcodes() {
    return nextTick(() => {
        tickets.value.forEach((ticket, index) => {
            const canvas = barcodeRefs.value[index];
            if (canvas && ticket.barcode) {
                try {
                    JsBarcode(canvas, ticket.barcode, {
                        displayValue: false,
                        width: 2,
                        height: 40,
                        margin: 0,
                    });
                } catch (error) {
                    console.error(
                        "Barcode generation error:",
                        error,
                        "Barcode:",
                        ticket.barcode,
                    );
                }
            }
        });
    });
}

async function silentPrint() {
    try {
        // 將票據內容轉為文本格式
        let text = "";
        tickets.value.forEach((ticket, index) => {
            text += `=== 票據 ${index + 1} ===\n`;
            text += `學生: ${ticket.student || ""}\n`;
            text += `站別: ${ticket.station || ""}\n`;
            text += `日期: ${ticket.date || ""}\n`;
            text += `時段: ${ticket.periods?.join(", ") || ""}\n`;
            text += `金額: ${formatPrice(ticket.amount)}\n`;
            text += `條碼: ${ticket.barcode || ""}\n`;
            text += `\n`;
        });

        // 調用 Rust 靜默列印
        await invoke("silent_print", { text });
        console.log("列印成功");
    } catch (error) {
        console.error("Tauri 靜默列印失敗:", error);
        console.log("回退到瀏覽器列印");
        // 如果 Tauri 不可用，回退到瀏覽器列印
        window.print();
    }
}

onMounted(async () => {
    await renderBarcodes();
    // 等待條碼渲染完成後再列印（如果啟用自動列印）
    if (autoPrint.value) {
        setTimeout(() => {
            silentPrint();
        }, 500);
    }
});

watch(
    () => tickets.value,
    () => {
        renderBarcodes();
    },
    { deep: true },
);
</script>

<template>
    <div class="bg-white">
        <Tickets :tickets="tickets" />
    </div>
</template>
