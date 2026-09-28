<script setup>
import { computed, onMounted, ref } from "vue";
import { Check, Ticket, Trash2 } from "lucide-vue-next";

import StudentSelector from "@/components/StudentSelector.vue";
import StationSelector from "@/components/StationSelector.vue";
import TripActionGroup from "@/components/TripActionGroup.vue";
import TripDateSelector from "@/components/TripDateSelector.vue";
import TripPeriodButtonGroup from "@/components/TripPeriodButtonGroup.vue";
import { canAllowDraftAdd, countInvalidRows } from "@/lib/ticketRowRules.js";

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
const toastMessage = ref("");
const toastVisible = ref(false);
let toastTimeout = null;

const showToast = (message) => {
    toastMessage.value = message;
    toastVisible.value = true;

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
        toastMessage.value = "";
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

const confirmTicket = () => {
    console.log({
        student: selectedStudent.value,
        station: selectedStation.value,
    });
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
    return Boolean(selectedStudent.value && selectedStation.value);
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

const canAddRows = computed(() => {
    const row = currentDraftRow.value;

    if (!row || !canAllowDraftAdd(row, addedRows.value, rowCheckState)) {
        return false;
    }

    const invalidRows = addedRows.value.filter((item) => !isBlankRow(item));

    return countInvalidRows(invalidRows, rowCheckState) < 2;
});

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
        const rawRemaining = Number(row.station?.[period]?.remaining ?? 0);

        const matchingSelectedCount = relevantRows.reduce((count, item) => {
            if (!item.periods.includes(period)) {
                return count;
            }

            return count + 1;
        }, 0);

        return rawRemaining - matchingSelectedCount;
    });

    if (!periodValues.length) {
        return null;
    }

    return Math.min(...periodValues);
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
        return "disabled";
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

function addSelectedRows(count) {
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

    const addCount = Number.isFinite(Number(count))
        ? Math.max(1, Number(count))
        : 1;

    for (let step = 0; step < addCount; step += 1) {
        const nextRow = cloneRowTemplate(row);
        const nextRows = [...addedRows.value, nextRow];
        const nextState = rowCheckState(nextRow, nextRows);
        const { validCount, invalidCount } = getStateSummary(nextRows);

        console.log("[ticket-check]", {
            date: nextRow.date,
            vehicle: nextRow.station?.vehicle ?? "未知車別",
            periods: nextRow.periods,
            validCount,
            invalidCount,
            nextState,
        });

        if (invalidCount >= 2) {
            showToast("已達 2 筆負餘票，無法再新增");
            return;
        }

        if (nextState !== "valid") {
            showToast("目前票數已達負餘票限制，請修正後再新增");
            //return;
        }

        addedRows.value = nextRows;
        const periodText = nextRow.periods.join(" / ");
        const vehicleText = nextRow.station?.vehicle ?? "未知車別";
        showToast(`${nextRow.date} ${periodText} ${vehicleText} 已加入 1 筆`);
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
            class="pointer-events-none fixed top-4 right-4 z-50 max-w-sm rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg dark:border-red-900 dark:bg-red-950/80 dark:text-red-200"
        >
            {{ toastMessage }}
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

        <main class="min-h-0 flex-1 overflow-auto">
            <div class="p-4 md:p-6">
                <div
                    class="mb-3 flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>新增項目</span>
                </div>

                <!-- ===================================================== -->
                <!-- Selected Information -->
                <!-- ===================================================== -->

                <div v-if="addedRows.length" class="mt-2 space-y-2">
                    <div v-for="row in addedRows" :key="row.id" class="p-0">
                        <div
                            class="grid gap-3 md:grid-cols-[1.2fr_1fr_1fr_1.8fr_auto_auto]"
                        >
                            <StudentSelector v-model="row.student" />

                            <TripDateSelector v-model="row.date" />

                            <TripPeriodButtonGroup v-model="row.periods" />

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

        <!-- ========================================================= -->
        <!-- Footer -->
        <!-- ========================================================= -->

        <footer class="shrink-0 border-t bg-background">
            <div
                class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between md:p-4"
            >
                <div class="text-sm text-muted-foreground">
                    <template v-if="selectedStudent || selectedStation">
                        {{ selectionSummary }}
                    </template>

                    <template v-else> 請選擇學生及站別 </template>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!canAddRows"
                        @click="addSelectedRows(1)"
                    >
                        新增 1 筆
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!canAddRows"
                        @click="addSelectedRows(7)"
                    >
                        新增 7 筆
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!canAddRows"
                        @click="addSelectedRows(14)"
                    >
                        新增 14 筆
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!canAddRows"
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
        </footer>
    </div>
</template>
