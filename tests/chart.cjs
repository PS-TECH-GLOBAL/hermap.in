// Exercise the shipped chart with a tiny DOM fixture; no browser automation or real data.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
class Element {
 constructor(tag='div'){this.tag=tag;this.children=[];this.attributes={};this.dataset={};this.events={};this.textContent='';}
 setAttribute(key,value){this.attributes[key]=String(value);}
 append(child){this.children.push(child);}
 replaceChildren(){this.children=[];}
 addEventListener(name,callback){this.events[name]=callback;}
 get selectedOptions(){return[{textContent:this.labels?.[this.value]||this.value}];}
}
const ids=Object.fromEntries(['admin-chart-data','admin-activity-chart','chart-metric','chart-period','chart-group','admin-update-state'].map(id=>[id,new Element()]));
ids['admin-chart-data'].dataset={today:'2026-10-05',apiUrl:'/api/admin-stats',series:JSON.stringify([{day:'2026-09-30',received_reports:2,visits:20},{day:'2026-10-01',received_reports:3,visits:30},{day:'2026-10-05',received_reports:7,visits:70}])};
ids['chart-metric'].value='received_reports';ids['chart-metric'].labels={received_reports:'Received reports',visits:'Page visits'};ids['chart-period'].value='all';ids['chart-group'].value='day';
let refresh,reloaded=false;const counts=[new Element()];counts[0].dataset.adminMetric='Reports all time';
const scope={document:{hidden:false,getElementById:id=>ids[id],createElementNS:(_,tag)=>new Element(tag),querySelectorAll:()=>counts,addEventListener(){}},setInterval:callback=>{refresh=callback;},window:{location:{reload:()=>{reloaded=true;}}},fetch:async()=>({status:401})};
vm.runInNewContext(fs.readFileSync('assets/js/admin.js','utf8'),scope);
function titles(){return ids['admin-activity-chart'].children[0].children.filter(e=>e.tag==='rect').map(e=>e.children[0].textContent);}
assert.equal(titles().length,6,'All-time daily chart fills dates with no activity');
assert.ok(titles().includes('2026-10-02: 0'),'Missing day must be zero');
assert.match(ids['admin-activity-chart'].attributes['aria-label'],/Total 12/);
ids['chart-group'].value='week';ids['chart-group'].events.change();
assert.deepEqual(titles(),['Week of 2026-09-28: 5','Week of 2026-10-05: 7'],'Weekly totals must cross month boundaries and start Monday');
ids['chart-group'].value='month';ids['chart-group'].events.change();
assert.deepEqual(titles(),['2026-09: 2','2026-10: 10'],'Monthly totals must cross the reporting range');
ids['chart-metric'].value='visits';ids['chart-metric'].events.change();
assert.deepEqual(titles(),['2026-09: 20','2026-10: 100'],'Visits use their own series');
assert.match(ids['admin-activity-chart'].attributes['aria-label'],/Total 120/);
ids['chart-period'].value='7';ids['chart-period'].events.change();assert.match(ids['admin-activity-chart'].attributes['aria-label'],/Total 120/);
(async()=>{await refresh();assert.equal(reloaded,true,'Expired moderator session must return to login');console.log('9 chart checks passed: date gaps, daily/weekly/monthly totals, metric changes and session expiry.');})().catch(error=>{console.error(error);process.exitCode=1;});
