import assert from 'node:assert/strict';
import test from 'node:test';

import { buildTicketPages } from './ticketPageGroups.js';

void test('buildTicketPages groups by student and chunks every five tickets', () => {
    const tickets = [
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-03',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-01',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-05',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-07',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-09',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小明',
            班級: '國一1',
            日期: '2026-10-11',
            車別: '嘉義A1',
            站別: '中山站',
        },
        {
            姓名: '小華',
            班級: '國二2',
            日期: '2026-10-02',
            車別: '嘉義A1',
            站別: '中山站',
        },
    ];

    const pages = buildTicketPages(tickets);

    assert.equal(pages.length, 3);
    assert.equal(pages[0].studentDisplay, '國一1 小明');
    assert.deepEqual(pages[0].items.length, 5);
    assert.deepEqual(pages[1].items.length, 1);
    assert.deepEqual(pages[2].studentDisplay, '國二2 小華');
    assert.deepEqual(pages[2].items.length, 1);
    assert.equal(pages[0].items[0].日期, '2026-10-01');
    assert.equal(pages[0].items[4].日期, '2026-10-09');
});
