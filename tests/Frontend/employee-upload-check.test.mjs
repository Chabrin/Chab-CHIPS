import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';

const source = readFileSync(new URL('../../resources/views/staff/partials/upload-duplicate-check.blade.php', import.meta.url), 'utf8')
    .replace('<script>', '').replace('</script>', '')
    .replace("@json(route('hr.uploads.check-duplicates'))", "'/check'")
    .replace('@json(\\App\\Support\\EmployeeUploadLimits::current())', '{file:2097152, post:8388608}');

function setup(fetch) {
    function node() {
        return {dataset:{}, children:[], style:{}, classList:{toggle(){},add(){},remove(){}},
            setAttribute(){}, addEventListener(){}, appendChild(child){this.children.push(child);},
            replaceChildren(){this.children=[];}, textContent:''};
    }
    const inputs = ['photo', 'documents[national_id]', 'documents[kra_pin]'].map(name => {
        const notes = [];
        return {
            name, type:'file', files: [], handlers: {}, message: '',
            parentElement: { querySelector: selector => notes.find(note => selector.includes('"' + note.dataset.duplicateFeedback + '"')) },
            insertAdjacentElement: (_, note) => notes.push(note),
            addEventListener(type, handler) { this.handlers[type] = handler; },
            setCustomValidity(message) { this.message = message; },
            classList: { toggle() {},remove(){}, contains: () => false }, closest: () => null, focus() {}, scrollIntoView() {}, notes,
        };
    });
    let submit;
    const summaries = [];
    const form = {
        dataset: {}, action:'/staff', elements:inputs,
        insertAdjacentElement(_,element){summaries.push(element);},
        querySelectorAll: () => inputs,
        querySelector: () => ({ value: 'csrf-token' }),
        addEventListener: (type, handler) => { if(type === 'submit') submit = handler; },
    };
    const window = {location:{assign(url){window.destination=url;}}};
    vm.runInNewContext(source, {
        document: {
            addEventListener: (_, handler) => handler(),
            getElementById: id => id === 'staffCreateForm' ? form : null,
            createElement: node,
        },
        crypto: webcrypto, fetch, window, FormData: class {constructor(value){this.form=value;}},
    });
    return {
        inputs, window, form, summaries,
        select(index, contents) {
            inputs[index].files = contents === null ? [] : [{
                size: contents.length,
                arrayBuffer: async () => new TextEncoder().encode(contents).buffer,
            }];
            return inputs[index].handlers.change();
        },
        submit() {
            let blocked = false;
            submit({ preventDefault: () => { blocked = true; }, stopImmediatePropagation() {} });
            return blocked;
        },
    };
}

test('same-form duplicates are blocked, and clearing the duplicate allows save', async () => {
    const ui = setup(async () => ({ ok: true, status: 200, json: async () => ({ valid: true }) }));
    await ui.select(1, 'same contents');
    await ui.select(2, 'same contents');
    assert.match(ui.inputs[2].message, /already selected for National ID/);
    assert.equal(ui.submit(), true);
    await ui.select(2, null);
    assert.equal(ui.inputs[2].message, '');
    assert.equal(ui.submit(), false);
});

test('a stored duplicate produces inline feedback and blocks save', async () => {
    const ui = setup(async () => ({ ok: false, status: 422, json: async () => ({ errors: { photo: ['Already uploaded.'] } }) }));
    await ui.select(0, 'existing image');
    assert.equal(ui.inputs[0].message, 'Already uploaded.');
    assert.equal(ui.submit(), true);
});

test('save waits for preflight and stale responses cannot invalidate a cleared selection', async () => {
    let respond;
    const ui = setup(() => new Promise(resolve => { respond = resolve; }));
    const pending = ui.select(0, 'image');
    while (!respond) await new Promise(resolve => setTimeout(resolve, 1));
    assert.equal(ui.submit(), true);
    await ui.select(0, null);
    respond({ ok: false, status: 422, json: async () => ({ errors: { photo: ['Old error'] } }) });
    await pending;
    assert.equal(ui.inputs[0].message, '');
    assert.equal(ui.submit(), false);
});

test('failed live checks block saving and preserve all selections for retry', async () => {
    const ui = setup(async () => { throw new Error('offline'); });
    await ui.select(0, 'new image');
    assert.match(ui.inputs[0].message, /could not finish/);
    assert.equal(ui.inputs[0].files.length,1);
    assert.equal(ui.submit(), true);
    assert.equal(ui.summaries.length,1);
});

test('preflight sends fingerprints rather than file contents', async () => {
    let payload;
    const ui = setup(async (_, options) => {
        payload = JSON.parse(options.body);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        return { ok: true, status: 200, json: async () => ({ valid: true }) };
    });
    await ui.select(0, 'private image content');
    assert.match(payload.hashes.photo, /^[a-f0-9]{64}$/);
    assert.equal(JSON.stringify(payload).includes('private image content'), false);
});

const jsonResponse = (status, payload) => ({status,ok:status >= 200 && status < 300, headers:{get:() => 'application/json'},json:async() => payload});

test('server duplicate errors after preflight keep the document and other selected files', async () => {
    const ui = setup(async url => url === '/check'
        ? jsonResponse(200,{valid:true})
        : jsonResponse(422,{message:'Correct the highlighted fields.',errors:{'documents.national_id':['National ID / Passport copy: This exact file is already saved for another employee. Choose a different file.']}}));
    await ui.select(1,'duplicate saved after preflight');
    await ui.select(2,'different valid certificate');
    const originalId = ui.inputs[1].files[0], originalPin = ui.inputs[2].files[0];
    await ui.window.EmployeeStaffForm.save();
    assert.equal(ui.inputs[1].files[0],originalId);
    assert.equal(ui.inputs[2].files[0],originalPin);
    assert.match(ui.inputs[1].message,/another employee/);
    assert.equal(ui.window.destination,undefined);
});

test('upload errors and oversized requests preserve selections without redirecting', async () => {
    const ui = setup(async url => url === '/check' ? jsonResponse(200,{valid:true}) : jsonResponse(413,{}));
    await ui.select(0,'photo');
    const photo=ui.inputs[0].files[0];
    await ui.window.EmployeeStaffForm.save();
    assert.equal(ui.inputs[0].files[0],photo);
    assert.match(ui.summaries[0].children[0].textContent,/upload limit/);
    assert.equal(ui.window.destination,undefined);
});

test('per-file limits are checked before submitting with a clear field-specific message', async () => {
    const ui=setup(async() => {throw new Error('Should not send oversized file');});
    await ui.select(1,'x'.repeat(2097153));
    assert.match(ui.inputs[1].message,/National ID.*2 MB/);
    assert.equal(ui.inputs[1].files.length,1);
    assert.equal(ui.submit(),true);
});

test('successful save follows the confirmed staff URL', async () => {
    const ui=setup(async url => url === '/check' ? jsonResponse(200,{valid:true}) : jsonResponse(200,{redirect_url:'/hr/employees/test'}));
    await ui.select(0,'photo');
    await ui.window.EmployeeStaffForm.save();
    assert.equal(ui.window.destination,'/hr/employees/test');
});

test('lost save response keeps files and warns against blindly resubmitting', async () => {
    const ui=setup(async url => {if(url==='/check')return jsonResponse(200,{valid:true});throw new Error('Connection lost');});
    await ui.select(0,'photo');
    await ui.window.EmployeeStaffForm.save();
    assert.equal(ui.inputs[0].files.length,1);
    assert.match(ui.summaries[0].children[0].textContent,/Check the Employees list/);
});
