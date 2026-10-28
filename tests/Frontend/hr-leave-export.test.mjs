import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/hr-leave-export.js', import.meta.url), 'utf8');
function setup() {
    const saved = {};
    const button = {disabled:true, addEventListener(_, callback) {this.click = callback;}};
    const message = {textContent:''};
    const root = {
        Blob, setTimeout(callback) {callback();},
        URL: {createObjectURL(blob) {saved.blob = blob; return 'blob:export';}, revokeObjectURL(url) {saved.revoked = url;}},
        document: {
            getElementById(id) {return id === 'button' ? button : id === 'status' ? message : null;},
            body: {appendChild(link) {saved.link = link;}},
            createElement() {return {click() {saved.clicked = true;}, remove() {saved.removed = true;}};},
        },
    };
    vm.runInNewContext(source, {window:root});
    return {api:root.LeaveCsvExport, saved, button, message};
}
const application = {employee:{name:'Test Employee'}, position:{position_name:'Clerk'}, leave_type:{type_name:'Annual'}, start_date:'2026-10-28 08:00', end_date:'2026-10-28 10:00', requested_days:0.25, used_days:0, current_status_id:1, waiting_on:'hr'};
const plan = {employee_name:'Test Employee', leave_type_name:'Annual', start_date:'2026-01-01', end_date:'2026-12-31', entitled_days:21, balance:0, status:5};

test('application export includes fractions, zero values and readable approval stages', () => {
    const {api} = setup();
    const csv = api.toCsv('applications', [application, {...application, waiting_on:'supervisor'}, {...application, current_status_id:24}]);
    assert.ok(csv.startsWith('\uFEFF'));
    assert.match(csv, /"0.25","0","Pending HR"/);
    assert.ok(csv.includes('"Pending Supervisor"'));
    assert.equal(csv.includes('encrypted'), false);
});

test('plan export includes dates, balance and plan-specific status labels', () => {
    const {api} = setup();
    const csv = api.toCsv('plans', [plan, {...plan, status:1}]);
    assert.match(csv, /"2026-01-01","2026-12-31","21","0","Approved"/);
    assert.ok(csv.includes('"Pending"'));
    assert.equal(csv.includes('Pending Supervisor'), false);
});

test('CSV protects formula-like text, quotes punctuation and leaves absent values blank', () => {
    const {api} = setup();
    for (const name of ['=SUM(1)', '+1+1', '-1+1', '@SUM(1)', '\t=1']) {
        assert.ok(api.toCsv('applications', [{employee:{name}}]).includes('"\''));
    }
    const csv = api.toCsv('plans', [{employee_name:'A, "B"\nC'}]);
    assert.ok(csv.includes('"A, ""B""\nC"'));
    assert.equal(csv.includes('undefined'), false);
});

test('downloads include every matching page in current order and use the correct filenames', async () => {
    for (const kind of ['applications','plans']) {
        const {api, saved} = setup();
        const records = kind === 'applications' ? [application, application] : [plan, plan];
        const table = {rows(options) {
            assert.deepEqual({...options}, {search:'applied',order:'applied',page:'all'});
            return {data:() => ({toArray:() => records})};
        }};
        assert.equal(api.download(table,kind),2);
        assert.equal(saved.clicked,true);
        assert.equal(saved.removed,true);
        assert.equal(saved.revoked,'blob:export');
        assert.ok(saved.link.download.startsWith(kind === 'applications' ? 'leave-applications-' : 'annual-leave-plans-'));
        assert.ok((await saved.blob.text()).includes('Test Employee'));
    }
});

test('empty search results do not create a download', () => {
    const {api,saved} = setup();
    assert.equal(api.download({rows:() => ({data:() => ({toArray:() => []})})},'plans'),0);
    assert.equal(saved.blob,undefined);
});

test('buttons follow table loading, filtered row counts and failed reloads', () => {
    const {api,button,message} = setup();
    const handlers = {};
    let count = 2;
    const table = {
        rows:() => ({count:() => count,data:() => ({toArray:() => [plan]})}),
        on(name,handler) {handlers[name] = handler;},
    };
    api.bind(table,'plans','button','status');
    assert.equal(button.disabled,true);
    handlers['draw.dt']();
    assert.equal(button.disabled,false);
    count = 0; handlers['draw.dt']();
    assert.equal(button.disabled,true);
    assert.match(message.textContent,/No matching/);
    count = 2;
    handlers['xhr.dt'](null,null,null);
    handlers['processing.dt'](null,null,false);
    assert.equal(button.disabled,true);
    assert.match(message.textContent,/Unable to load/);
    handlers['xhr.dt'](null,null,{leave_calendar_plans:[]});
    handlers['draw.dt']();
    assert.equal(button.disabled,false);
    button.click();
    assert.match(message.textContent,/Downloaded 1/);
});
