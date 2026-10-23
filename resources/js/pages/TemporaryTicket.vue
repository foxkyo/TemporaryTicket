<script setup>
import axios from "axios";
import { computed, nextTick, onMounted, ref } from "vue";
import {
    AlertTriangle,
    Check,
    CircleAlert,
    Printer,
    Ticket,
    Trash2,
} from "lucide-vue-next";
import { router } from "@inertiajs/vue3";

import StudentSelector from "@/components/StudentSelector.vue";
import StationSelector from "@/components/StationSelector.vue";
import Tickets from "@/components/Tickets.vue";
import TripActionGroup from "@/components/TripActionGroup.vue";
import TripDateSelector from "@/components/TripDateSelector.vue";
import TripPeriodButtonGroup from "@/components/TripPeriodButtonGroup.vue";
import { buildTicketPages } from "@/lib/ticketPageGroups.js";
import {
    canAllowDraftAdd,
    countInvalidRows,
    shouldShowAddLimitToast,
} from "@/lib/ticketRowRules.js";

import { Button } from "@/components/ui/button";

const formatDateValue = (date) => {
    const offset = date.getTimezoneOffset();
    const normalized = new Date(date.getTime() - offset * 60 * 1000);

    return normalized.toISOString().slice(0, 10);
};

const selectedStudent = ref(null);
const selectedStation = ref(null);
const selectedDate = ref(formatDateValue(new Date()));
const selectedPeriods = ref(["放學"]);
const isPrinting = ref(false);
const debugLogs = ref([]);
const showDebugLogs = ref(false);

const createBlankRow = () => ({
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    student: null,
    station: null,
    date: formatDateValue(new Date()),
    periods: ["放學"],
});

const addedRows = ref([]);
const ticketListScroller = ref(null);
const addOptions = ref({
    sameDate: true,
    fixedWeekday: false,
    incrementDate: false,
    skipWeekend: true,
});
const toastMessage = ref("");
const toastType = ref("success");
const toastVisible = ref(false);
let toastTimeout = null;

const SEAT_LIMIT_WARNING = "目前所選的車別或時段票數不足，請重新選擇。";

