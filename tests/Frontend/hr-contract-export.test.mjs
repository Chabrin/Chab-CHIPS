import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/hr-contract-export.js', import.meta.url), 'utf8');
function setup() {
    const saved = { clicked: false };
    const root = {
        Blob,
        URL: { createObjectURL(blob) { saved.blob = blob; return 'blob:test'; }, revokeObjectURL(url) { saved.revoked = url; } },
        setTimeout(callback) { callback(); },
        document: {
            body: { appendChild(link) { saved.link = link; } },
            createElement() { return { click() { saved.clicked = true; }, remove() { saved.removed = true; } }; },
        },
    };
    vm.runInNewContext(source, {window: root});
    return { api: root.ContractCsvExport, saved, root };
}
const contract = {
    date_time: '2026-10-27T09:00:00Z', staff_number: '004', employee: {name: 'Claire, "Example"'},
    position: {position_name: 'Developer'}, date_start: '2026-01-01', date_end: '2026-10-26',
    expiry: {label: 'Expired', status: 'expired', days_remaining: -1}, basic_salary: '60000.00', created_by: {name: 'HR'},
};

test('CSV contains expiry fields, safely quotes names and omits salary/creator', () => {
    const {api} = setup();
    const csv = api.toCsv([contract]);
    assert.ok(csv.startsWith('\uFEFF'));
    assert.match(csv, /"Claire, ""Example"""/);
    assert.match(csv, /"004"/);
    assert.match(csv, /"Expired","-1"/);
    assert.equal(csv.includes('60000.00'), false);
    assert.equal(csv.includes('Created By'), false);
    assert.equal(csv.includes('encrypted_id'), false);
});

test('missing fields are blank, zero values are retained and invalid dates have no day count', () => {
    const {api} = setup();
    const csv = api.toCsv([{ basic_salary: 0, expiry: {status: 'invalid_dates', label: 'Invalid dates', days_remaining: -10} }]);
    assert.match(csv, /"Invalid dates",""/);
    assert.ok(api.toCsv([{expiry:{days_remaining:0}}]).includes('"0"'));
    assert.equal(csv.includes('undefined'), false);
    assert.equal(csv.includes('null'), false);
});

test('formula-like names are neutralized and embedded newlines are quoted', () => {
    const {api} = setup();
    for (const name of ['=HYPERLINK("bad")', '+SUM(1,2)', '-10+20', '@SUM(1)', '\t=1+1']) {
        assert.ok(api.toCsv([{employee:{name}}]).includes('"\''));
    }
    assert.ok(api.toCsv([{employee:{name:'First\nSecond'}}]).includes('"First\nSecond"'));
});

test('download requests all filtered pages in the current order and releases the download URL', async () => {
    const {api, saved} = setup();
    const table = { rows(options) {
        assert.deepEqual({...options}, {search:'applied', order:'applied', page:'all'});
        return {data: () => ({toArray: () => [contract, {...contract, staff_number:'005'}]})};
    } };
    assert.equal(api.download(table), 2);
    assert.equal(saved.clicked, true);
    assert.match(saved.link.download, /^employee-contracts-\d{4}-\d{2}-\d{2}\.csv$/);
    assert.equal(saved.revoked, 'blob:test');
    assert.equal(saved.removed, true);
    assert.ok((await saved.blob.text()).includes('"005"'));
});

test('no matching rows do not create a download', () => {
    const {api, saved} = setup();
    assert.equal(api.download({rows: () => ({data: () => ({toArray: () => []})})}), 0);
    assert.equal(saved.clicked, false);
    assert.equal(saved.blob, undefined);
});

test('upcoming windows include today and their upper boundary but exclude expired and superseded contracts', () => {
    const {api}=setup();
    const current=days => ({id:1,is_current_approved:true,expiry:{status:days < 0 ? 'expired' : 'expiring_soon',days_remaining:days}});
    for (const window of ['7','14','30']) {
        assert.equal(api.matches(current(0),window,'',null),true);
        assert.equal(api.matches(current(Number(window)),window,'',null),true);
        assert.equal(api.matches(current(Number(window)+1),window,'',null),false);
        assert.equal(api.matches(current(-1),window,'',null),false);
        assert.equal(api.matches({...current(1),is_current_approved:false},window,'',null),false);
    }
});

