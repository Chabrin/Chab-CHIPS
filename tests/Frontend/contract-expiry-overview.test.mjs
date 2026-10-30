import {test} from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source=readFileSync(new URL('../../public/assets/js/contract-expiry-overview.js',import.meta.url),'utf8');
function setup(fetch) {
    const node=()=>({textContent:'',children:[],handlers:{},setAttribute(){},appendChild(child){this.children.push(child);},replaceChildren(){this.children=[];},addEventListener(type,handler){this.handlers[type]=handler;}});
    const elements=new Map();
    const get=selector=>{if(!elements.has(selector))elements.set(selector,node());return elements.get(selector);};
    const document={createElement:node,addEventListener(){}};
    const section={ownerDocument:document,dataset:{endpoint:'/dashboard/contract-expiry-overview',reportUrl:'/hr/contracts?window=30'},querySelector:get};
    const root={document,fetch,location:{origin:'http://127.0.0.1:8000'}};
    vm.runInNewContext(source,{window:root,URL});
    return {view:root.ContractExpiryOverview.mount(section),get};
}
const summary={due_7:2,due_14:3,due_30:5,expired:4,generated_at:'2026-10-29 09:00:00'};
const contract={employee:'Test Employee',staff_number:'PER-001',department:'Operations',end_date:'2026-11-05',days_remaining:7,report_url:'/hr/contracts?contract_id=1&window=30'};
const ok=data=>({ok:true,json:async()=>data});

test('renders live counts and employee/department details with report links',async()=>{
    const ui=setup(async()=>ok({summary,upcoming:[contract]}));
    await ui.view.refresh();
    assert.equal(ui.get('[data-count="due_30"]').textContent,'5');
    const row=ui.get('[data-contract-rows]').children[0];
    assert.equal(row.children[0].textContent,'Test Employee · PER-001');
    assert.equal(row.children[1].textContent,'Operations');
    assert.equal(row.children[4].children[0].href,'http://127.0.0.1:8000/hr/contracts?contract_id=1&window=30');
    assert.match(ui.get('[data-status]').textContent,/Africa\/Nairobi/);
});

test('empty list shows no upcoming contracts without other period cards',async()=>{
    const ui=setup(async()=>ok({summary:{...summary,due_7:0,due_14:0,due_30:0},upcoming:[]}));
    await ui.view.refresh();
    assert.equal(ui.get('[data-count="due_30"]').textContent,'0');
    assert.match(ui.get('[data-contract-rows]').children[0].children[0].textContent,/No current approved contracts/);
});

test('failed requests show unavailable instead of zero and refresh can recover',async()=>{
    let fail=true;
    const ui=setup(async()=>fail?{ok:false}:ok({summary,upcoming:[contract]}));
    await ui.view.refresh();
    assert.equal(ui.get('[data-count="due_30"]').textContent,'Unavailable');
    assert.match(ui.get('[data-status]').textContent,/Unable to load/);
    assert.equal(ui.get('[data-refresh]').disabled,false);
    fail=false;await ui.view.refresh();
    assert.equal(ui.get('[data-count="due_30"]').textContent,'5');
});

test('renders names as text and refuses off-site record links',async()=>{
    const ui=setup(async()=>ok({summary,upcoming:[{...contract,employee:'<img onerror=bad>',report_url:'https://other.example/test',department:null}]}));
    await ui.view.refresh();
    const row=ui.get('[data-contract-rows]').children[0];
    assert.match(row.children[0].textContent,/<img onerror=bad>/);
    assert.equal(row.children[1].textContent,'Not set');
    assert.equal(row.children[4].children[0].href,'/hr/contracts?window=30');
});

test('stale refresh response cannot overwrite a newer report',async()=>{
    let resolveOld;
    let calls=0;
    const ui=setup(()=>++calls===1?new Promise(resolve=>{resolveOld=resolve;}):Promise.resolve(ok({summary:{...summary,due_30:9},upcoming:[]})));
    const first=ui.view.refresh();
    await ui.view.refresh();
    resolveOld(ok({summary,upcoming:[contract]}));await first;
    assert.equal(ui.get('[data-count="due_30"]').textContent,'9');
});
