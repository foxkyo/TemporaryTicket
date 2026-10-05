import test from "node:test";
import assert from "node:assert/strict";

import {
    getEffectiveRemainingForRow,
    rowCheckState,
} from "../resources/js/lib/ticketSeatState.js";

test("0 remaining on the final ticket stays valid", () => {
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
    assert.equal(rowCheckState(finalRow, [firstRow, finalRow]), "valid");
});

test("negative remaining is invalid while zero remains valid", () => {
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
    assert.equal(rowCheckState(zeroRow, [firstRow, finalRow]), "valid");
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
