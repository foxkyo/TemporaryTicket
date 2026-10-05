export function getEffectiveRemainingForRow(row, rows = []) {
    if (!row?.date || !row?.periods?.length || !row?.station) {
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

        if (item?.selectionState === "reset") {
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

export function rowCheckState(row, rows = []) {
    if (row?.selectionState === "reset") {
        return "invalid";
    }

    if (!row?.date || !row?.periods?.length || !row?.station) {
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

    return effectiveRemaining < 0 ? "invalid" : "valid";
}
