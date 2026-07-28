(() => {
  'use strict';
  const set=(id,value)=>{const el=document.getElementById(id);if(el){el.textContent=value;el.classList.remove('ab-skeleton')}};
  function drawTrend(items){
    const host=document.getElementById('trend-chart'); if(!host)return;
    host.replaceChildren();
    if(!items.length){host.innerHTML='<div class="ab-empty">No recent feedback data available.</div>';return;}
    const w=760,h=250,p=36,max=Math.max(...items.map(x=>Number(x.response_count||0)),1);
    const pts=items.map((x,i)=>({x:p+i*((w-2*p)/Math.max(items.length-1,1)),y:h-p-(Number(x.response_count||0)/max)*(h-2*p),label:x.month_key,count:Number(x.response_count||0)}));
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox',`0 0 ${w} ${h}`);svg.style.width='100%';svg.style.height='100%';svg.setAttribute('role','img');svg.setAttribute('aria-label','Monthly feedback response trend');
    const line=document.createElementNS(svg.namespaceURI,'polyline');line.setAttribute('points',pts.map(q=>`${q.x},${q.y}`).join(' '));line.setAttribute('fill','none');line.setAttribute('stroke','#145a7a');line.setAttribute('stroke-width','4');line.setAttribute('stroke-linecap','round');line.setAttribute('stroke-linejoin','round');svg.appendChild(line);
    pts.forEach(q=>{const c=document.createElementNS(svg.namespaceURI,'circle');c.setAttribute('cx',q.x);c.setAttribute('cy',q.y);c.setAttribute('r','5');c.setAttribute('fill','#fff');c.setAttribute('stroke','#145a7a');c.setAttribute('stroke-width','3');svg.appendChild(c);const t=document.createElementNS(svg.namespaceURI,'text');t.setAttribute('x',q.x);t.setAttribute('y',h-10);t.setAttribute('text-anchor','middle');t.setAttribute('font-size','11');t.setAttribute('fill','#52606d');t.textContent=q.label;svg.appendChild(t)});
    host.appendChild(svg);
  }
  async function load(){
    try{
      const api=window.AbhiprayaUI?.json; if(!api) return;
      const [sum,detail]=await Promise.all([api('/api/v1/analytics/summary?summary_only=1&trend_months=6'),api('/api/v1/analytics/summary')]);
      const s=sum.data?.summary||{}; const indicators=(detail.data?.indicators||[]).filter(x=>Number.isFinite(Number(x.score)));
      const avg=indicators.length?(indicators.reduce((a,b)=>a+Number(b.score),0)/indicators.length).toFixed(1):'—';
      set('kpi-responses',s.total_responses??0);set('kpi-rating',avg==='—'?'—':`${avg}/5`);set('kpi-facilities',`${s.facility_count??0}/${s.configured_facility_count??0}`);
      set('kpi-priority',indicators.filter(x=>Number(x.score)<3).length);set('kpi-actions','—');drawTrend(sum.data?.monthly_trend||[]);
      const rows=document.getElementById('indicator-list'); if(rows){rows.replaceChildren();const grouped=new Map();indicators.forEach(x=>{const key=String(x.indicator_id??x.indicator_name);const n=Math.max(1,Number(x.responses||x.response_count||1));const g=grouped.get(key)||{...x,sum:0,n:0};g.sum+=Number(x.score)*n;g.n+=n;grouped.set(key,g)});[...grouped.values()].map(x=>({...x,score:x.sum/x.n})).sort((a,b)=>Number(a.score)-Number(b.score)||String(a.indicator_name).localeCompare(String(b.indicator_name))).slice(0,5).forEach(x=>{const score=Number(x.score);const row=document.createElement('div');row.className='ab-indicator-row';row.innerHTML=`<span>${x.indicator_name}</span><div class="ab-progress"><span style="width:${Math.max(0,Math.min(100,score/5*100))}%"></span></div><strong>${score.toFixed(1)}/5</strong>`;rows.appendChild(row)});if(!rows.children.length)rows.innerHTML='<div class="ab-empty">No indicator data available.</div>'}
    }catch(e){['kpi-responses','kpi-rating','kpi-facilities','kpi-priority','kpi-actions'].forEach(id=>set(id,'—'));drawTrend([])}
  }
  load();
})();
