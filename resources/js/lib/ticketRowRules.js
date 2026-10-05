export function countInvalidRows(rows = [], rowCheckState = () => 'valid') {
    return rows.filter((item) => {
        if (!item || !item.date || !item.station || !item.periods?.length) {
            return false;
        }

        return rowCheckState(item) === 'invalid';
    }).length;
}

export function shouldShowAddLimitToast(
    rows = [],
    rowCheckState = () => 'valid',
) {
    return countInvalidRows(rows, rowCheckState) > 1;
}

export function canAllowDraftAdd(
    draftRow,
    rows = [],
    rowCheckState = () => 'valid',
) {
    if (
        !draftRow ||
        !draftRow.student ||
        !draftRow.station ||
        !draftRow.date ||
        !draftRow.periods?.length
    ) {
        return false;
    }

    const invalidCount = countInvalidRows(rows, rowCheckState);
    const draftState = rowCheckState(draftRow);

    if (invalidCount >= 2) {
        return false;
    }

    if (draftState === 'invalid' && invalidCount >= 1) {
        return false;
    }

    return true;
}