const showToast = (message, type = "success") => {
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

const debugLog = (...args) => {
    const timestamp = new Date().toLocaleTimeString();
    const message = args
        .map((arg) => {
            if (typeof arg === "object") {
                try {
                    return JSON.stringify(arg, null, 2);
                } catch (e) {
                    return String(arg);
                }
            }
            return String(arg);
        })
        .join(" ");

    debugLogs.value.push(`[${timestamp}] ${message}`);
    console.log(...args); // Still log to console
};

const rejectSeatLimitRow = async (row, message = SEAT_LIMIT_WARNING) => {
    if (!row) {
        return;
    }

    clearCurrentRowStation(row);

    await nextTick();

    showToast(message, "error");
};

const isBlankRow = (row) => {
    return (
        !row?.student &&
        !row?.station &&
        row?.date === formatDateValue(new Date()) &&
        row?.periods?.length === 1 &&
        row?.periods?.[0] === "放學"
    );
};

const resetToFreshRow = () => {
    selectedStudent.value = null;
    selectedStation.value = null;
    selectedDate.value = formatDateValue(new Date());
    selectedPeriods.value = ["放學"];
    addedRows.value = [createBlankRow()];
};

onMounted(() => {
    resetToFreshRow();
});

const studentDisplayName = computed(() => {
    if (!selectedStudent.value) {
        return "";
    }

    return `${selectedStudent.value.班級} ${selectedStudent.value.姓名}`.trim();
});

const stationDisplayName = computed(() => {
    if (!selectedStation.value) {
        return "";
    }

    return `${selectedStation.value.vehicle ?? ""} · ${selectedStation.value.station ?? ""}`.trim();
});

const selectionSummary = computed(() => {
    if (!selectedStudent.value && !selectedStation.value) {
        return "請選擇學生及站別";
    }

    if (!selectedStudent.value) {
        return selectedStation.value.station ?? "請選擇學生及站別";
    }

    if (!selectedStation.value) {
        return studentDisplayName.value;
    }

    return `${studentDisplayName.value} · ${selectedStation.value.station}`;
});

const savedTickets = ref([]);
const previewVisible = ref(false);
const previewUrl = ref("/ticket-print");

const isTauri = computed(() => {
    return typeof window !== "undefined" && window.__TAURI__;
});

const openPreview = () => {
    previewVisible.value = true;
};

const closePreview = () => {
    previewVisible.value = false;
};

const printPreview = () => {
    // 直接列印 /ticket-print 頁面
    showToast("正在開啟列印預覽...", "info");
    window.open("/ticket-print", "_blank");
};

const performSilentPrint = async () => {
    isPrinting.value = true;
    debugLogs.value = []; // Clear previous logs
    showDebugLogs.value = true; // Show debug panel
    try {
        debugLog("開始靜默列印...");
        debugLog("環境檢查 - isTauri:", isTauri.value);

        showToast("正在準備列印...", "info");

        // 檢查是否真的在 Tauri 環境中
        if (!isTauri.value) {
            throw new Error("靜默列印功能僅在 Tauri 桌面應用中可用");
        }

        showToast("正在生成列印內容...", "info");

        // 將票據轉為文本格式 - 使用當前可用的票據
        const ticketsToPrint =
            savedTickets.value.length > 0
                ? savedTickets.value
                : addedRows.value.filter(
                      (row) =>
                          !isBlankRow(row) &&
                          rowCheckState(row, addedRows.value) === "valid",
                  );

        let text = "";
        ticketsToPrint.forEach((ticket, index) => {
            text += `=== 票據 ${index + 1} ===\n`;
            text += `學生: ${ticket.student || ""}\n`;
            text += `站別: ${ticket.station || ""}\n`;
            text += `日期: ${ticket.date || ""}\n`;
            text += `時段: ${ticket.periods?.join(", ") || ""}\n`;
            text += `金額: ${formatPrice(ticket.amount)}\n`;
            text += `條碼: ${ticket.barcode || ""}\n`;
            text += `\n`;
        });

        debugLog("列印內容長度:", text.length);
        debugLog("票據數量:", ticketsToPrint.length);

        if (text.length === 0) {
            throw new Error("沒有可列印的票據");
        }

        // 調用 Rust 靜默列印
        const { invoke } = await import("@tauri-apps/api/core");
        await invoke("silent_print", { text });

        debugLog("靜默列印成功");
        showToast("列印已發送到印表機", "success");
    } catch (error) {
        debugLog("靜默列印失敗:", error);
        const errorMessage = error?.message || String(error);
        debugLog("錯誤詳情:", errorMessage);

        // Fallback 到打開列印視窗
        debugLog("Fallback 到打開列印視窗");
        showToast("靜默列印失敗，改用標準列印", "info");

        const printWindow = window.open(
            "/ticket-print?autoPrint=true",
            "_blank",
        );
        if (printWindow) {
            printWindow.onload = () => {
                setTimeout(() => {
                    printWindow.print();
                }, 1500);
            };
        }
    } finally {
        isPrinting.value = false;
    }
};

const confirmTicket = async () => {
    const tickets = addedRows.value
        .filter((row) => !isBlankRow(row))
        .filter((row) => rowCheckState(row, addedRows.value) === "valid");

    if (!tickets.length || totalTicketAmount.value <= 0) {
        showToast("沒有可儲存的票資料", "error");
        return;
    }

    try {
        const { data } = await axios.post("/api/temporary-tickets", {
            tickets: tickets.map((row) => ({
                student: row.student,
                station: row.station,
                date: row.date,
                periods: row.periods,
                amount: getRowTicketAmount(row),
            })),
        });

        savedTickets.value = data.tickets ?? [];
        showToast(`已儲存 ${data.saved ?? tickets.length} 筆資料`, "success");
        resetToFreshRow();

        // 如果在 Tauri 環境下，嘗試使用靜默列印
        if (isTauri.value) {
            try {
                await performSilentPrint();
            } catch (printError) {
                console.error("靜默列印失敗，fallback 到預覽:", printError);
                // 如果靜默列印失敗，打開預覽
                openPreview();
            }
        } else {
            // 非 Tauri 環境，打開預覽
            openPreview();
        }
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            error?.response?.data?.error ||
            "儲存失敗";

        showToast(message, "error");
    }
};

const resolveAutoAppliedStation = async (ticket) => {
    const fallbackStation = {
        id: ticket?.station?.id ?? null,
        vehicle: ticket?.車別 ?? ticket?.station?.vehicle ?? null,
        station: ticket?.站別 ?? ticket?.station?.station ?? null,
        boarding: ticket?.station?.boarding ?? null,
        one_way_amount: Number(ticket?.station?.one_way_amount ?? 0),
        one_month_amount: Number(ticket?.station?.one_month_amount ?? 0),
        periods: Array.isArray(ticket?.時段) ? [...ticket.時段] : [],
        ...ticket?.station,
    };

    if (!fallbackStation.vehicle || !fallbackStation.station) {
        return fallbackStation;
    }

    try {
        const { data } = await axios.get("/api/stations");
        const stationAreas = Array.isArray(data) ? data : [];

        for (const area of stationAreas) {
            const vehicle = (area?.vehicles ?? []).find(
                (item) => item?.vehicle === fallbackStation.vehicle,
            );

            if (!vehicle) {
                continue;
            }

            const matchedStation = (vehicle?.stations ?? []).find((item) => {
                if (fallbackStation.id != null && item?.id != null) {
                    return Number(item.id) === Number(fallbackStation.id);
                }

                return (
                    String(item?.station ?? "") ===
                        String(fallbackStation.station) &&
                    String(item?.vehicle ?? "") ===
                        String(fallbackStation.vehicle)
                );
            });

            if (matchedStation) {
                return {
                    ...matchedStation,
                    periods: Array.isArray(ticket?.時段)
                        ? [...ticket.時段]
                        : [],
                    vehicle: matchedStation.vehicle ?? fallbackStation.vehicle,
                    area: area?.area ?? null,
                    上學: vehicle.上學,
                    放學: vehicle.放學,
                };
            }
        }
    } catch (error) {
        console.warn("查詢站別資料失敗，使用歷史資料備援", error);
    }

    return fallbackStation;
};

const applyLastStudentTicketPreference = async (row) => {
    if (!row?.student) {
        return;
    }

    try {
        const { data } = await axios.get(
            "/api/temporary-tickets/latest-for-student",
            {
                params: {
                    學號: row.student.學號,
                    班級: row.student.班級,
                    姓名: row.student.姓名,
                },
            },
        );

        const ticket = data?.ticket;

        if (!ticket) {
            return;
        }

        if (Array.isArray(ticket.時段) && ticket.時段.length) {
            row.periods = [...ticket.時段];
        }

        if (ticket.車別 || ticket.站別 || ticket.station) {
            row.station = await resolveAutoAppliedStation(ticket);
        }

        showToast(`已套用歷史購票紀錄`, "success");
    } catch (error) {
        console.warn("套用歷史票資料失敗", error);
    }
};

const handleStudentSelected = async (row, value) => {
    row.student = value;
    await applyLastStudentTicketPreference(row);
};

const handleStationSelected = (row, value) => {
    row.station = value;
    row.selectionState = null;
};

const clearSelection = () => {
    resetToFreshRow();
};

const handleTripCheck = () => {
    console.log({
        date: selectedDate.value,
        periods: selectedPeriods.value,
        vehicle: selectedStation.value?.vehicle,
    });
};

const canConfirm = computed(() => {
    const rows = addedRows.value.filter((row) => !isBlankRow(row));

    if (!rows.length || totalTicketAmount.value <= 0) {
        return false;
    }

    return countInvalidRows(rows, (row) => rowCheckState(row, rows)) === 0;
});

const canCheck = computed(() => {
    return Boolean(
        selectedDate.value &&
        selectedPeriods.value.length &&
        selectedStation.value,
    );
});

const stationCheckState = computed(() => {
    if (
        !selectedDate.value ||
        !selectedPeriods.value.length ||
        !selectedStation.value
    ) {
        return "disabled";
    }

    return "valid";
});

const cloneRowTemplate = (row) => ({
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    student: row.student ? { ...row.student } : null,
    station: row.station ? { ...row.station } : null,
    date: row.date,
    periods: [...row.periods],
});

const getLatestTemplateRow = () => {
    const nonBlankRows = addedRows.value.filter((row) => !isBlankRow(row));

    return nonBlankRows.at(-1) ?? null;
};

const currentDraftRow = computed(() => getLatestTemplateRow());

const getStateSummary = (rows = addedRows.value) => {
    const realRows = rows.filter((item) => !isBlankRow(item));
    const validCount = realRows.filter(
        (item) => rowCheckState(item, rows) === "valid",
    ).length;
    const invalidCount = countInvalidRows(realRows, (item) =>
        rowCheckState(item, rows),
    );

    return { validCount, invalidCount };
};

const canClearSelection = computed(() => {
    return addedRows.value.some((row) => !isBlankRow(row));
});

const validRows = computed(() => {
    return addedRows.value.filter(
        (row) =>
            !isBlankRow(row) && rowCheckState(row, addedRows.value) === "valid",
    );
});

function getRowTicketAmount(row) {
    if (!row?.station || !row?.student) {
        return 0;
    }

    const pricePerTrip = Number(row.station?.one_way_amount ?? 0);
    const tripCount = Array.isArray(row.periods) ? row.periods.length : 0;

    return pricePerTrip * Math.max(tripCount, 1);
}

function getStudentPriceSummary(rows = []) {
    const grouped = new Map();

    rows.forEach((row) => {
        if (!row?.student || rowCheckState(row, rows) !== "valid") {
            return;
        }

        const studentKey =
            `${row.student.班級 ?? ""} ${row.student.姓名 ?? ""}`.trim();
        const studentName = studentKey || "未知學生";
        const subtotal = getRowTicketAmount(row);

        grouped.set(studentName, (grouped.get(studentName) ?? 0) + subtotal);
    });

    return Array.from(grouped.entries()).map(([student, total]) => ({
        student,
        total,
    }));
}

const studentPriceSummary = computed(() => {
    return getStudentPriceSummary(validRows.value);
});

const totalTicketAmount = computed(() => {
    return studentPriceSummary.value.reduce((sum, item) => sum + item.total, 0);
});

function formatCurrency(value) {
    const amount = Number(value ?? 0);

    return `NT$ ${amount.toLocaleString("zh-TW")}`;
}

function getEffectiveRemainingForRow(row, rows = addedRows.value) {
    if (!row.date || !row.periods?.length || !row.station) {
        return null;
    }

    const rowIndex = rows.findIndex((item) => item?.id === row.id);
    const evaluationRows = rowIndex >= 0 ? rows.slice(0, rowIndex + 1) : [row];

    const relevantRows = evaluationRows.filter((item) => {
        if (!item || !item.date || !item.station || !item.periods?.length) {
            return false;
        }

        if (item.id === row.id) {
            return false;
        }

        if (
            item?.selectionState === "reset" ||
            item?.selectionState === "invalid"
        ) {
            return false;
        }

        const hasMissingRemainingData = item.periods.some((period) => {
            const periodData = item.station?.[period];
            const remaining = Number(periodData?.remaining ?? NaN);

            return !periodData || !Number.isFinite(remaining);
        });

        if (hasMissingRemainingData) {
            return false;
        }

        return (
            item.date === row.date &&
            item.station.vehicle === row.station.vehicle &&
            item.periods.some((period) => row.periods.includes(period))
        );
    });

    const periodValues = row.periods.map((period) => {
        const periodData = row.station?.[period];
        const remainingValue = Number(periodData?.remaining ?? NaN);

        if (!periodData || !Number.isFinite(remainingValue)) {
            return null;
        }

        const matchingSelectedCount = relevantRows.reduce((count, item) => {
            if (!item.periods.includes(period)) {
                return count;
            }

            return count + 1;
        }, 0);

        return remainingValue - matchingSelectedCount;
    });

    const finiteValues = periodValues.filter((value) => value !== null);

    if (!finiteValues.length) {
        return null;
    }

    return Math.min(...finiteValues);
}

function rowRemainingValue(row) {
    return getEffectiveRemainingForRow(row, addedRows.value);
}

function rowCheckState(row, rows = addedRows.value) {
    if (row?.selectionState === "reset" || row?.selectionState === "invalid") {
        return "invalid";
    }

    if (!row.date || !row.periods?.length || !row.station) {
        return row?.student || row?.date || row?.periods?.length
            ? "invalid"
            : "disabled";
    }

    const hasMissingRemainingData = row.periods.some((period) => {
        const periodData = row.station?.[period];
        const remaining = Number(periodData?.remaining ?? NaN);

        return !periodData || !Number.isFinite(remaining);
    });

    if (hasMissingRemainingData) {
        return "invalid";
    }

    const effectiveRemaining = getEffectiveRemainingForRow(row, rows);

    if (effectiveRemaining === null) {
        return "valid";
    }

    return effectiveRemaining <= 0 ? "invalid" : "valid";
}

function rowCanCheck(row) {
    return Boolean(
        row.date &&
        row.periods?.length &&
        row.student &&
        row.station &&
        rowCheckState(row) === "valid",
    );
}

function toggleAddOption(key) {
    if (
        key === "sameDate" ||
        key === "fixedWeekday" ||
        key === "incrementDate"
    ) {
        addOptions.value.sameDate = key === "sameDate";
        addOptions.value.fixedWeekday = key === "fixedWeekday";
        addOptions.value.incrementDate = key === "incrementDate";
        return;
    }

    addOptions.value[key] = !addOptions.value[key];
}

function getGeneratedDateForIndex(baseDate, index, lastGeneratedDate = null) {
    const base = new Date(`${baseDate}T00:00:00`);
    const offset = index + 1;
    let candidate = new Date(base);

    if (addOptions.value.sameDate) {
        return formatDateValue(candidate);
    }

    if (addOptions.value.fixedWeekday) {
        candidate.setDate(base.getDate() + offset * 7);
    } else if (addOptions.value.incrementDate) {
        candidate.setDate(base.getDate() + offset);
    }

    const lastDate = lastGeneratedDate
        ? new Date(`${lastGeneratedDate}T00:00:00`)
        : null;

    if (lastDate && candidate <= lastDate) {
        candidate = new Date(lastDate);
        candidate.setDate(candidate.getDate() + 1);
    }

    if (addOptions.value.skipWeekend) {
        while (candidate.getDay() === 0 || candidate.getDay() === 6) {
            candidate.setDate(candidate.getDate() + 1);
        }
    }

    if (lastDate && candidate <= lastDate) {
        candidate = new Date(lastDate);
        candidate.setDate(candidate.getDate() + 1);

        if (addOptions.value.skipWeekend) {
            while (candidate.getDay() === 0 || candidate.getDay() === 6) {
                candidate.setDate(candidate.getDate() + 1);
            }
        }
    }

    return formatDateValue(candidate);
}

function clearCurrentRowStation(row) {
    if (!row) {
        return;
    }

    row.station = null;
    row.selectionState = "reset";
}

function scrollTicketListToBottom() {
    nextTick(() => {
        if (!ticketListScroller.value) {
            return;
        }

        const container = ticketListScroller.value;
        container.scrollTo({
            top: container.scrollHeight,
            behavior: "smooth",
        });
    });
}

function addSingleRow({ suppressToast = false } = {}) {
    const draftRow = currentDraftRow.value;

    if (
        !draftRow ||
        !draftRow.student ||
        !draftRow.date ||
        !draftRow.periods?.length ||
        !draftRow.station
    ) {
        showToast("請先選擇學生、日期、時段及站別", "error");
        return { success: false, seatLimitHit: false };
    }

    if (rowCheckState(draftRow, addedRows.value) === "invalid") {
        rejectSeatLimitRow(draftRow);
        return { success: false, seatLimitHit: false };
    }

    if (
        !canAllowDraftAdd(draftRow, addedRows.value, (item) =>
            rowCheckState(item, addedRows.value),
        )
    ) {
        if (!suppressToast) {
            showToast("已有未完成或無效的票資料，請先修正後再新增", "warning");
        }
        return { success: false, seatLimitHit: false };
    }

    const lastGeneratedDate = addedRows.value.at(-1)?.date ?? draftRow.date;
    const nextDate = getGeneratedDateForIndex(
        draftRow.date,
        0,
        lastGeneratedDate,
    );

    const nextRow = cloneRowTemplate(draftRow);
    nextRow.date = nextDate;

    const candidateRows = [...addedRows.value, nextRow];

    if (rowCheckState(nextRow, candidateRows) === "invalid") {
        nextRow.station = null;
        nextRow.selectionState = null;
        addedRows.value = [...addedRows.value, nextRow];
        scrollTicketListToBottom();
        if (!suppressToast) {
            showToast(
                "票數已達上限，已新增此筆並清空站別，請重新選擇",
                "warning",
            );
        }
        return { success: true, seatLimitHit: true };
    }

    addedRows.value = candidateRows;
    scrollTicketListToBottom();
    if (!suppressToast) {
        showToast("已加入 1 筆", "success");
    }
    return { success: true, seatLimitHit: false };
}

function addSelectedRows(count) {
    const times = Number.isFinite(Number(count))
        ? Math.max(1, Number(count))
        : 1;

    let addedCount = 0;
    let hitLimit = false;

    for (let step = 0; step < times; step += 1) {
        const result = addSingleRow({ suppressToast: times > 1 });

        if (!result.success) {
            break;
        }

        addedCount += 1;

        if (result.seatLimitHit) {
            hitLimit = true;
            break;
        }
    }

    if (times > 1) {
        if (hitLimit) {
            showToast(
                `已加入 ${addedCount} 筆（票數已達上限，已清空最後一筆站別）`,
                "warning",
            );
        } else if (addedCount > 0) {
            showToast(`已加入 ${addedCount} 筆`, "success");
        }
    }
}

function removeAddedRow(rowId) {
    addedRows.value = addedRows.value.filter((row) => row.id !== rowId);
}
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-background">
        <div
            v-if="toastVisible"
            class="pointer-events-none fixed top-4 right-4 z-50 max-w-sm rounded-md border px-4 py-3 text-sm shadow-lg"
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

        <!-- Debug Log Panel -->
        <div
            v-if="showDebugLogs"
            class="fixed bottom-4 right-4 z-50 w-96 max-h-96 overflow-auto rounded-md border border-slate-300 bg-slate-50 p-4 text-xs shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="mb-2 flex items-center justify-between">
                <span class="font-semibold">Debug Logs</span>
                <button
                    @click="showDebugLogs = false"
                    class="rounded px-2 py-1 hover:bg-slate-200 dark:hover:bg-slate-800"
                >
                    關閉
                </button>
            </div>
            <div class="space-y-1 font-mono">
                <div
                    v-for="(log, index) in debugLogs"
                    :key="index"
                    class="whitespace-pre-wrap break-words"
                >
                    {{ log }}
                </div>
                <div v-if="debugLogs.length === 0" class="text-slate-500">
                    無日誌
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- Header -->
        <!-- ========================================================= -->

        <header class="shrink-0 border-b print:hidden">
            <div class="flex h-14 items-center justify-between px-4 md:px-6">
                <div class="flex items-center gap-2">
                    <Ticket class="h-5 w-5" />

                    <h1 class="font-semibold">臨時車票系統</h1>
                </div>
            </div>
        </header>

        <!-- ========================================================= -->
        <!-- Main -->
        <!-- ========================================================= -->

        <div class="flex min-h-0 w-full flex-1 print:hidden">
            <div class="flex min-h-0 w-1/2 flex-col">
                <main ref="ticketListScroller" class="flex-1 overflow-auto">
                    <div class="p-2 md:p-4">
                        <!-- ===================================================== -->
                        <!-- Selected Information -->
                        <!-- ===================================================== -->

                        <div v-if="addedRows.length" class="mt-2 space-y-2">
                            <div
                                v-for="(row, index) in addedRows"
                                :key="row.id"
                                class="p-0"
                            >
                                <div
                                    class="grid items-center gap-2 md:grid-cols-[auto_minmax(140px,1.2fr)_minmax(120px,1fr)_minmax(140px,1fr)_minmax(220px,1.8fr)_auto_auto]"
                                >
                                    <div
                                        class="flex items-center justify-center"
                                    >
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-md border bg-muted/50 text-[11px] font-semibold text-muted-foreground"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <StudentSelector
                                        :model-value="row.student"
                                        @update:modelValue="
                                            (value) =>
                                                handleStudentSelected(
                                                    row,
                                                    value,
                                                )
                                        "
                                    />

                                    <TripDateSelector v-model="row.date" />

                                    <TripPeriodButtonGroup
                                        v-model="row.periods"
                                    />

                                    <StationSelector
                                        :model-value="row.station"
                                        @update:modelValue="
                                            (value) =>
                                                handleStationSelected(
                                                    row,
                                                    value,
                                                )
                                        "
                                        v-model:selected-periods="row.periods"
                                        :selected-date="row.date"
                                        :selected-rows="addedRows"
                                        :current-row-id="row.id"
                                    />

                                    <div class="flex items-end">
                                        <TripActionGroup
                                            :disabled="!rowCanCheck(row)"
                                            :date="row.date"
                                            :periods="row.periods"
                                            :vehicle="row.station?.vehicle"
                                            :remaining="rowRemainingValue(row)"
                                            :check-state="rowCheckState(row)"
                                            :show-delete="addedRows.length > 1"
                                            @check="handleTripCheck"
                                            @remove="removeAddedRow(row.id)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <div class="mt-auto shrink-0 border-t bg-background">
                    <div
                        class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between md:p-4"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex items-center gap-1 rounded-md border border-border bg-muted/30 p-0.5"
                            >
                                <button
                                    v-for="option in [
                                        {
                                            key: 'sameDate',
                                            label: '日期固定',
                                        },
                                        {
                                            key: 'incrementDate',
                                            label: '日期遞增',
                                        },
                                        {
                                            key: 'fixedWeekday',
                                            label: '星期固定',
                                        },
                                    ]"
                                    :key="option.key"
                                    type="button"
                                    class="min-w-[90px] rounded-[6px] px-2.5 py-1.5 text-xs font-medium tracking-wide transition-all"
                                    :class="
                                        addOptions[option.key]
                                            ? 'bg-foreground text-background shadow-[inset_0_1px_0_rgba(255,255,255,0.18)]'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="toggleAddOption(option.key)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <button
                                type="button"
                                class="rounded-md border px-2.5 py-1.5 text-xs font-medium tracking-wide transition-colors"
                                :class="
                                    addOptions.skipWeekend
                                        ? 'border-foreground bg-foreground text-background shadow-[inset_0_1px_0_rgba(255,255,255,0.18)]'
                                        : 'border-muted bg-background text-muted-foreground'
                                "
                                @click="toggleAddOption('skipWeekend')"
                            >
                                避開六日
                            </button>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addSelectedRows(1)"
                                >
                                    新增 1 筆
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addSelectedRows(7)"
                                >
                                    新增 7 筆
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="!canClearSelection"
                                    @click="clearSelection"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />

                                    清除
                                </Button>

                                <Button
                                    type="button"
                                    :disabled="!canConfirm"
                                    @click="confirmTicket"
                                >
                                    <Check class="mr-2 h-4 w-4" />

                                    確定
                                </Button>

                                <Button
                                    v-if="isTauri"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="isPrinting"
                                    @click="performSilentPrint"
                                >
                                    <Printer class="mr-2 h-4 w-4" />
                                    {{
                                        isPrinting
                                            ? "列印中..."
                                            : "Rust 靜默列印"
                                    }}
                                </Button>

                                <Button
                                    v-if="savedTickets.length"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="isPrinting"
                                    @click="openPreview"
                                >
                                    <Ticket class="mr-2 h-4 w-4" />
                                    預覽
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative w-1/2 border-l bg-muted/20 p-4">
                <div
                    v-if="validRows.length"
                    class="absolute top-4 right-4 w-72"
                >
                    <div
                        class="rounded-lg border bg-background/90 p-3 shadow-sm backdrop-blur-sm"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                            >
                                購票價格
                            </span>
                            <span class="font-semibold text-foreground">
                                總計 {{ formatCurrency(totalTicketAmount) }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="item in studentPriceSummary"
                                :key="item.student"
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="truncate text-foreground">
                                    {{ item.student }}
                                </span>
                                <span class="shrink-0 font-medium tabular-nums">
                                    {{ formatCurrency(item.total) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex h-full items-center justify-center text-sm text-muted-foreground"
                >
                    尚未有可計算票價
                </div>
            </div>
        </div>

        <div
            v-if="previewVisible && savedTickets.length"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div
                class="flex h-[90vh] w-full max-w-90 flex-col overflow-hidden rounded-xl border bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3 shrink-0"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            臨時車票預覽
                        </h3>
                        <p class="text-sm text-slate-500">
                            共 {{ savedTickets.length }} 筆
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="isTauri"
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="isPrinting"
                            @click="performSilentPrint"
                        >
                            {{ isPrinting ? "列印中..." : "Rust 靜默列印" }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="closePreview"
                        >
                            關閉預覽
                        </Button>
                    </div>
                </div>

                <div class="flex-1 overflow-auto bg-slate-100">
                    <iframe
                        v-if="previewUrl"
                        :src="previewUrl"
                        class="w-full h-full border-0"
                        frameborder="0"
                    ></iframe>
                </div>
            </div>
        </div>

        <!-- 列印專用區域 -->
        <div v-if="savedTickets.length" class="hidden print:block p-4">
            <Tickets :tickets="savedTickets" />
        </div>

        <!-- ========================================================= -->
        <!-- Footer -->
        <!-- ========================================================= -->
    </div>
</template>
