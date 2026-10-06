<script setup lang="ts">
import { onMounted, ref } from "vue";
import axios from "axios";

type VehicleCount = {
    id?: number;
    vehicle: string;
    車別: string;
    上學人數: number;
    放學人數: number;
};

declare global {
    interface Window {
        XLSX?: any;
    }
}

const vehicles = ref<VehicleCount[]>([]);
const loading = ref(true);
const importMessage = ref("");
const importBusy = ref(false);
const saving = ref<Record<string, boolean>>({});

const sortVehiclesById = (list: VehicleCount[]) => {
    return [...list].sort(
        (left, right) => Number(left.id ?? 0) - Number(right.id ?? 0),
    );
};

const normalizeWorkbookRow = (rawRow: Record<string, unknown>) => {
    const data = Object.entries(rawRow).reduce<Record<string, string | number>>(
        (acc, [key, value]) => {
            const normalizedKey = String(key)
                .trim()
                .replace(/[\s_]+/g, "");
            const normalizedValue =
                value === null || value === undefined
                    ? ""
                    : String(value).trim();

            acc[normalizedKey] = normalizedValue;

            return acc;
        },
        {},
    );

    const vehicle = String(
        data["車別"] ??
            data["vehicle"] ??
            data["車種"] ??
            data["Bus"] ??
            data["車號"] ??
            "",
    ).trim();

    const morningValue =
        data["上學人數"] ??
        data["上學"] ??
        data["morning"] ??
        data["上午"] ??
        data["人數"] ??
        data["count"] ??
        0;

    const afternoonValue =
        data["放學人數"] ??
        data["放學"] ??
        data["afternoon"] ??
        data["下午"] ??
        data["人數"] ??
        data["count"] ??
        0;

    return {
        車別: vehicle,
        上學人數: Math.max(0, Number(morningValue || 0)),
        放學人數: Math.max(0, Number(afternoonValue || 0)),
    };
};

const fetchVehicles = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get<VehicleCount[]>(
            "/api/vehicle-initial-counts",
        );
        vehicles.value = sortVehiclesById(
            data.map((vehicle) => ({
                ...vehicle,
                id: Number(vehicle.id ?? 0),
                上學人數: Number(vehicle.上學人數 ?? 0),
                放學人數: Number(vehicle.放學人數 ?? 0),
            })),
        );
    } catch (error) {
        importMessage.value = "載入車輛人數失敗，請稍後再試。";
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const saveVehicle = async (vehicle: VehicleCount) => {
    if (!vehicle.vehicle) {
        return;
    }

    saving.value[vehicle.vehicle] = true;

    try {
        const payload = {
            上學人數: Math.max(0, Number(vehicle.上學人數 || 0)),
            放學人數: Math.max(0, Number(vehicle.放學人數 || 0)),
        };

        const { data } = await axios.put(
            `/api/vehicle-initial-counts/${encodeURIComponent(vehicle.vehicle)}`,
            payload,
        );

        vehicle.上學人數 = Number(data.上學人數 ?? vehicle.上學人數);
        vehicle.放學人數 = Number(data.放學人數 ?? vehicle.放學人數);
        importMessage.value = `${vehicle.vehicle} 已同步更新。`;
    } catch (error) {
        importMessage.value = `${vehicle.vehicle} 更新失敗，請重新整理後再試。`;
        console.error(error);
    } finally {
        saving.value[vehicle.vehicle] = false;
    }
};

const handleFieldInput = (
    vehicle: VehicleCount,
    field: "上學人數" | "放學人數",
    value: string,
) => {
    const numeric = Number(value || 0);
    vehicle[field] = Number.isFinite(numeric) ? Math.max(0, numeric) : 0;

    void saveVehicle(vehicle);
};

const loadXlsxLibrary = async () => {
    if (window.XLSX) {
        return;
    }

    await new Promise<void>((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            "script[data-xlsx-loader]",
        );

        if (existing) {
            existing.addEventListener("load", () => resolve(), { once: true });
            existing.addEventListener(
                "error",
                () => reject(new Error("xlsx 載入失敗")),
                { once: true },
            );
            return;
        }

        const script = document.createElement("script");
        script.src =
            "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
        script.setAttribute("data-xlsx-loader", "true");
        script.addEventListener("load", () => resolve(), { once: true });
        script.addEventListener(
            "error",
            () => reject(new Error("xlsx 載入失敗")),
            { once: true },
        );
        document.head.appendChild(script);
    });
};

