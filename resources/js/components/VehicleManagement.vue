<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, computed } from "vue";
import axios from "axios";
import { AlertTriangle, Check, CircleAlert } from "lucide-vue-next";
import Switch from "@/components/ui/switch/Switch.vue";

type VehicleData = {
    id?: number;
    vehicle: string;
    車別: string;
    搭車人數上限: number;
    上學管制: boolean;
    放學管制: boolean;
    上學搭車人數: number;
    放學搭車人數: number;
    已繳款人數: number;
    未繳款人數: number;
};

type NumberField = "搭車人數上限" | "上學搭車人數" | "放學搭車人數";
type ControlField = "上學管制" | "放學管制";
type ToastType = "success" | "error" | "warning";

declare global {
    interface Window {
        XLSX?: any;
    }
}

const vehicles = ref<VehicleData[]>([]);
const loading = ref(true);
const importMessage = ref("");
const importBusy = ref(false);
const saving = ref<Record<string, boolean>>({});

// ===== Toast（頁面內建） =====
const toastMessage = ref("");
const toastType = ref<ToastType>("success");
const toastVisible = ref(false);
let toastTimeout: ReturnType<typeof setTimeout> | null = null;

const showToast = (message: string, type: ToastType = "success") => {
    toastMessage.value = message;
    toastType.value = type;
    toastVisible.value = true;

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
        toastMessage.value = "";
        toastType.value = "success";
    }, 2500);
};

onBeforeUnmount(() => {
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }
});

// 將車別分為嘉義縣市和其他
const categorizedVehicles = computed(() => {
    const chiayi = vehicles.value.filter((v) => v.vehicle.startsWith("嘉"));
    const others = vehicles.value.filter((v) => !v.vehicle.startsWith("嘉"));
    return { chiayi, others };
});

const sortVehiclesById = (list: VehicleData[]) => {
    return [...list].sort(
        (left, right) => Number(left.id ?? 0) - Number(right.id ?? 0),
    );
};

// 統一轉換布林值（相容 true/false、1/0、"1"/"0"、"true"/"false"、"是"/"否"）
const toBool = (value: unknown): boolean => {
    if (typeof value === "boolean") return value;
    if (typeof value === "number") return value === 1;
    const str = String(value ?? "")
        .trim()
        .toLowerCase();
    return str === "是" || str === "true" || str === "1" || str === "yes";
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

    const capacity =
        data["人數上限"] ??
        data["搭車人數上限"] ??
        data["capacity"] ??
        data["人數"] ??
        data["count"] ??
        0;

    const morningControl = data["上學管制"] ?? false;
    const afternoonControl = data["放學管制"] ?? false;

    const morningValue =
        data["上學人數"] ??
        data["上學搭車人數"] ??
        data["上學"] ??
        data["morning"] ??
        data["上午"] ??
        0;

    const afternoonValue =
        data["放學人數"] ??
        data["放學搭車人數"] ??
        data["放學"] ??
        data["afternoon"] ??
        data["下午"] ??
        0;

    return {
        車別: vehicle,
        搭車人數上限: Math.max(0, Math.min(99, Number(capacity || 0))),
        上學管制: toBool(morningControl),
        放學管制: toBool(afternoonControl),
        上學搭車人數: Math.max(0, Math.min(99, Number(morningValue || 0))),
        放學搭車人數: Math.max(0, Math.min(99, Number(afternoonValue || 0))),
        已繳款人數: 0,
        未繳款人數: 0,
    };
};

