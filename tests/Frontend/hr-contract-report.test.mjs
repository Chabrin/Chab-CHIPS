import {test} from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = name => readFileSync(new URL('../../public/assets/js/' + name, import.meta.url), 'utf8');

test('report scripts are rendered after the layout DataTables bundle that includes jQuery', () => {
    const view = readFileSync(new URL('../../resources/views/staff/contracts/contracts_index.blade.php', import.meta.url), 'utf8');
    const layout = readFileSync(new URL('../../resources/views/layouts/main.blade.php', import.meta.url), 'utf8');
    const content = view.split("@section('content')")[1].split('@endsection')[0];
    const scripts = view.split("@section('scripts')")[1].split('@endsection')[0];
    assert.equal(content.includes('assets/js/hr-contract-report.js'), false);
    assert.ok(scripts.includes('assets/js/hr-contract-report.js'));
    assert.ok(scripts.indexOf('hr-contract-export.js') < scripts.indexOf('hr-contract-report.js'));
    assert.ok(layout.indexOf('assets/datatables/datatables.min.js') < layout.indexOf("@yield('scripts')"));
});
const report = {as_of:'2026-10-30', through:'2026-11-29', due_30:1, generated_at:'2026-10-30 09:00:00'};
const contracts = [
    {id:1,is_current_approved:true,staff_number:'PER-1',employee:{name:'Claire'},department:'Operations',date_start:'2026-01-01',date_end:'2026-11-10',expiry:{status:'expiring_soon',days_remaining:11}},
    {id:2,is_current_approved:true,staff_number:'PER-2',employee:{name:'Other'},department:'Finance',date_start:'2026-01-01',date_end:'2026-12-20',expiry:{status:'active',days_remaining:51}},
    {id:3,is_current_approved:false,employee:{name:'Historical'},department:'Finance',date_start:'2025-01-01',date_end:'2025-05-01',expiry:{status:'expired',days_remaining:-547}},
    {id:4,is_current_approved:false,employee:{name:'Review'},department:'Finance',date_start:null,date_end:null,expiry:{status:'missing_dates',days_remaining:null}}
];

function setup(query='') {
    const nodes = new Map();
    const table = {dataset:{endpoint:'/ajax/hr/contracts/list'},handlers:{}};
    const get = key => {
        if (!nodes.has(key)) nodes.set(key,{value:'',textContent:'',options:[],handlers:{},classes:new Set()});
        return nodes.get(key);
    };
    let ready, options, rows=[], fail=false, exported;
    const filtered = () => rows.filter(row => $.fn.dataTable.ext.search.every(filter => filter({nTable:table},[],0,row)));
    function trigger(node,event) { (node.handlers[event] || []).forEach(fn => fn.call(node,{preventDefault(){}})); }
    const dt = {
        rows() { const values=filtered();return {count:()=>values.length,data:()=>({toArray:()=>values})}; },
        draw() { trigger(table,'draw.dt'); },
        ajax:{reload() { trigger(table,'preXhr.dt'); if (fail) options.ajax.error(); else {rows=options.ajax.dataSrc({report,contracts});dt.draw();} }}
    };
    function $(selector) {
        if (typeof selector === 'function') { ready=selector; return; }
        const elements = typeof selector === 'string' ? selector.split(',').map(key=>get(key.trim())) : [selector];
        const wrapper={0:elements[0],
            val(value) { if(value === undefined) return elements[0].value;elements.forEach(node=>{node.value=value;});return wrapper; },
            text(value) { if(value === undefined) return elements[0].textContent;elements.forEach(node=>{node.textContent=value;});return wrapper; },
            prop(key,value) { elements.forEach(node=>{node[key]=value;});return wrapper; },
            empty() {elements.forEach(node=>{node.options=[];});return wrapper;},
            append(option) {elements.forEach(node=>node.options.push(option));return wrapper;},
            toggleClass(key,on) {elements.forEach(node=>on?node.classes.add(key):node.classes.delete(key));return wrapper;},
            addClass(key) {elements.forEach(node=>node.classes.add(key));return wrapper;},
            removeClass(key) {elements.forEach(node=>node.classes.delete(key));return wrapper;},
            on(events,handler) { elements.forEach(node=>events.split(' ').forEach(event=>{(node.handlers[event] ||= []).push(handler);}));return wrapper; },
            DataTable(config) { if(config) options=config;return dt; }
        };
        return wrapper;
    }
    $.fn={dataTable:{ext:{search:[]},render:{text:()=>({display:value=>value})}}};
    const root={document:{getElementById:()=>table},location:{href:'http://localhost/hr/contracts'+query,search:query},history:{replaceState(a,b,url){root.location.href=url.href;}}};
    const context={window:root,jQuery:$,URL,URLSearchParams,Option:function(text,value){this.text=text;this.value=value;}};
    vm.runInNewContext(source('hr-contract-export.js'),context);
    root.ContractCsvExport={...root.ContractCsvExport,download(table,metadata){exported={rows:table.rows().data().toArray(),metadata};return exported.rows.length;}};
    vm.runInNewContext(source('hr-contract-report.js'),context);
    ready(); dt.ajax.reload();
    return {get,filtered,root,dt,get columns(){return options.columns;},edit(key,value) {get(key).value=value;trigger(get('#contract-filters input'),'input');trigger(get(key),'change');},
        submit(){trigger(get('#contract-filters'),'submit');},click(key){trigger(get(key),'click');},get exported(){return exported;},fail(){fail=true;dt.ajax.reload();}};
}

