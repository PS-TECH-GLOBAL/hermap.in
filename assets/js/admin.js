'use strict';
(() => {
 const source=document.getElementById('admin-chart-data');if(!source)return;
 let data=JSON.parse(source.dataset.series);const container=document.getElementById('admin-activity-chart'),metric=document.getElementById('chart-metric'),period=document.getElementById('chart-period'),group=document.getElementById('chart-group');
 function draw(){
  const today=new Date(`${source.dataset.today}T00:00:00Z`),first=period.value==='all'?new Date(`${data[0]?.day||source.dataset.today}T00:00:00Z`):new Date(today.getTime()-(Number(period.value)-1)*86400000),key=metric.value,buckets=new Map(),byDay=new Map(data.map(row=>[row.day,Number(row[key]||0)]));
  for(let date=new Date(first);date<=today;date.setUTCDate(date.getUTCDate()+1)){const day=date.toISOString().slice(0,10);let label=day;if(group.value==='month')label=day.slice(0,7);if(group.value==='week'){const start=new Date(date);start.setUTCDate(start.getUTCDate()-((start.getUTCDay()+6)%7));label=start.toISOString().slice(0,10);}buckets.set(label,(buckets.get(label)||0)+(byDay.get(day)||0));}
  const rows=[...buckets].map(([day,count])=>({day,count}));container.replaceChildren();container.setAttribute('aria-label',`${metric.selectedOptions[0].textContent}, ${group.selectedOptions[0].textContent.toLowerCase()}, ${period.selectedOptions[0].textContent.toLowerCase()}. Total ${rows.reduce((sum,row)=>sum+row.count,0)}. Weeks begin Monday; first and last periods may be partial.`);
  const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 1000 260');svg.setAttribute('aria-hidden','true');
  function element(tag,attrs,text){const item=document.createElementNS(ns,tag);for(const [name,value]of Object.entries(attrs))item.setAttribute(name,String(value));if(text!==undefined)item.textContent=text;svg.append(item);return item;}
  const max=Math.max(1,...rows.map(row=>row.count));for(let i=0;i<=4;i++){const y=210-i*45;element('line',{x1:45,x2:980,y1:y,y2:y,stroke:'#e5e5df'});element('text',{x:35,y:y+4,'text-anchor':'end',fill:'#626969','font-size':11},Number((max*i/4).toFixed(1)));}
  const width=930/rows.length;rows.forEach((row,i)=>{const h=row.count/max*180;const bar=element('rect',{x:45+i*width+width*.15,y:210-h,width:Math.max(.5,width*.7),height:h,rx:Math.min(3,width*.2),fill:key==='visits'?'#80649e':'#ff6b6b'});const title=document.createElementNS(ns,'title');title.textContent=`${group.value==='week'?'Week of ':''}${row.day}: ${row.count}`;bar.append(title);if(i===0||i===rows.length-1||i%Math.ceil(rows.length/5)===0)element('text',{x:45+i*width+width/2,y:238,'text-anchor':'middle',fill:'#626969','font-size':11},row.day.slice(5));});
  if(!rows.some(row=>row.count))element('text',{x:500,y:110,'text-anchor':'middle',fill:'#626969','font-size':15},'No activity recorded in this period');container.append(svg);
 }
 metric.addEventListener('change',draw);group.addEventListener('change',draw);period.addEventListener('change',()=>{if(period.value==='all')group.value='month';draw();});draw();
 async function refresh(){if(document.hidden)return;try{const response=await fetch(source.dataset.apiUrl,{cache:'no-store',credentials:'same-origin'});if(response.status===401){window.location.reload();return;}const stats=await response.json();if(!response.ok)throw new Error(stats.error||'Dashboard updates are unavailable.');data=stats.daily;source.dataset.today=stats.today;document.querySelectorAll('[data-admin-metric]').forEach(element=>{element.textContent=Number(stats.metrics[element.dataset.adminMetric]||0).toLocaleString('en-IN');});draw();document.getElementById('admin-update-state').textContent='Counts and graphs updated. Refresh the page to update the review queue.';}catch(error){document.getElementById('admin-update-state').textContent=error.message;}}
 setInterval(refresh,30000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
 const adminState=document.getElementById('admin-state-select'),adminCity=document.getElementById('admin-city-select');
 if(adminState&&adminCity){
  adminState.addEventListener('change',async()=>{
   const stateId=adminState.value;adminCity.innerHTML='<option value="">All Cities</option>';
   if(!stateId){adminCity.disabled=true;return;}
   adminCity.disabled=true;
   try{
    const res=await fetch(`${source.dataset.apiUrl.replace('admin-stats','')}locations?type=cities&state_id=${encodeURIComponent(stateId)}`,{cache:'no-store'});
    const data=await res.json();adminCity.innerHTML='<option value="">All Cities</option>';
    if(Array.isArray(data.items)){data.items.forEach(c=>adminCity.add(new Option(c.name,c.id)));}
    adminCity.disabled=false;
   }catch{adminCity.disabled=false;}
  });
 }
})();
