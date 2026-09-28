export function countInvalidRows(rows = [], rowCheckState = () => 'valid') {
    return rows.filter((item) => {
        if (!item || !item.date || !item.station || !item.periods?.length) {
            return false;
        }

        return rowCheckState(item) === 'invalid';
    }).length;
}

export function canAllowDraftAdd(
    draftRow,
    _rows = [],
    _rowCheckState = () => 'valid',
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

    // Allow adding the draft row regardless of invalid counts.
    // The component will decide how to persist invalid rows.
    return true;
}
