<script setup>
import { computed, onMounted, nextTick, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { buildTicketPages } from '@/lib/ticketPageGroups.js';
import JsBarcode from 'jsbarcode';
import Tickets from '@/components/Tickets.vue';

const page = usePage();

// 從URL參數解析tickets
const tickets = computed(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const ticketsParam = urlParams.get('tickets');
    if (ticketsParam) {
        try {
            return JSON.parse(decodeURIComponent(ticketsParam));
        } catch (e) {
            console.error('Failed to parse tickets:', e);
            return [];
        }
    }
    return page.props.tickets || [];
});

const barcodeRefs = ref([]);

function formatPrice(price) {
    if (price === null || price === undefined) {
        return '0';
    }
    return Number(price).toLocaleString('zh-TW');
}

function renderBarcodes() {
    nextTick(() => {
        tickets.value.forEach((ticket, index) => {
            const canvas = barcodeRefs.value[index];
            if (canvas && ticket.barcode) {
                try {
                    JsBarcode(canvas, ticket.barcode, {
                        format: 'CODE39',
                        displayValue: false,
                        width: 2,
                        height: 40,
                        margin: 0,
                    });
                } catch (error) {
                    console.error('Barcode generation error:', error, 'Barcode:', ticket.barcode);
                }
            }
        });
    });
}

onMounted(() => {
    renderBarcodes();
});

watch(() => tickets.value, () => {
    renderBarcodes();
}, { deep: true });
</script>

<template>
    <div class="bg-white">
        <Tickets :tickets="tickets" />
    </div>
</template>
