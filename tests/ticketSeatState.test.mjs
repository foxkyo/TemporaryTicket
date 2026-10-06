import test from "node:test";
import assert from "node:assert/strict";

import {
    getEffectiveRemainingForRow,
    getSelectedUsageForVehicle,
    rowCheckState,
} from "../resources/js/lib/ticketSeatState.js";

test("0 remaining on the final ticket is invalid", () => {
    const station = { vehicle: "A", 放學: { remaining: 1 } };
    const firstRow = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station,
    };
    const finalRow = {
        id: "r2",
        date: "2026-10-05",
        periods: ["放學"],
        station,
    };

    assert.equal(
        getEffectiveRemainingForRow(finalRow, [firstRow, finalRow]),
        0,
    );
    assert.equal(rowCheckState(finalRow, [firstRow, finalRow]), "invalid");
});

test("negative remaining is invalid and zero remaining is invalid", () => {
    const station = { vehicle: "A", 放學: { remaining: 0 } };
    const firstRow = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station,
    };
    const finalRow = {
        id: "r2",
        date: "2026-10-05",
        periods: ["放學"],
        station,
    };

    assert.equal(
        getEffectiveRemainingForRow(finalRow, [firstRow, finalRow]),
        -1,
    );
    assert.equal(rowCheckState(finalRow, [firstRow, finalRow]), "invalid");

    const zeroRow = {
        id: "r3",
        date: "2026-10-05",
        periods: ["放學"],
        station: { vehicle: "A", 放學: { remaining: 0 } },
    };
    assert.equal(getEffectiveRemainingForRow(zeroRow, [firstRow, finalRow]), 0);
    assert.equal(rowCheckState(zeroRow, [firstRow, finalRow]), "invalid");
});

test("missing remaining data is invalid", () => {
    const station = { vehicle: "A", 放學: {} };
    const row = { id: "r1", date: "2026-10-05", periods: ["放學"], station };

    assert.equal(rowCheckState(row, [row]), "invalid");
});
test("only valid rows count toward remaining seats", () => {
    const baseStation = { vehicle: "A", 放學: { remaining: 1 } };
    const validRow = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
        selectionState: null,
    };
    const invalidRow = {
        id: "r2",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
        selectionState: "reset",
    };

    assert.equal(rowCheckState(validRow, [validRow]), "valid");
    assert.equal(rowCheckState(invalidRow, [validRow, invalidRow]), "invalid");
    assert.equal(
        getEffectiveRemainingForRow(validRow, [validRow, invalidRow]),
        1,
    );
    assert.equal(
        getEffectiveRemainingForRow(invalidRow, [validRow, invalidRow]),
        0,
    );
});

test("invalid state keeps the station but excludes it from remaining calculations", () => {
    const baseStation = { vehicle: "A", 放學: { remaining: 1 } };
    const validRow = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
        selectionState: null,
    };
    const invalidRow = {
        id: "r2",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
        selectionState: "invalid",
    };

    assert.equal(rowCheckState(invalidRow, [validRow, invalidRow]), "invalid");
    assert.equal(
        getSelectedUsageForVehicle({ vehicle: "A" }, [validRow, invalidRow], {
            selectedDate: "2026-10-05",
            period: "放學",
        }),
        1,
    );
    assert.equal(
        getEffectiveRemainingForRow(validRow, [validRow, invalidRow]),
        1,
    );
    assert.equal(invalidRow.station, baseStation);
});

test("current row counts as occupied while the station selector is open", () => {
    const vehicle = { vehicle: "A" };
    const currentRow = {
        id: "current",
        date: "2026-10-05",
        periods: ["放學"],
        station: { vehicle: "A", 放學: { remaining: 1 } },
        selectionState: "invalid",
    };
    const otherRow = {
        id: "other",
        date: "2026-10-05",
        periods: ["放學"],
        station: { vehicle: "A", 放學: { remaining: 1 } },
    };

    assert.equal(
        getSelectedUsageForVehicle(vehicle, [currentRow], {
            selectedDate: "2026-10-05",
            currentRowId: "current",
            period: "放學",
        }),
        1,
    );

    assert.equal(
        getSelectedUsageForVehicle(vehicle, [currentRow, otherRow], {
            selectedDate: "2026-10-05",
            currentRowId: "current",
            period: "放學",
        }),
        2,
    );

    assert.equal(rowCheckState(currentRow, [currentRow, otherRow]), "invalid");
});

test("batch add stops before the first zero-or-negative seat result", () => {
    const baseStation = { vehicle: "A", 放學: { remaining: 1 } };
    let workingRows = [];

    for (let index = 0; index < 5; index += 1) {
        const draftRow = {
            id: `r${index + 1}`,
            date: "2026-10-05",
            periods: ["放學"],
            station: baseStation,
        };

        const candidateRows = [...workingRows, draftRow];

        if (rowCheckState(draftRow, candidateRows) === "invalid") {
            break;
        }

        workingRows = candidateRows;
    }

    assert.equal(workingRows.length, 1);
    assert.equal(getEffectiveRemainingForRow(workingRows[0], workingRows), 1);
    assert.equal(rowCheckState(workingRows[0], workingRows), "valid");
});