test('expired and recent-expired windows are distinct and missing dates are not mistaken for today', () => {
    const {api}=setup();
    const current=days => ({is_current_approved:true,expiry:{days_remaining:days}});
    assert.equal(api.matches(current(-31),'expired','',null),true);
    assert.equal(api.matches(current(-31),'expired30','',null),false);
    assert.equal(api.matches(current(-30),'expired30','',null),true);
    assert.equal(api.matches(current(0),'expired','',null),false);
    assert.equal(api.matches(current(null),'30','',null),false);
    assert.equal(api.matches(current(undefined),'30','',null),false);
});

test('history and legacy individual links remain available only when their filters match', () => {
    const {api}=setup();
    const old={id:12,is_current_approved:false,expiry:{status:'expired',days_remaining:-100}};
    assert.equal(api.matches(old,'all','',12),true);
    assert.equal(api.matches(old,'all','',13),false);
    assert.equal(api.matches(old,'all','active',12),false);
    assert.equal(api.matches(old,'30','',12),false);
});

test('report CSV includes department, approval status and generation details', () => {
    const {api}=setup();
    const csv=api.toCsv([{...contract,department:'Operations',approval_status:'Approved'}],{generated_at:'2026-10-29 09:00:00',window:'30'});
    assert.ok(csv.includes('"Operations"'));
    assert.ok(csv.includes('"Approved"'));
    assert.ok(csv.includes('2026-10-29 09:00:00'));
    assert.ok(csv.includes('Expiring within 30 days'));
    assert.ok(csv.includes('Report Generated At (Africa/Nairobi)'));
});

const filters = {from:'2026-10-30', to:'2026-11-29', scope:'current', department:'', search:''};
const dated = {id:1, is_current_approved:true, staff_number:'PER-004', employee:{name:'Claire Example'}, department:'Operations',
    date_start:'2026-01-01', date_end:'2026-10-30', expiry:{status:'expiring_soon', days_remaining:0}};

test('calendar boundaries are inclusive, use end date and reject superseded contracts', () => {
    const {api}=setup();
    assert.equal(api.matchesFilters(dated,filters),true);
    assert.equal(api.matchesFilters({...dated,date_end:filters.to},filters),true);
    assert.equal(api.matchesFilters({...dated,date_end:'2026-11-30'},filters),false);
    assert.equal(api.matchesFilters({...dated,date_end:'2026-10-29'},filters),false);
    assert.equal(api.matchesFilters({...dated,is_current_approved:false},filters),false);
    assert.equal(api.matchesFilters({...dated,is_current_approved:false},{...filters,scope:'history'}),true);
});

test('custom filters combine department, employee, individual record and date range', () => {
    const {api}=setup();
    assert.equal(api.matchesFilters(dated,{...filters,department:'Operations',search:'claire',selectedId:1}),true);
    assert.equal(api.matchesFilters(dated,{...filters,search:'per-004'}),true);
    assert.equal(api.matchesFilters(dated,{...filters,department:'Finance'}),false);
    assert.equal(api.matchesFilters(dated,{...filters,search:'Unknown'}),false);
    assert.equal(api.matchesFilters(dated,{...filters,selectedId:2}),false);
    assert.equal(api.matchesFilters({...dated,department:null},{...filters,department:'__missing__'}),true);
});

test('invalid and incomplete ranges are blocked including impossible calendar dates', () => {
    const {api}=setup();
    for (const bad of [{from:'2026-11-30'}, {to:''}, {from:'2026-02-30'}, {to:'not-a-date'}]) {
        assert.notEqual(api.validateFilters({...filters,...bad}),'');
        assert.equal(api.matchesFilters(dated,{...filters,...bad}),false);
    }
    assert.equal(api.validateFilters({...filters,from:'2028-02-29',to:'2028-02-29'}),'');
    assert.equal(api.validateFilters({...filters,from:'2026-02-29'}).length > 0,true);
});

