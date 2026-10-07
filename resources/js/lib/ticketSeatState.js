export function getSelectedUsageForVehicle(
    vehicle,
    rows = [],
    { selectedDate = '', currentRowId = null, period = null } = {},
) {
    return (Array.isArray(rows) ? rows : []).reduce((count, row) => {
        const isCurrentRow = currentRowId != null && row?.id === currentRowId;

        if (row?.selectionState === 'reset') {
            return count;
        }

        if (row?.selectionState === 'invalid' && !isCurrentRow) {
            return count;
        }

        if (!row || !row.date || !row.periods?.length || !row.station) {
            return count;
        }

        if (row.date !== selectedDate) {
            return count;
        }

        if (row.station?.vehicle !== vehicle?.vehicle) {
            return count;
        }

        if (period != null && !row.periods.includes(period)) {
            return count;
        }

        return count + 1;
    }, 0);
}

export function getVehiclePeriodTotal(
    vehicle,
    period,
    rows = [],
    { selectedDate = '', currentRowId = null } = {},
) {
    const data = period === '上學' ? vehicle?.上學 : vehicle?.放學;

    if (!data) {
        return null;
    }

    return (
        Number(data.total ?? 0) +
        getSelectedUsageForVehicle(vehicle, rows, {
            selectedDate,
            currentRowId,
            period,
        })
    );
}

export function getEffectiveRemainingForRow(
    row,
    rows = [],
    currentRowId = null,
) {
    if (!row?.date || !row?.periods?.length || !row?.station) {
        return null;
    }

    const ignoredIds = new Set();

    if (row?.id != null) {
        ignoredIds.add(row.id);
    }

    if (currentRowId != null) {
        ignoredIds.add(currentRowId);
    }

    const rowIndex = rows.findIndex((item) => item?.id === row.id);
    const evaluationRows = rowIndex >= 0 ? rows.slice(0, rowIndex + 1) : [row];

    const relevantRows = evaluationRows.filter((item) => {
        if (!item || !item.date || !item.station || !item.periods?.length) {
            return false;
        }

        if (item.id != null && ignoredIds.has(item.id)) {
            return false;
        }

        if (
            item?.selectionState === 'reset' ||
            item?.selectionState === 'invalid'
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

export function getAdjustedVehiclePeriodRemaining(
    vehicle,
    period,
    rows = [],
    { selectedDate = '', currentRowId = null } = {},
) {
    const data = period === '上學' ? vehicle?.上學 : vehicle?.放學;

    if (!data || !Number.isFinite(Number(data.remaining))) {
        return null;
    }

    const baseRemaining = Number(data.remaining);
    const selectedUsage = getSelectedUsageForVehicle(vehicle, rows, {
        selectedDate,
        currentRowId,
        period,
    });

    return Math.max(baseRemaining - selectedUsage, 0);
}

export function rowCheckState(row, rows = [], currentRowId = null) {
    if (row?.selectionState === 'reset' || row?.selectionState === 'invalid') {
        return 'invalid';
    }

    if (!row?.date || !row?.periods?.length || !row?.station) {
        return row?.student || row?.date || row?.periods?.length
            ? 'invalid'
            : 'disabled';
    }

    const hasMissingRemainingData = row.periods.some((period) => {
        const periodData = row.station?.[period];
        const remaining = Number(periodData?.remaining ?? NaN);

        return !periodData || !Number.isFinite(remaining);
    });

    if (hasMissingRemainingData) {
        return 'invalid';
    }

    const effectiveRemaining = getEffectiveRemainingForRow(
        row,
        rows,
        currentRowId,
    );

    if (effectiveRemaining === null) {
        return 'valid';
    }

    return effectiveRemaining <= 0 ? 'invalid' : 'valid';
}