const exportWorkbook = (
    rows: Record<string, string | number>[],
    filename: string,
) => {
    void loadXlsxLibrary().then(() => {
        const workbook = window.XLSX.utils.book_new();
        const sheet = window.XLSX.utils.json_to_sheet(rows, {
            header: ["車別", "上學人數", "放學人數"],
        });
        window.XLSX.utils.book_append_sheet(workbook, sheet, "各車人數");
        window.XLSX.writeFile(workbook, filename);
    });
};

const exportCurrentVehicles = () => {
    const rows = vehicles.value.map((vehicle) => ({
        車別: vehicle.vehicle,
        上學人數: vehicle.上學人數,
        放學人數: vehicle.放學人數,
    }));

    exportWorkbook(rows, "各車初始人數.xlsx");
};

const downloadBlankSample = () => {
    const rows = [{ 車別: "", 上學人數: "", 放學人數: "" }];

    exportWorkbook(rows, "各車初始人數空白範例.xlsx");
};

const handleFileImport = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    importBusy.value = true;
    importMessage.value = "匯入中...";

    try {
        await loadXlsxLibrary();

        const reader = new FileReader();
        const workbook = await new Promise<any>((resolve, reject) => {
            reader.onload = () => {
                try {
                    const arrayBuffer = reader.result as ArrayBuffer;
                    const workbookObject = window.XLSX.read(arrayBuffer, {
                        type: "array",
                    });
                    resolve(workbookObject);
                } catch (error) {
                    reject(error);
                }
            };

            reader.onerror = () => reject(new Error("檔案讀取失敗"));
            reader.readAsArrayBuffer(file);
        });

        const sheetName = workbook.SheetNames.includes("各車人數")
            ? "各車人數"
            : workbook.SheetNames[0];

        const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
            defval: "",
            raw: false,
            blankrows: false,
        });

        const rows = rawRows.map((row: Record<string, unknown>) =>
            normalizeWorkbookRow(row),
        );
        const validRows = rows.filter((row) => row.車別);

        if (validRows.length === 0) {
            throw new Error("Excel 內容沒有找到車別欄位。");
        }

        await axios.post("/api/vehicle-initial-counts/import", {
            rows: validRows,
        });

        importMessage.value = `已匯入 ${validRows.length} 筆車輛人數資料。`;
        await fetchVehicles();
    } catch (error) {
        importMessage.value =
            error instanceof Error ? error.message : "Excel 匯入失敗。";
        console.error(error);
    } finally {
        importBusy.value = false;
        input.value = "";
    }
};

onMounted(() => {
    void fetchVehicles();
});
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-6 text-slate-800">
        <div class="mx-auto max-w-5xl">
            <div
                class="mb-6 flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-medium uppercase tracking-[0.22em] text-slate-500"
                    >
                        車輛設定
                    </p>
                    <h1 class="mt-2 text-2xl font-bold">各車初始人數管理</h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                        @click="exportCurrentVehicles"
                    >
                        匯出 Excel
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="downloadBlankSample"
                    >
                        下載空白範例
                    </button>

                    <label
                        class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-60"
                        :class="{
                            'pointer-events-none opacity-60': importBusy,
                        }"
                    >
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="hidden"
                            @change="handleFileImport"
                            :disabled="importBusy"
                        />
                        {{ importBusy ? "匯入中..." : "匯入 Excel" }}
                    </label>
                </div>
            </div>

            <div
                v-if="importMessage"
                class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
            >
                {{ importMessage }}
            </div>

            <div
                class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
            >
                <div
                    class="grid grid-cols-[1.4fr_1fr_1fr] border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
                >
                    <span>車別</span>
                    <span>上學人數</span>
                    <span>放學人數</span>
                </div>

                <div v-if="loading" class="p-6 text-sm text-slate-500">
                    載入車輛資料中...
                </div>

                <div
                    v-else-if="vehicles.length === 0"
                    class="p-6 text-sm text-slate-500"
                >
                    目前沒有車輛資料。
                </div>

                <div v-else>
                    <div
                        v-for="vehicle in vehicles"
                        :key="vehicle.vehicle"
                        class="grid grid-cols-[1.4fr_1fr_1fr] items-center gap-3 border-b border-slate-200 px-4 py-3 last:border-b-0"
                    >
                        <div class="font-medium text-slate-800">
                            {{ vehicle.vehicle }}
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="vehicle.上學人數"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleFieldInput(
                                        vehicle,
                                        '上學人數',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                            <span
                                v-if="saving[vehicle.vehicle]"
                                class="text-xs text-slate-400"
                                >儲存中...</span
                            >
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="vehicle.放學人數"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleFieldInput(
                                        vehicle,
                                        '放學人數',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