test('page preserves all contracts by default and applies/persists optional filters before exporting',()=>{
    const ui=setup();
    assert.deepEqual(ui.filtered().map(row=>row.id),[1,2,3,4]);
    assert.equal(ui.get('#contract-from').value,'');
    assert.equal(ui.get('#contract-from').disabled,true);
    assert.equal(ui.get('#contracts-export').disabled,false);
    ui.edit('#contract-report-window','custom');
    ui.edit('#contract-from','2026-12-01');ui.edit('#contract-to','2026-12-31');
    ui.edit('#contract-department','Finance');
    assert.equal(ui.get('#contracts-export').disabled,true);
    ui.dt.draw();assert.equal(ui.get('#contracts-export').disabled,true);
    ui.submit();
    assert.deepEqual(ui.filtered().map(row=>row.id),[2]);
    assert.ok(ui.root.location.href.includes('from=2026-12-01'));
    assert.ok(ui.root.location.href.includes('department=Finance'));
    ui.click('#contracts-export');
    assert.equal(ui.exported.rows[0].id,2);
    assert.equal(ui.exported.metadata.filters.from,'2026-12-01');
    assert.equal(ui.exported.metadata.kind,'full');
    ui.click('#contracts-reset');
    assert.deepEqual(ui.filtered().map(row=>row.id),[1,2,3,4]);
    assert.equal(ui.root.location.href,'http://localhost/hr/contracts?window=all&scope=history');
});

test('bookmarks, individual historical links and undated review retain their intended scope',()=>{
    const custom=setup('?window=custom&from=2026-12-01&to=2026-12-31&department=Finance');
    assert.deepEqual(custom.filtered().map(row=>row.id),[2]);
    const individual=setup('?contract_id=3&window=30');
    assert.deepEqual(individual.filtered().map(row=>row.id),[3]);
    const review=setup('?scope=review');
    assert.deepEqual(review.filtered().map(row=>row.id),[4]);
    assert.equal(review.get('#contract-from').disabled,true);
    assert.ok(review.get('#contract-active-filters').textContent.includes('No date range'));
});

test('invalid range does not replace the applied report and refresh failure blocks downloads',()=>{
    const ui=setup();
    ui.edit('#contract-report-window','custom');ui.edit('#contract-from','2026-12-31');ui.edit('#contract-to','2026-12-01');
    ui.submit();
    assert.ok(ui.get('#contract-filter-error').textContent.includes('To date'));
    assert.deepEqual(ui.filtered().map(row=>row.id),[1,2,3,4]);
    assert.equal(ui.get('#contracts-export').disabled,true);
    ui.fail();
    assert.equal(ui.get('#contracts-expiring-count').textContent,'Unavailable');
    assert.equal(ui.get('#contracts-export').disabled,true);
    assert.equal(ui.get('#contracts-refresh').disabled,false);
});

test('30-day dashboard links still open the expiry subset and the expiry export stays separate',()=>{
    const ui=setup('?window=30');
    assert.deepEqual(ui.filtered().map(row=>row.id),[1]);
    assert.equal(ui.get('#contract-from').value,report.as_of);
    assert.equal(ui.get('#contracts-expiry-export').disabled,false);
    ui.click('#contracts-expiry-export');
    assert.equal(ui.exported.metadata.kind,'expiry');
    ui.click('#contracts-reset');
    assert.equal(ui.get('#contracts-expiry-export').disabled,true);
    assert.equal(ui.get('#contracts-export').disabled,false);
});

test('original contract fields and permission-controlled action icons remain available',()=>{
    const ui=setup();
    assert.deepEqual(Array.from(ui.columns,column=>column.data).filter(Boolean),['date_time','position.position_name','date_start','date_end','basic_salary','created_by.name','department','approval_status','expiry','expiry.days_remaining','id']);
    const actions=ui.columns.at(-1);
    const row={employee:{encrypted_id:'safe-key'},can_read:true,can_update:true};
    assert.ok(actions.render(1,'display',row).includes('visibility'));
    assert.ok(actions.render(1,'display',row).includes('edit_document'));
    assert.ok(actions.render(1,'display',row).includes('/hr/contracts/safe-key/edit'));
    assert.equal(actions.render(1,'display',{...row,can_read:false,can_update:false}).includes('<a '),false);
});
