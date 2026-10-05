<script setup>
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import {
    AlertTriangle,
    Check,
    CircleAlert,
    Ticket,
    Trash2,
} from "lucide-vue-next";

import StudentSelector from "@/components/StudentSelector.vue";
import StationSelector from "@/components/StationSelector.vue";
import Tickets from "@/components/Tickets.vue";
import TripActionGroup from "@/components/TripActionGroup.vue";
import TripDateSelector from "@/components/TripDateSelector.vue";
import TripPeriodButtonGroup from "@/components/TripPeriodButtonGroup.vue";
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

const createBlankRow = () => ({
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    student: null,
    station: null,
    date: formatDateValue(new Date()),
    periods: ["放學"],
});

const addedRows = ref([]);
const addOptions = ref({
    sameDate: false,
    fixedWeekday: false,
    incrementDate: true,
    skipWeekend: true,
});
const toastMessage = ref("");
const toastType = ref("success");
const toastVisible = ref(false);
let toastTimeout = null;

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
        previewVisible.value = true;
        showToast(`已儲存 ${data.saved ?? tickets.length} 筆資料`, "success");
        resetToFreshRow();
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            error?.response?.data?.error ||
            "儲存失敗";

        showToast(message, "error");
    }
};

const savedTickets = ref([]);
const previewVisible = ref(false);

const openPreview = () => {
    if (savedTickets.value.length) {
        previewVisible.value = true;
    }
};

const closePreview = () => {
    previewVisible.value = false;
};

const printPreview = () => {
    // 關閉預覽後直接列印
    closePreview();
    window.print();
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

    if (!periodValues.length) {
        return null;
    }

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
    if (!row.date || !row.periods?.length || !row.station) {
        return "disabled";
    }

    const effectiveRemaining = getEffectiveRemainingForRow(row, rows);

    if (effectiveRemaining === null) {
        return "valid";
    }

    return effectiveRemaining < 0 ? "invalid" : "valid";
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

function applyBatchAddSequentially(row, count) {
    const addCount = Number.isFinite(Number(count))
        ? Math.max(1, Number(count))
        : 1;
    const generatedRows = [];
    const baseRows = [...addedRows.value];
    let lastGeneratedDate = baseRows.at(-1)?.date ?? null;

    for (let step = 0; step < addCount; step += 1) {
        const nextRow = cloneRowTemplate(row);
        nextRow.date = getGeneratedDateForIndex(
            row.date,
            step,
            lastGeneratedDate,
        );

        const candidateRows = [...baseRows, ...generatedRows, nextRow];
        const nextState = rowCheckState(nextRow, candidateRows);
        const { invalidCount } = getStateSummary(candidateRows);

        console.log("[ticket-check]", {
            index: step,
            date: nextRow.date,
            vehicle: nextRow.station?.vehicle ?? "未知車別",
            periods: nextRow.periods,
            nextState,
            invalidCount,
        });

        if (
            shouldShowAddLimitToast(candidateRows, (item) =>
                rowCheckState(item, candidateRows),
            )
        ) {
            showToast("已達 2 筆負餘票，無法再新增", "error");
            break;
        }

        generatedRows.push(nextRow);
        lastGeneratedDate = nextRow.date;

        if (nextState !== "valid") {
            showToast(
                `${nextRow.date} ${nextRow.periods.join(" / ")} ${nextRow.station?.vehicle ?? "未知車別"} 已加入，但需確認剩餘票`,
                "warning",
            );
        }
    }

    if (!generatedRows.length) {
        return;
    }

    addedRows.value = [...baseRows, ...generatedRows];

    const firstRow = generatedRows[0];
    const periodText = firstRow.periods.join(" / ");
    const vehicleText = firstRow.station?.vehicle ?? "未知車別";
    showToast(
        `${firstRow.date} ${periodText} ${vehicleText} 已加入 ${generatedRows.length} 筆`,
        "success",
    );
}

function addSelectedRows(count) {
    const invalidLimitTriggered = shouldShowAddLimitToast(
        addedRows.value,
        (item) => rowCheckState(item, addedRows.value),
    );

    if (invalidLimitTriggered) {
        showToast("已達 2 筆負餘票，無法再新增", "error");
        return;
    }

    const row = currentDraftRow.value;

    if (
        !row ||
        !row.student ||
        !row.station ||
        !row.date ||
        !row.periods?.length
    ) {
        return;
    }

    if (
        !canAllowDraftAdd(row, addedRows.value, (item) =>
            rowCheckState(item, addedRows.value),
        )
    ) {
        showToast("票數不足，請先修正後再繼續操作!", "error");
        return;
    }

    applyBatchAddSequentially(row, count);
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

        <!-- ========================================================= -->
        <!-- Header -->
        <!-- ========================================================= -->

        <header class="shrink-0 border-b">
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

        <div class="flex min-h-0 w-full flex-1">
            <div class="flex min-h-0 w-1/2 flex-col">
                <main class="flex-1 overflow-auto">
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
                                        v-model="row.station"
                                        v-model:selected-periods="row.periods"
                                        :selected-date="row.date"
                                        :selected-rows="addedRows"
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

                <footer class="mt-auto shrink-0 border-t bg-background">
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
                                    size="sm"
                                    @click="addSelectedRows(14)"
                                >
                                    新增 14 筆
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addSelectedRows(21)"
                                >
                                    新增 21 筆
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
                            </div>
                        </div>
                    </div>
                </footer>
            </div>

            <div class="relative w-1/2 border-l bg-muted/20 p-4">
                <div
                    v-if="validRows.length"
                    class="absolute top-4 right-4 w-72"
                >
                    <div
                        class="rounded-lg border bg-background/90 p-3 shadow-sm backdrop-blur-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                購票價格
                            </span>
                            <span class="text-xs font-semibold text-foreground">
                                總計 {{ formatCurrency(totalTicketAmount) }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="item in studentPriceSummary"
                                :key="item.student"
                                class="flex items-center justify-between gap-3 text-sm"
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

        <div v-if="savedTickets.length" class="border-t bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-foreground">
                    臨時車票預覽
                </h2>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="openPreview"
                    >
                        檢視預覽
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="previewVisible && savedTickets.length"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div
                class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
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
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="printPreview"
                        >
                            直接列印
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

                <div class="flex-1 overflow-auto bg-slate-100 p-4">
                    <Tickets :tickets="savedTickets" />
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- Footer -->
        <!-- ========================================================= -->
    </div>
</template>
