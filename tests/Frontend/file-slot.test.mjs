import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/file-slot.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const F = root.FileSlot;

test('sizes read as bytes, kilobytes or megabytes', () => {
    assert.equal(F.formatSize(0), '0 B');
    assert.equal(F.formatSize(512), '512 B');
    assert.equal(F.formatSize(1023), '1023 B');
    assert.equal(F.formatSize(1024), '1 KB');
    assert.equal(F.formatSize(1536), '1.5 KB');
    assert.equal(F.formatSize(1024 * 1024), '1 MB');
    assert.equal(F.formatSize(2.34 * 1024 * 1024), '2.34 MB');
    assert.equal(F.formatSize(5 * 1024 * 1024), '5 MB');
});

test('a size that makes no sense shows nothing', () => {
    for (const bad of [-1, NaN, 'abc', undefined, Infinity]) {
        assert.equal(F.formatSize(bad), '', String(bad));
    }
});

test('images and PDFs can be previewed, anything else is only named', () => {
    assert.equal(F.kind({ type: 'image/png', name: 'a.png' }), 'image');
    assert.equal(F.kind({ type: 'image/jpeg', name: 'a.jpg' }), 'image');
    assert.equal(F.kind({ type: 'application/pdf', name: 'a.pdf' }), 'pdf');
    assert.equal(F.kind({ type: 'application/msword', name: 'cv.doc' }), 'other');
    assert.equal(F.kind({ type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', name: 'cv.docx' }), 'other');
});

test('a file with no type is judged by its name, in any capitals', () => {
    assert.equal(F.kind({ type: '', name: 'SCAN.PDF' }), 'pdf');
    assert.equal(F.kind({ type: '', name: 'Photo.JPEG' }), 'image');
    assert.equal(F.kind({ type: '', name: 'notes.txt' }), 'other');
});

test('a file that is not there is "other"', () => {
    assert.equal(F.kind(null), 'other');
    assert.equal(F.kind({}), 'other');
});

test('an SVG is not previewed, because it can carry scripts', () => {
    assert.equal(F.kind({ type: 'image/svg+xml', name: 'logo.svg' }), 'other');
});
