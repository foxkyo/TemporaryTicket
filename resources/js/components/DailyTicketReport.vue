<script setup lang="ts">
import { ref, onMounted, computed, watch } from "vue";
import axios from "axios";
import DatePicker from "@/components/ui/date-picker/DatePicker.vue";

const selectedDate = ref("");
const loading = ref(false);
const reportData = ref<any>(null);
const generating = ref(false);

const formatDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const getPreviousDay = (dateString: string): string => {
    const date = new Date(dateString);
    date.setDate(date.getDate() - 1);
    return formatDate(date);
};

const getNextDay = (dateString: string): string => {
    const date = new Date(dateString);
    date.setDate(date.getDate() + 1);
    return formatDate(date);
};

// 將車別分為嘉義縣市和其他
const categorizedVehicles = computed(() => {
    if (!reportData.value?.vehicles) return { chiayi: [], others: [] };

    const chiayi = [];
    const others = [];

    reportData.value.vehicles.forEach((vehicle: any) => {
        // 按站別分組統計
        const stationStats: Record<
            string,
            { periods: Set<string>; count: number; amount: number }
        > = {};

        vehicle.stations.forEach((station: any) => {
            const periods = Array.isArray(station.periods)
                ? station.periods
                : [station.periods];

            if (!stationStats[station.station]) {
                stationStats[station.station] = {
                    periods: new Set(),
                    count: 0,
                    amount: 0,
                };
            }

            // 計算購次：上學+放學算2，單純上學或放學算1
            const hasMorning = periods.includes("上學");
            const hasAfternoon = periods.includes("放學");
            const purchaseCount = hasMorning && hasAfternoon ? 2 : 1;

            stationStats[station.station].count += purchaseCount;
            stationStats[station.station].amount += station.amount;
            periods.forEach((p: string) =>
                stationStats[station.station].periods.add(p),
            );
        });

        // 轉換為陣列格式
        const stationStatsArray = Object.entries(stationStats).map(
            ([station, stats]) => {
                return {
                    station,
                    count: stats.count,
                    amount: stats.amount,
                };
            },
        );

        const vehicleWithStats = {
            ...vehicle,
            stationStats: stationStatsArray,
        };

        if (vehicle.vehicle.startsWith("嘉")) {
            chiayi.push(vehicleWithStats);
        } else {
            others.push(vehicleWithStats);
        }
    });

    return { chiayi, others };
});

// 計算當日總計金額
const totalAmount = computed(() => {
    if (!reportData.value?.vehicles) return 0;
    return reportData.value.vehicles.reduce(
        (sum: number, v: any) => sum + v.total_amount,
        0,
    );
});

onMounted(() => {
    const today = new Date();
    selectedDate.value = formatDate(today);
});

// 自動載入報告當日期變更
watch(selectedDate, (newDate) => {
    if (newDate) {
        void fetchReport();
    }
});

const fetchReport = async () => {
    if (!selectedDate.value) return;

    loading.value = true;
    try {
        const { data } = await axios.get(
            `/api/daily-ticket-report/${selectedDate.value}`,
        );
        reportData.value = data;
    } catch (error) {
        console.error("載入報告失敗", error);
    } finally {
        loading.value = false;
    }
};

const goToPreviousDay = () => {
    selectedDate.value = getPreviousDay(selectedDate.value);
};

const goToNextDay = () => {
    selectedDate.value = getNextDay(selectedDate.value);
};

