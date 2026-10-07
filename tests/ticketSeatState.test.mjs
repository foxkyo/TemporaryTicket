import test from "node:test";
import assert from "node:assert/strict";

import {
    getEffectiveRemainingForRow,
    getSelectedUsageForVehicle,
    getVehiclePeriodTotal,
    rowCheckState,
} from "../resources/js/lib/ticketSeatState.js";

test("vehicle period total includes matching tickets in the current list", () => {
    const vehicle = {
        vehicle: "A",
        放學: { total: 44 },
    };
    const rows = [
        {
            id: "current",
            date: "2026-10-06",
            periods: ["放學"],
            station: { vehicle: "A" },
            selectionState: "invalid",
        },
        {
            id: "other-period",
            date: "2026-10-06",
            periods: ["上學"],
            station: { vehicle: "A" },
        },
        {
            id: "other-date",
            date: "2026-10-05",
            periods: ["放學"],
            station: { vehicle: "A" },
        },
    ];

    assert.equal(
        getVehiclePeriodTotal(vehicle, "放學", rows, {
            selectedDate: "2026-10-06",
            currentRowId: "current",
        }),
        45,
    );
});

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

test("batch add with 4 remaining allows exactly 4 valid rows and row 3 has remaining: 2", () => {
    const baseStation = { vehicle: "嘉一車", 放學: { remaining: 4 } };
    let workingRows = [];

    for (let index = 0; index < 7; index += 1) {
        const nextRow = {
            id: `r${index + 1}`,
            date: "2026-10-05",
            periods: ["放學"],
            station: baseStation,
        };

        const candidateRows = [...workingRows, nextRow];

        if (rowCheckState(nextRow, candidateRows) === "invalid") {
            break;
        }

        workingRows = candidateRows;
    }

    assert.equal(workingRows.length, 4);
    assert.equal(rowCheckState(workingRows[0], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[1], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[2], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[3], workingRows), "valid");

    assert.equal(getEffectiveRemainingForRow(workingRows[0], workingRows), 4);
    assert.equal(getEffectiveRemainingForRow(workingRows[1], workingRows), 3);
    assert.equal(getEffectiveRemainingForRow(workingRows[2], workingRows), 2);
    assert.equal(getEffectiveRemainingForRow(workingRows[3], workingRows), 1);
});

test("single add when seat limit is exceeded allows adding row with null station for user to modify", () => {
    const baseStation = { vehicle: "嘉一車", 放學: { remaining: 1 } };
    const r1 = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
    };
    let workingRows = [r1];

    // Trying to add 1 more row with baseStation
    const nextRow = {
        id: "r2",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
    };

    const candidateRows = [...workingRows, nextRow];
    if (rowCheckState(nextRow, candidateRows) === "invalid") {
        nextRow.station = null;
        nextRow.selectionState = null;
        workingRows = [...workingRows, nextRow];
    }

    assert.equal(workingRows.length, 2);
    assert.equal(rowCheckState(workingRows[0], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[1], workingRows), "invalid");
    assert.equal(workingRows[1].station, null);

    // When user modifies r2 station to another vehicle with available seats:
    const alternativeStation = { vehicle: "嘉二車", 放學: { remaining: 5 } };
    workingRows[1].station = alternativeStation;
    assert.equal(rowCheckState(workingRows[1], workingRows), "valid");
});

test("calling single-add 7 times on a vehicle with 4 seats remaining results in 4 valid rows and 1 row with null station", () => {
    const baseStation = { vehicle: "嘉一車", 放學: { remaining: 4 } };
    const r1 = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
    };
    let workingRows = [r1];

    function addSingle() {
        const draftRow = workingRows.at(-1);
        if (!draftRow || !draftRow.station) {
            return { success: false, seatLimitHit: false };
        }
        const nextRow = {
            id: `r${workingRows.length + 1}`,
            date: draftRow.date,
            periods: [...draftRow.periods],
            station: draftRow.station,
        };
        const candidateRows = [...workingRows, nextRow];
        if (rowCheckState(nextRow, candidateRows) === "invalid") {
            nextRow.station = null;
            nextRow.selectionState = null;
            workingRows = [...workingRows, nextRow];
            return { success: true, seatLimitHit: true };
        }
        workingRows = candidateRows;
        return { success: true, seatLimitHit: false };
    }

    let addedCount = 0;
    for (let i = 0; i < 7; i += 1) {
        const res = addSingle();
        if (!res.success) {
            break;
        }
        addedCount += 1;
        if (res.seatLimitHit) {
            break;
        }
    }

    assert.equal(addedCount, 4);
    assert.equal(workingRows.length, 5);
    assert.equal(rowCheckState(workingRows[0], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[1], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[2], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[3], workingRows), "valid");
    assert.equal(rowCheckState(workingRows[4], workingRows), "invalid");
    assert.equal(workingRows[4].station, null);
});

test("closing station selector modal without modifying restores row state and rechecks validity", () => {
    const baseStation = { vehicle: "嘉一車", 放學: { remaining: 5 } };
    const row = {
        id: "r1",
        date: "2026-10-05",
        periods: ["放學"],
        station: baseStation,
        selectionState: null,
    };
    const rows = [row];

    assert.equal(rowCheckState(row, rows), "valid");

    // Modal opens: selectionState becomes "invalid"
    row.selectionState = "invalid";
    assert.equal(rowCheckState(row, rows), "invalid");

    // User cancels / closes modal without modifying: selectionState is cleared to null
    row.selectionState = null;

    // Normal check logic re-evaluates: since station and remaining seats are valid, it becomes valid!
    assert.equal(rowCheckState(row, rows), "valid");
});