const fetchVehicles = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get<VehicleData[]>(
            "/api/vehicle-management",
        );
        vehicles.value = sortVehiclesById(
            data.map((vehicle) => ({
                ...vehicle,
                id: Number(vehicle.id ?? 0),
                搭車人數上限: Math.min(99, Number(vehicle.搭車人數上限 ?? 0)),
                上學管制: toBool(vehicle.上學管制),
                放學管制: toBool(vehicle.放學管制),
                上學搭車人數: Math.min(99, Number(vehicle.上學搭車人數 ?? 0)),
                放學搭車人數: Math.min(99, Number(vehicle.放學搭車人數 ?? 0)),
                已繳款人數: Math.min(99, Number(vehicle.已繳款人數 ?? 0)),
                未繳款人數: Math.min(99, Number(vehicle.未繳款人數 ?? 0)),
            })),
        );
    } catch (error) {
        importMessage.value = "載入車輛資料失敗，請稍後再試。";
        showToast("載入車輛資料失敗，請稍後再試。", "error");
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const saveVehicle = async (
    vehicle: VehicleData,
    field?: keyof VehicleData,
    booleanValue?: boolean,
): Promise<boolean> => {
    if (!vehicle.vehicle) {
        return false;
    }

    // 驗證人數上限 >= 上學人數 且 人數上限 >= 放學人數
    if (vehicle.搭車人數上限 < vehicle.上學搭車人數) {
        showToast(
            `${vehicle.vehicle} 人數上限不能小於上學人數 (${vehicle.上學搭車人數})`,
            "error",
        );
        return false;
    }

    if (vehicle.搭車人數上限 < vehicle.放學搭車人數) {
        showToast(
            `${vehicle.vehicle} 人數上限不能小於放學人數 (${vehicle.放學搭車人數})`,
            "error",
        );
        return false;
    }

    saving.value[vehicle.vehicle] = true;

    try {
        const payload = {
            搭車人數上限: Math.max(
                0,
                Math.min(99, Number(vehicle.搭車人數上限 || 0)),
            ),
            上學管制: toBool(vehicle.上學管制),
            放學管制: toBool(vehicle.放學管制),
            上學搭車人數: Math.max(
                0,
                Math.min(99, Number(vehicle.上學搭車人數 || 0)),
            ),
            放學搭車人數: Math.max(
                0,
                Math.min(99, Number(vehicle.放學搭車人數 || 0)),
            ),
        };

        const { data } = await axios.put(
            `/api/vehicle-management/${encodeURIComponent(vehicle.vehicle)}`,
            payload,
        );

        vehicle.搭車人數上限 = Number(
            data.搭車人數上限 ?? vehicle.搭車人數上限,
        );
        vehicle.上學管制 = toBool(data.上學管制 ?? vehicle.上學管制);
        vehicle.放學管制 = toBool(data.放學管制 ?? vehicle.放學管制);
        vehicle.上學搭車人數 = Number(
            data.上學搭車人數 ?? vehicle.上學搭車人數,
        );
        vehicle.放學搭車人數 = Number(
            data.放學搭車人數 ?? vehicle.放學搭車人數,
        );

        // 如果是布林欄位變更，顯示成功 toast
        if (field && booleanValue !== undefined) {
            const controlName = field === "上學管制" ? "上學管制" : "放學管制";
            const status = booleanValue ? "已開啟" : "已關閉";
            showToast(`${vehicle.vehicle} ${controlName} ${status}`, "success");
        } else {
            showToast(`${vehicle.vehicle} 已同步更新。`, "success");
        }

        return true;
    } catch (error) {
        showToast(`${vehicle.vehicle} 更新失敗，請重新整理後再試。`, "error");
        console.error(error);
        return false;
    } finally {
        saving.value[vehicle.vehicle] = false;
    }
};

/**
 * 數字欄位輸入處理（人數上限 / 上學人數 / 放學人數）
 * - 輸入框不使用 v-model，改由這裡統一控制，才能在驗證失敗時正確還原原值
 * - 違反「人數上限 >= 上學人數」或「人數上限 >= 放學人數」時，自動還原並顯示 toast
 */
const handleNumberInput = (
    vehicle: VehicleData,
    field: NumberField,
    event: Event,
) => {
    const el = event.target as HTMLInputElement;
    const originalValue = vehicle[field];

    const raw = Number(el.value || 0);
    const next = Number.isFinite(raw) ? Math.max(0, Math.min(99, raw)) : 0;

    const revert = () => {
        vehicle[field] = originalValue;
        el.value = String(originalValue);
    };

    // 以「修改後」的數值進行驗證
    const capacity = field === "搭車人數上限" ? next : vehicle.搭車人數上限;
    const morning = field === "上學搭車人數" ? next : vehicle.上學搭車人數;
    const afternoon = field === "放學搭車人數" ? next : vehicle.放學搭車人數;

    if (capacity < morning) {
        showToast(
            `${vehicle.vehicle} 人數上限不能小於上學人數 (${morning})`,
            "error",
        );
        revert();
        return;
    }

    if (capacity < afternoon) {
        showToast(
            `${vehicle.vehicle} 人數上限不能小於放學人數 (${afternoon})`,
            "error",
        );
        revert();
        return;
    }

    // 超出範圍被修正（例如輸入 150 -> 99）時，同步輸入框顯示
    if (el.value !== String(next)) {
        el.value = String(next);
    }

    vehicle[field] = next;

    void saveVehicle(vehicle).then((success) => {
        if (!success) {
            // 儲存失敗，恢復原始值
            revert();
        }
    });
};

// 管制開關變更
const handleSwitchChange = (
    vehicle: VehicleData,
    field: ControlField,
    value: boolean,
) => {
    const originalValue = vehicle[field];
    const nextValue = Boolean(value);

    // 值沒有變化（例如兩種事件同時觸發）時不重複儲存
    if (originalValue === nextValue) {
        return;
    }

    vehicle[field] = nextValue;

    void saveVehicle(vehicle, field, nextValue).then((success) => {
        if (!success) {
            // 儲存失敗，恢復原始值（Switch 以 modelValue 綁定，會跟著切回去）
            vehicle[field] = originalValue;
        }
    });
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
    rows: Record<string, string | number | boolean>[],
    filename: string,
) => {
    void loadXlsxLibrary().then(() => {
        const workbook = window.XLSX.utils.book_new();
        const sheet = window.XLSX.utils.json_to_sheet(rows, {
            header: [
                "車別",
                "人數上限",
                "上學管制",
                "放學管制",
                "上學人數",
                "放學人數",
            ],
        });
        window.XLSX.utils.book_append_sheet(workbook, sheet, "車別管理");
        window.XLSX.writeFile(workbook, filename);
    });
};

const exportCurrentVehicles = () => {
    const rows = vehicles.value.map((vehicle) => ({
        車別: vehicle.vehicle,
        人數上限: vehicle.搭車人數上限,
        上學管制: vehicle.上學管制 ? "是" : "否",
        放學管制: vehicle.放學管制 ? "是" : "否",
        上學人數: vehicle.上學搭車人數,
        放學人數: vehicle.放學搭車人數,
    }));

    exportWorkbook(rows, "車別管理.xlsx");
};

const downloadBlankSample = () => {
    const rows = [
        {
            車別: "",
            人數上限: "",
            上學管制: "",
            放學管制: "",
            上學人數: "",
            放學人數: "",
        },
    ];

    exportWorkbook(rows, "車別管理空白範例.xlsx");
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

        const sheetName = workbook.SheetNames.includes("車別管理")
            ? "車別管理"
            : workbook.SheetNames[0];

        const rawRows = window.XLSX.utils.sheet_to_json(
            workbook.Sheets[sheetName],
            {
                defval: "",
                raw: false,
                blankrows: false,
            },
        );

        const rows = rawRows.map((row: Record<string, unknown>) =>
            normalizeWorkbookRow(row),
        );
        const validRows = rows.filter((row: any) => row.車別);

        if (validRows.length === 0) {
            throw new Error("Excel 內容沒有找到車別欄位。");
        }

        await axios.post("/api/vehicle-management/import", {
            rows: validRows,
        });

        showToast(`已匯入 ${validRows.length} 筆車別資料。`, "success");
        await fetchVehicles();
    } catch (error) {
        showToast(
            error instanceof Error ? error.message : "Excel 匯入失敗。",
            "error",
        );
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
    <div>
        <!-- Toast（Teleport 到 body，避免被上層 layout 裁切或遮蓋） -->
        <Teleport to="body">
            <div
                v-if="toastVisible"
                class="pointer-events-none fixed top-4 right-4 z-[100] max-w-sm rounded-md border px-4 py-3 text-sm shadow-lg"
                :class="
                    toastType === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200'
                        : toastType === 'warning'
                          ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/80 dark:text-amber-200'
                          : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/80 dark:text-red-200'
                "
            >
                <div class="flex items-center gap-2">
                    <Check
                        v-if="toastType === 'success'"
                        class="h-4 w-4 shrink-0"
                    />
                    <AlertTriangle
                        v-else-if="toastType === 'warning'"
                        class="h-4 w-4 shrink-0"
                    />
                    <CircleAlert v-else class="h-4 w-4 shrink-0" />
                    <span>{{ toastMessage }}</span>
                </div>
            </div>
        </Teleport>

        <div
            class="mb-6 flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p
                    class="text-sm font-medium uppercase tracking-[0.22em] text-slate-500"
                >
                    車輛設定
                </p>
                <h1 class="mt-2 text-2xl font-bold">車別管理</h1>
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
            v-if="loading"
            class="flex items-center justify-center rounded-2xl bg-white p-12 shadow-sm ring-1 ring-slate-200"
        >
            <p class="text-slate-500">載入車輛資料中...</p>
        </div>

        <div
            v-else-if="vehicles.length === 0"
            class="flex items-center justify-center rounded-2xl bg-white p-12 shadow-sm ring-1 ring-slate-200"
        >
            <p class="text-slate-500">目前沒有車輛資料。</p>
        </div>

        <div v-else class="flex gap-4">
            <!-- 嘉義縣市 -->
            <div class="min-w-0 flex-1 border bg-white">
                <div
                    class="grid grid-cols-[96px_repeat(5,minmax(0,1fr))] items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-center text-sm font-semibold text-slate-700"
                >
                    <div class="text-left">車別</div>
                    <div>人數上限</div>
                    <div>上學人數</div>
                    <div>放學人數</div>
                    <div>上學管制</div>
                    <div>放學管制</div>
                </div>
                <div
                    v-if="categorizedVehicles.chiayi.length === 0"
                    class="p-6 text-sm text-slate-500"
                >
                    無資料
                </div>
                <div v-else>
                    <div
                        v-for="vehicle in categorizedVehicles.chiayi"
                        :key="vehicle.vehicle"
                        class="grid grid-cols-[96px_repeat(5,minmax(0,1fr))] items-center gap-2 border-b border-slate-200 px-3 py-1.5 text-center last:border-b-0"
                    >
                        <div
                            class="truncate text-left font-medium text-slate-800"
                        >
                            {{ vehicle.vehicle }}
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.搭車人數上限"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '搭車人數上限',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.上學搭車人數"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '上學搭車人數',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.放學搭車人數"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '放學搭車人數',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <Switch
                                :model-value="vehicle.上學管制"
                                :checked="vehicle.上學管制"
                                @update:checked="
                                    handleSwitchChange(
                                        vehicle,
                                        '上學管制',
                                        $event,
                                    )
                                "
                                @update:model-value="
                                    handleSwitchChange(
                                        vehicle,
                                        '上學管制',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <Switch
                                :model-value="vehicle.放學管制"
                                :checked="vehicle.放學管制"
                                @update:checked="
                                    handleSwitchChange(
                                        vehicle,
                                        '放學管制',
                                        $event,
                                    )
                                "
                                @update:model-value="
                                    handleSwitchChange(
                                        vehicle,
                                        '放學管制',
                                        $event,
                                    )
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 其他 -->
            <div class="min-w-0 flex-1 border bg-white">
                <div
                    class="grid grid-cols-[96px_repeat(5,minmax(0,1fr))] items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-center text-sm font-semibold text-slate-700"
                >
                    <div class="text-left">車別</div>
                    <div>人數上限</div>
                    <div>上學人數</div>
                    <div>放學人數</div>
                    <div>上學管制</div>
                    <div>放學管制</div>
                </div>
                <div
                    v-if="categorizedVehicles.others.length === 0"
                    class="p-6 text-sm text-slate-500"
                >
                    無資料
                </div>
                <div v-else>
                    <div
                        v-for="vehicle in categorizedVehicles.others"
                        :key="vehicle.vehicle"
                        class="grid grid-cols-[96px_repeat(5,minmax(0,1fr))] items-center gap-2 border-b border-slate-200 px-3 py-1.5 text-center last:border-b-0"
                    >
                        <div
                            class="truncate text-left font-medium text-slate-800"
                        >
                            {{ vehicle.vehicle }}
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.搭車人數上限"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '搭車人數上限',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.上學搭車人數"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '上學搭車人數',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <input
                                :value="vehicle.放學搭車人數"
                                type="number"
                                min="0"
                                max="99"
                                class="w-full max-w-14 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                @input="
                                    handleNumberInput(
                                        vehicle,
                                        '放學搭車人數',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <Switch
                                :model-value="vehicle.上學管制"
                                :checked="vehicle.上學管制"
                                @update:checked="
                                    handleSwitchChange(
                                        vehicle,
                                        '上學管制',
                                        $event,
                                    )
                                "
                                @update:model-value="
                                    handleSwitchChange(
                                        vehicle,
                                        '上學管制',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="flex justify-center">
                            <Switch
                                :model-value="vehicle.放學管制"
                                :checked="vehicle.放學管制"
                                @update:checked="
                                    handleSwitchChange(
                                        vehicle,
                                        '放學管制',
                                        $event,
                                    )
                                "
                                @update:model-value="
                                    handleSwitchChange(
                                        vehicle,
                                        '放學管制',
                                        $event,
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