test('missing and invalid dates have an explicit review scope and no misleading date match', () => {
    const {api}=setup();
    for (const status of ['missing_dates','invalid_dates']) {
        const row={...dated,date_end:null,expiry:{status}};
        assert.equal(api.matchesFilters(row,filters),false);
        assert.equal(api.matchesFilters(row,{...filters,scope:'review',from:'',to:''}),true);
        assert.equal(api.matchesFilters(row,{...filters,scope:'review',department:'Finance'}),false);
    }
    assert.equal(api.matchesFilters(dated,{...filters,scope:'review'}),false);
});

test('CSV and print share report columns, active filters and matching totals without salary', () => {
    const {api}=setup();
    const report={generated_at:'2026-10-30 10:00:00',filters:{...filters,department:'Operations',search:'Claire'}};
    const csv=api.toCsv([dated],report);
    const html=api.printHtml([dated],report);
    for (const value of ['2026-10-30 to 2026-11-29','Department: Operations','Employee search: Claire','2026-10-30 10:00:00']) {
        assert.ok(csv.includes(value)); assert.ok(html.includes(value));
    }
    assert.ok(html.includes('Matching contracts: 1'));
    assert.ok(csv.includes('Matching Contracts'));
    assert.equal(html.includes('Basic Salary'),false);
    const unsafe=api.printHtml([{...dated,employee:{name:'<script>alert(1)</script>'},department:'<img src=x>'}],report);
    assert.equal(unsafe.includes('<script>'),false);
    assert.ok(unsafe.includes('&lt;script&gt;'));
    assert.equal(unsafe.includes('<img src=x>'),false);
});

test('print uses all filtered pages in current order and leaves the report page intact', () => {
    const {api}=setup();
    assert.equal(api.print({rows:options=>{
        assert.deepEqual({...options},{search:'applied',order:'applied',page:'all'});
        return {data:()=>({toArray:()=>[]})};
    }}),0);
});

test('nonempty print opens a separate preview and blocked popups have a recoverable error', () => {
    const {api,root}=setup();
    const table={rows:options=>{
        assert.deepEqual({...options},{search:'applied',order:'applied',page:'all'});
        return {data:()=>({toArray:()=>[dated]})};
    }};
    root.open=()=>null;
    assert.throws(()=>api.print(table),/Allow the report window/);
    let html,printed=false;
    const preview={opener:root,document:{write(value){html=value;},close(){}},focus(){},print(){printed=true;}};
    root.open=(url,target)=>{assert.equal(url,'');assert.equal(target,'_blank');return preview;};
    assert.equal(api.print(table,{filters}),1);
    assert.equal(preview.opener,null);
    assert.equal(printed,true);
    assert.ok(html.includes('PER-004'));
});

test('all-contract filters retain undated and historical records and full reports retain the original fields',()=>{
    const {api}=setup();
    const all={period:'all',scope:'history',from:'',to:'',department:'',search:''};
    const row={...contract,is_current_approved:false,date_end:null,expiry:{status:'missing_dates',label:'Missing dates',days_remaining:null}};
    assert.equal(api.validateFilters(all),'');
    assert.equal(api.matchesFilters(row,all),true);
    assert.equal(api.matchesFilters(row,{...all,scope:'current'}),false);
    const full=api.toCsv([row],{kind:'full',filters:all});
    for(const value of ['Date/Time','Basic Salary (KES)','Created By','60000.00','2026-10-27T09:00:00Z','HR','All dates']) assert.ok(full.includes(value));
    const html=api.printHtml([row],{kind:'full',filters:all});
    assert.ok(html.includes('60000.00'));
    assert.ok(html.includes('Created By'));
    const expiry=api.toCsv([row],{kind:'expiry'});
    assert.equal(expiry.includes('60000.00'),false);
});
