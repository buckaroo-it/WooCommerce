import { test } from 'node:test';
import assert from 'node:assert/strict';
import { matchesDayFirstDate } from '../assets/js/blocks/utils/utils.js';

test('accepts a date typed day-first with any supported separator', () => {
    const date = new Date(1999, 3, 10);
    for (const text of ['10-04-1999', '10-4-1999', '10/04/1999', '10.4.1999', ' 10-04-1999 ']) {
        assert.equal(matchesDayFirstDate(text, date), true, text);
    }
});

test('rejects a date read month-first from the typed text', () => {
    // "12/25/1999" is not a valid day-first date; month-first parsing gives 25 December.
    assert.equal(matchesDayFirstDate('12/25/1999', new Date(1999, 11, 25)), false);
    // "10/04/1999" read month-first is 4 October.
    assert.equal(matchesDayFirstDate('10/04/1999', new Date(1999, 9, 4)), false);
});

test('rejects text that is not in a supported format', () => {
    assert.equal(matchesDayFirstDate('1999-04-10', new Date(1999, 3, 10)), false);
    assert.equal(matchesDayFirstDate('10-04/1999', new Date(1999, 3, 10)), false);
    assert.equal(matchesDayFirstDate('April 10, 1999', new Date(1999, 3, 10)), false);
});

test('rejects a missing or invalid date', () => {
    assert.equal(matchesDayFirstDate('10-04-1999', null), false);
    assert.equal(matchesDayFirstDate('10-04-1999', new Date(NaN)), false);
    assert.equal(matchesDayFirstDate('31-02-1999', new Date(1999, 2, 3)), false);
});
