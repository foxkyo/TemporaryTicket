import test from 'node:test';
import assert from 'node:assert/strict';

import {
    canAllowDraftAdd,
    countInvalidRows,
    shouldShowAddLimitToast,
} from './ticketRowRules.js';

const invalidState = (row) => {
    if (!row.date || !row.periods?.length || !row.station) {
        return 'disabled';
    }

    return row.periods.includes('放學') && row.station.vehicle === 'A'
        ? 'invalid'
        : 'valid';
};

void test('allows the first invalid row to be added', () => {
    const rows = [];

    const row = {
        id: 1,
        date: '2026-09-22',
        periods: ['放學'],
        station: { vehicle: 'A' },
        student: { name: 'A' },
    };

    assert.equal(countInvalidRows(rows, invalidState), 0);
    assert.equal(canAllowDraftAdd(row, rows, invalidState), true);
});

void test('blocks a second invalid row once one already exists', () => {
    const rows = [
        {
            id: 1,
            date: '2026-09-22',
            periods: ['放學'],
            station: { vehicle: 'A' },
            student: { name: 'A' },
        },
    ];

    const row = {
        id: 2,
        date: '2026-09-22',
        periods: ['放學'],
        station: { vehicle: 'A' },
        student: { name: 'B' },
    };

    assert.equal(countInvalidRows(rows, invalidState), 1);
    assert.equal(canAllowDraftAdd(row, rows, invalidState), false);
});

void test('allows valid rows while one invalid row remains for correction', () => {
    const rows = [
        {
            id: 1,
            date: '2026-09-22',
            periods: ['放學'],
            station: { vehicle: 'A' },
            student: { name: 'A' },
        },
    ];

    const row = {
        id: 2,
        date: '2026-09-22',
        periods: ['放學'],
        station: { vehicle: 'C' },
        student: { name: 'C' },
    };

    assert.equal(countInvalidRows(rows, invalidState), 1);
    assert.equal(canAllowDraftAdd(row, rows, invalidState), true);
});

void test('shows the limit toast when invalid rows exceed one', () => {
    const rows = [
        {
            id: 1,
            date: '2026-09-22',
            periods: ['放學'],
            station: { vehicle: 'A' },
            student: { name: 'A' },
        },
        {
            id: 2,
            date: '2026-09-22',
            periods: ['放學'],
            station: { vehicle: 'A' },
            student: { name: 'B' },
        },
    ];

    assert.equal(countInvalidRows(rows, invalidState), 2);
    assert.equal(shouldShowAddLimitToast(rows, invalidState), true);
});