const generatePDF = async () => {
    if (!reportData.value) return;

    generating.value = true;
    try {
        // TODO: Implement PDF generation using html2canvas and jspdf
        // The report should be in A4 format
        alert("PDF 生成功能開發中");
    } catch (error) {
        console.error("PDF 生成失敗", error);
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <div>
        <div
            class="mb-6 flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p
                    class="text-sm font-medium uppercase tracking-[0.22em] text-slate-500"
                >
                    報表中心
                </p>
                <h1 class="mt-2 text-2xl font-bold">日報表</h1>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <DatePicker v-model="selectedDate" class="w-40" />
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading || !selectedDate"
                    @click="goToPreviousDay"
                >
                    前一天
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading || !selectedDate"
                    @click="goToNextDay"
                >
                    後一天
                </button>

                <!-- <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="generating || !reportData"
                    @click="generatePDF"
                >
                    {{ generating ? "生成中..." : "匯出 PDF" }}
                </button> -->
            </div>
        </div>

        <div
            v-if="loading"
            class="flex items-center justify-center rounded-2xl bg-white p-12 shadow-sm ring-1 ring-slate-200"
        >
            <p class="text-slate-500">載入報告中...</p>
        </div>

        <div
            v-else-if="!reportData"
            class="flex items-center justify-center rounded-2xl bg-white p-12 shadow-sm ring-1 ring-slate-200"
        >
            <p class="text-slate-500">請選擇日期並載入報告</p>
        </div>

        <div
            v-else
            class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200"
        >
            <h2 class="mb-4 text-center text-xl font-bold">
                協同中學 臨時車票販售日報表
            </h2>
            <div class="mb-4 grid grid-cols-3 items-center">
                <!-- 左：日期 -->
                <div class="text-left font-semibold text-slate-700">
                    日期：{{ selectedDate }}
                </div>

                <!-- 中：標題 -->
                <div class="text-center">
                    <p class="font-semibold text-slate-700">
                        總購次：{{ reportData.total_tickets }}
                    </p>
                </div>

                <!-- 右：總金額 -->
                <div class="text-right">
                    <p class="text-lg font-bold text-slate-800">
                        總金額：{{ totalAmount }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8">
                <!-- 嘉義縣市車別 -->
                <div>
                    <div>
                        <div
                            v-for="vehicle in categorizedVehicles.chiayi"
                            :key="vehicle.vehicle"
                            class="mb-6"
                        >
                            <h4
                                class="flex items-center justify-between font-semibold text-slate-700 mb-1 px-2"
                            >
                                <div>{{ vehicle.vehicle }}</div>
                                <div>{{ vehicle.total_amount }}</div>
                            </h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-left"
                                        >
                                            站別
                                        </th>
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-right w-12"
                                        >
                                            購次
                                        </th>
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-right w-12"
                                        >
                                            小計
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            stat, index
                                        ) in vehicle.stationStats"
                                        :key="index"
                                        class="border border-slate-200"
                                    >
                                        <td class="px-2 py-0.5 border">
                                            {{ stat.station }}
                                        </td>
                                        <td
                                            class="px-2 py-0.5 text-right border"
                                        >
                                            {{ stat.count }}
                                        </td>
                                        <td
                                            class="px-2 py-0.5 text-right border"
                                        >
                                            {{ stat.amount }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 其他車別 -->
                <div>
                    <div>
                        <div
                            v-for="vehicle in categorizedVehicles.others"
                            :key="vehicle.vehicle"
                            class="mb-6"
                        >
                            <h4
                                class="flex items-center justify-between font-semibold text-slate-700 mb-1 px-2"
                            >
                                <span>{{ vehicle.vehicle }}</span>
                                <span>{{ vehicle.total_amount }}</span>
                            </h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-left"
                                        >
                                            站別
                                        </th>
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-right w-12"
                                        >
                                            購次
                                        </th>
                                        <th
                                            class="border border-slate-200 px-2 py-0.5 text-right w-12"
                                        >
                                            小計
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            stat, index
                                        ) in vehicle.stationStats"
                                        :key="index"
                                        class="border border-slate-200"
                                    >
                                        <td class="px-2 py-0.5 border">
                                            {{ stat.station }}
                                        </td>
                                        <td
                                            class="px-2 py-0.5 text-right border"
                                        >
                                            {{ stat.count }}
                                        </td>
                                        <td
                                            class="px-2 py-0.5 text-right border"
                                        >
                                            {{ stat.amount }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
