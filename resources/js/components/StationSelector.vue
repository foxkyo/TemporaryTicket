<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { Lock, Check, ChevronRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps({
    modelValue: {
        type: Object,
        default: null,
    },
    selectedPeriods: {
        type: Array,
        default: () => [],
    },
    selectedDate: {
        type: String,
        default: '',
    },
    selectedRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'update:selectedPeriods']);

const open = ref(false);
const loading = ref(false);
const stationAreas = ref([]);

const activePeriods = computed({
    get: () => props.selectedPeriods ?? [],
    set: (value) => {
        emit('update:selectedPeriods', value);
    },
});

const selectedArea = ref(null);
const selectedVehicle = ref(null);
const selectedStation = ref(null);

function normalizePeriod(data) {
    const safeData = data ?? {};

    return {
        student_count: Number(safeData.student_count ?? safeData.學生人數 ?? 0),
        temporary_ticket_count: Number(
            safeData.temporary_ticket_count ?? safeData.臨時票數 ?? 0,
        ),
        total: Number(safeData.total ?? 0),
        remaining: Number(safeData.remaining ?? 0),
        status: safeData.status ?? 'normal',
        管制: Boolean(safeData.管制 ?? false),
    };
}

function normalizeStation(station) {
    return {
        id: station?.id ?? null,
        station: station?.station ?? station?.站別 ?? '',
        boarding: station?.boarding ?? station?.乘車處 ?? '',
        one_way_amount: Number(
            station?.one_way_amount ?? station?.單趟金額 ?? 0,
        ),
        one_month_amount: Number(
            station?.one_month_amount ?? station?.單月金額 ?? 0,
        ),
        vehicle: station?.vehicle ?? station?.車別 ?? null,
        ...station,
    };
}

function normalizeVehicle(vehicle) {
    const normalized = {
        vehicle: vehicle?.vehicle ?? vehicle?.車別 ?? '',
        capacity: Number(vehicle?.capacity ?? 0),
        stations: Array.isArray(vehicle?.stations)
            ? vehicle.stations.map(normalizeStation)
            : [],
        上學: normalizePeriod(vehicle?.上學),
        放學: normalizePeriod(vehicle?.放學),
    };

    return {
        ...vehicle,
        ...normalized,
    };
}

function normalizeArea(area) {
    return {
        area: area?.area ?? area?.地區 ?? '',
        vehicles: Array.isArray(area?.vehicles)
            ? area.vehicles.map(normalizeVehicle)
            : [],
    };
}

async function loadStations() {
    loading.value = true;

    try {
        const response = await axios.get('/api/stations');
        const payload = Array.isArray(response.data) ? response.data : [];

        stationAreas.value = payload.map(normalizeArea);
    } catch (error) {
        console.error('取得站別資料失敗：', error);
        stationAreas.value = [];
    } finally {
        loading.value = false;
    }
}

async function openSelector() {
    open.value = true;

    if (!stationAreas.value.length) {
        await loadStations();
    }

    initializeSelection();

    if (!selectedArea.value) {
        const fallbackArea = stationAreas.value.find(
            (area) => area.area === '嘉義縣市',
        );

        if (fallbackArea) {
            selectArea(fallbackArea);
        }
    }
}

function initializeSelection() {
    selectedArea.value = null;
    selectedVehicle.value = null;
    selectedStation.value = null;

    if (!props.modelValue?.id) {
        return;
    }

    for (const area of stationAreas.value) {
        const vehicle = area.vehicles?.find((item) =>
            item.stations?.some(
                (station) => station.id === props.modelValue.id,
            ),
        );

        if (!vehicle) {
            continue;
        }

        selectedArea.value = area;
        selectedVehicle.value = vehicle;
        selectedStation.value = vehicle.stations.find(
            (station) => station.id === props.modelValue.id,
        );
        break;
    }
}

function togglePeriod(period) {
    if (activePeriods.value.includes(period)) {
        activePeriods.value = activePeriods.value.filter(
            (item) => item !== period,
        );
    } else {
        activePeriods.value = [...activePeriods.value, period];
    }

    if (selectedVehicle.value && !vehicleAvailable(selectedVehicle.value)) {
        selectedVehicle.value = null;
        selectedStation.value = null;
    }
}

function getVehiclePeriodData(vehicle, period) {
    return period === '上學' ? vehicle?.上學 : vehicle?.放學;
}

function getSelectedUsageForVehicle(vehicle, period) {
    const rows = Array.isArray(props.selectedRows) ? props.selectedRows : [];
    const targetDate = props.selectedDate ?? '';

    return rows.reduce((count, row) => {
        if (!row || !row.date || !row.periods?.length || !row.station) {
            return count;
        }

        if (row.date !== targetDate) {
            return count;
        }

        if (row.station?.vehicle !== vehicle?.vehicle) {
            return count;
        }

        if (!row.periods.includes(period)) {
            return count;
        }

        return count + 1;
    }, 0);
}

function getAdjustedPeriodRemaining(vehicle, period) {
    const data = getVehiclePeriodData(vehicle, period);
    const baseRemaining = Number(data?.remaining ?? 0);
    const selectedUsage = getSelectedUsageForVehicle(vehicle, period);

    return Math.max(baseRemaining - selectedUsage, 0);
}

function vehicleAvailable(vehicle) {
    if (!activePeriods.value.length) {
        return false;
    }

    return activePeriods.value.every((period) => {
        const data = getVehiclePeriodData(vehicle, period);

        if (!data) {
            return false;
        }

        if (data.管制) {
            return false;
        }

        if (data.total >= vehicle.capacity) {
            return false;
        }

        return getAdjustedPeriodRemaining(vehicle, period) > 0;
    });
}

function vehicleRemainingTickets(vehicle) {
    if (!activePeriods.value.length) {
        return null;
    }

    const values = activePeriods.value
        .map((period) => getAdjustedPeriodRemaining(vehicle, period))
        .filter((value) => value !== null);

    if (!values.length) {
        return null;
    }

    return Math.min(...values);
}

function vehicleStatus(vehicle, period) {
    const data = getVehiclePeriodData(vehicle, period);

    if (!data) {
        return 'normal';
    }

    if (data.total > vehicle.capacity) {
        return 'danger';
    }

    if (vehicle.capacity - data.total <= 3) {
        return 'warning';
    }

    return 'normal';
}

function vehicleStatusClass(vehicle, period) {
    const status = vehicleStatus(vehicle, period);

    if (status === 'danger') {
        return 'text-red-600 dark:text-red-400';
    }

    if (status === 'warning') {
        return 'text-yellow-600 dark:text-yellow-400';
    }

    return 'text-muted-foreground';
}

function selectArea(area) {
    selectedArea.value = area;
    selectedVehicle.value = null;
    selectedStation.value = null;
}

function selectVehicle(vehicle) {
    if (!vehicleAvailable(vehicle)) {
        return;
    }

    selectedVehicle.value = vehicle;
    selectedStation.value = null;
}

function selectStation(station) {
    if (!selectedVehicle.value || !activePeriods.value.length) {
        return;
    }

    selectedStation.value = station;

    emit('update:modelValue', {
        ...station,
        periods: [...activePeriods.value],
        vehicle: selectedVehicle.value.vehicle,
        capacity: selectedVehicle.value.capacity,
        area: selectedArea.value?.area ?? null,
        上學: selectedVehicle.value.上學,
        放學: selectedVehicle.value.放學,
    });

    open.value = false;
}

function formatPrice(amount) {
    const value = Number(amount ?? 0);

    return `NT$ ${value.toLocaleString('zh-TW')}`;
}

const selectedLabel = computed(() => {
    if (!props.modelValue) {
        return '選擇站別';
    }

    const periods = Array.isArray(props.modelValue.periods)
        ? props.modelValue.periods.join('＋')
        : '';

    const station = props.modelValue.station ?? props.modelValue.站別 ?? '';
    const vehicle = props.modelValue.vehicle ?? '';

    // if (periods && station) {
    //     return `${vehicle}・${station}`;
    // }

    if (vehicle && station) {
        return `${vehicle}・${station}`;
    }

    return station || '選擇站別';
});

const canSelectStation = computed(() => {
    return activePeriods.value.length > 0 && selectedVehicle.value !== null;
});

watch(
    () => props.modelValue,
    () => {
        if (open.value) {
            initializeSelection();

            if (!selectedArea.value) {
                const fallbackArea = stationAreas.value.find(
                    (area) => area.area === '嘉義縣市',
                );

                if (fallbackArea) {
                    selectArea(fallbackArea);
                }
            }
        }
    },
    { deep: true },
);
</script>

<template>
    <Button
        type="button"
        variant="outline"
        class="w-64 justify-between"
        @click="openSelector"
    >
        <span class="truncate text-left">
            {{ selectedLabel }}
        </span>

        <ChevronRight class="ml-2 size-4 shrink-0 opacity-50" />
    </Button>

    <Dialog v-model:open="open">
        <DialogContent
            class="!h-[90vh] !max-h-[90vh] !w-[90vw] !max-w-[90vw] p-0"
        >
            <DialogHeader class="px-6 pt-6 pb-4">
                <DialogTitle> 選擇乘車站別 </DialogTitle>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 overflow-hidden px-6 pb-6">
                <div
                    v-if="loading"
                    class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
                >
                    載入中...
                </div>

                <div v-else class="flex h-[100vh] w-full overflow-x-auto">
                    <!-- 區域 -->
                    <div class="w-36 shrink-0 border-r px-3">
                        <div class="mb-3 text-sm font-medium">地區</div>

                        <div class="space-y-1">
                            <Button
                                v-for="area in stationAreas"
                                :key="area.area"
                                type="button"
                                variant="ghost"
                                class="w-full justify-start"
                                :class="
                                    selectedArea?.area === area.area
                                        ? 'bg-accent'
                                        : ''
                                "
                                @click="selectArea(area)"
                            >
                                {{ area.area }}
                            </Button>
                        </div>
                    </div>

                    <!-- 車別 -->
                    <div class="w-64 shrink-0 border-r px-3">
                        <div class="mb-3 text-sm font-medium">車別</div>

                        <div
                            v-if="!activePeriods.length"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            請先選擇時段
                        </div>

                        <div
                            v-else-if="!selectedArea"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            請選擇地區
                        </div>

                        <div v-else class="space-y-2">
                            <button
                                v-for="vehicle in selectedArea.vehicles"
                                :key="vehicle.vehicle"
                                type="button"
                                class="w-full rounded-lg border px-2 py-1 text-left transition-colors"
                                :class="[
                                    vehicleAvailable(vehicle)
                                        ? selectedVehicle?.vehicle ===
                                          vehicle.vehicle
                                            ? 'border-primary bg-primary/10'
                                            : 'hover:bg-accent'
                                        : 'cursor-not-allowed opacity-50',
                                ]"
                                :disabled="!vehicleAvailable(vehicle)"
                                @click="selectVehicle(vehicle)"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="font-medium">
                                        {{ vehicle.vehicle }}
                                    </span>

                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="activePeriods.length"
                                            class="text-xs font-medium tabular-nums"
                                            :class="
                                                vehicleRemainingTickets(
                                                    vehicle,
                                                ) === 0
                                                    ? 'text-red-500'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            剩
                                            {{
                                                vehicleRemainingTickets(
                                                    vehicle,
                                                ) ?? 0
                                            }}
                                            票
                                        </span>

                                        <Lock
                                            v-if="!vehicleAvailable(vehicle)"
                                            class="size-4 shrink-0 text-muted-foreground"
                                        />
                                    </div>
                                </div>

                                <div class="mt-2 space-y-1 text-xs">
                                    <div
                                        v-if="activePeriods.includes('上學')"
                                        class="flex items-center justify-between"
                                    >
                                        <span>上學</span>

                                        <span
                                            :class="
                                                vehicleStatusClass(
                                                    vehicle,
                                                    '上學',
                                                )
                                            "
                                        >
                                            {{ vehicle.上學.total }}
                                            /
                                            {{ vehicle.capacity }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="activePeriods.includes('放學')"
                                        class="flex items-center justify-between"
                                    >
                                        <span>放學</span>

                                        <span
                                            :class="
                                                vehicleStatusClass(
                                                    vehicle,
                                                    '放學',
                                                )
                                            "
                                        >
                                            {{ vehicle.放學.total }}
                                            /
                                            {{ vehicle.capacity }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        activePeriods.some((period) =>
                                            period === '上學'
                                                ? vehicle.上學?.管制
                                                : vehicle.放學?.管制,
                                        )
                                    "
                                    class="mt-2"
                                >
                                    <Badge
                                        variant="destructive"
                                        class="text-xs"
                                    >
                                        停賣
                                    </Badge>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- 站別 -->
                    <div class="min-w-[360px] flex-1 px-3">
                        <div class="mb-3 text-sm font-medium">站別</div>

                        <div
                            v-if="!canSelectStation"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            請先選擇時段及車別
                        </div>

                        <div
                            v-else-if="!selectedVehicle?.stations?.length"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            目前沒有可選站別
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <button
                                v-for="station in selectedVehicle.stations"
                                :key="station.id"
                                type="button"
                                class="flex min-h-16 w-full items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left transition-colors hover:bg-accent"
                                :class="
                                    selectedStation?.id === station.id
                                        ? 'border-primary bg-primary/10'
                                        : ''
                                "
                                @click="selectStation(station)"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">
                                        {{ station.station }}
                                    </div>

                                    <div
                                        v-if="station.boarding"
                                        class="mt-1 truncate text-xs text-muted-foreground"
                                    >
                                        {{ station.boarding }}
                                    </div>
                                </div>

                                <!-- 價格：按鈕內靠右 -->
                                <div
                                    class="shrink-0 text-right text-sm font-semibold tabular-nums"
                                >
                                    {{ formatPrice(station.one_way_amount) }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
