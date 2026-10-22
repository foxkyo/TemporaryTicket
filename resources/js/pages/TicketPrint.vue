<script setup>
import { computed, onMounted, nextTick, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
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
        // 動態導入 tauri-plugin-printer v2
        const { printHtml } = await import("tauri-plugin-printer");

        // 獲取整個 HTML 內容
        const htmlContent = document.documentElement.outerHTML;

        // 使用 tauri-plugin-printer v2 進行靜默列印
        await printHtml(htmlContent, {
            page_size: { width: 800, height: 1200 },
        });

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
