document.querySelectorAll('[data-eye]').forEach(b=>b.onclick=()=>{const i=b.parentElement.querySelector('input');i.type=i.type==='password'?'text':'password'});
document.querySelectorAll('input[name=role]').forEach(r=>r.onchange=()=>document.getElementById('employerFields').classList.toggle('hidden',r.value!=='employer'));
function addRow(boxId,html){const d=document.createElement('div');d.className='row';d.innerHTML=html+'<button type="button" class="btn sm" onclick="this.parentElement.remove()">×</button>';document.getElementById(boxId).appendChild(d)}
document.getElementById('addAddr')?.addEventListener('click',()=>addRow('addrs','<input name="addresses[]" placeholder="Additional company address">'));
const resumeFields={education:['School / college / university','Degree, course, strand, or program','Graduation year'],seminar:['Seminar / workshop / training title','Organizer and topics or skills learned','Year attended'],certification:['Certificate / license name','Issuing organization (and validity, if applicable)','Year earned']};
document.querySelectorAll('[data-add-resume]').forEach(b=>b.onclick=()=>{const t=b.dataset.addResume;const f=resumeFields[t]||['Title','Details','Year'];addRow('r-'+t,`<input type=hidden name="rtype[]" value="${t}"><input name="rtitle[]" placeholder="${f[0]}"><input name="rdetail[]" placeholder="${f[1]}"><input name="ryear[]" placeholder="${f[2]}" style="max-width:125px">`)});
document.getElementById('seeAll')?.addEventListener('click', function (e) {
    e.preventDefault();
    const box = document.getElementById('allLgus');
    const btn = this;
    if (!box) return;
    const opening = box.classList.contains('hidden');
    box.classList.toggle('hidden');
    btn.textContent = opening ? 'Show less' : 'See all';
    if (opening) {
        box.classList.remove('lgu-open-anim');
        void box.offsetWidth;
        box.classList.add('lgu-open-anim');
        box.querySelectorAll('.thumb').forEach(function (tile, i) {
            tile.style.animationDelay = Math.min(i * 30, 420) + 'ms';
        });
    }
});
const s=document.getElementById('jobSearch');s?.addEventListener('input',()=>{const v=s.value.toLowerCase();document.querySelectorAll('#jobs tbody tr').forEach(r=>r.hidden=!r.textContent.toLowerCase().includes(v))});
const p=document.querySelector('[data-poll]');if(p){setInterval(async()=>{try{const r=await (await fetch('notif_count.php')).json();const b=p.querySelector('.badge');b.textContent=r.n;b.hidden=!r.n}catch(e){}},15000)}
document.querySelectorAll('.fold').forEach(c=>{const b=c.querySelector('.foldbtn'),k='abeka-fold-'+c.dataset.fold;
const set=x=>{c.classList.toggle('collapsed',x);b.textContent=x?'+':'−';b.title=x?'Expand':'Minimize';b.setAttribute('aria-expanded',!x)};
try{set(localStorage.getItem(k)==='1')}catch(e){}
b.onclick=()=>{const x=!c.classList.contains('collapsed');set(x);try{localStorage.setItem(k,x?'1':'0')}catch(e){}}});
(()=>{const mb=document.getElementById('mailBtn'),mp=document.getElementById('mailPanel');if(!mb||!mp)return;const badge=mb.querySelector('.badge');
const close=()=>{mp.hidden=true;mb.setAttribute('aria-expanded','false')};
mb.onclick=async e=>{e.stopPropagation();if(!mp.hidden)return close();mp.hidden=false;mb.setAttribute('aria-expanded','true');mp.textContent='Loading…';
try{const rows=await(await fetch('notif_list.php')).json();mp.textContent='';const h=document.createElement('div');h.className='ddhead';h.textContent='Notifications';mp.appendChild(h);
if(!rows.length){const d=document.createElement('div');d.className='ddi';d.textContent='No notifications yet.';mp.appendChild(d)}
rows.forEach(r=>{const a=document.createElement(r.link?'a':'div');a.className='ddi'+(r.is_read==1?'':' unread');if(r.link)a.href=r.link;const m=document.createElement('div');m.textContent=r.message;const t=document.createElement('small');t.textContent=r.created;a.append(m,t);mp.appendChild(a)});
const all=document.createElement('a');all.className='ddall';all.href=mb.dataset.all;all.textContent='View all';mp.appendChild(all);
await fetch('notif_list.php',{method:'POST'});badge.hidden=true;badge.textContent='0'}catch(err){mp.textContent='Could not load notifications.'}};
document.addEventListener('click',e=>{if(!mp.hidden&&!mp.contains(e.target))close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()})})();document.querySelectorAll('[data-add-contact]').forEach(b=>b.addEventListener('click',()=>{
 const type=b.dataset.addContact, box=document.getElementById(type+'-contacts');
 if(!box||box.children.length>=5){if(box)alert('You can add up to 5 '+(type==='mobile'?'mobile numbers':'personal emails')+'.');return;}
 const row=document.createElement('div');row.className='row';
 const input=document.createElement('input');input.type=type==='email'?'email':'tel';input.name=type+'[]';input.placeholder=type==='email'?'e.g. yourname@example.com':'e.g. 0917 123 4567';
 const remove=document.createElement('button');remove.type='button';remove.className='btn sm';remove.textContent='×';remove.setAttribute('aria-label','Remove contact');remove.addEventListener('click',()=>row.remove());
 row.append(input,remove);box.appendChild(row);input.focus();
}));

// Smooth scroll-reveal animation for cards and grid items
(function () {
    if (!("IntersectionObserver" in window)) return;
    if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const targets = document.querySelectorAll(".elearn-card, .doc-item, .alert-contact");
    if (!targets.length) return;

    const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    targets.forEach(function (el, i) {
        el.classList.add("reveal-ready");
        el.style.transitionDelay = Math.min((i % 6) * 45, 225) + "ms";
        io.observe(el);
    });
})();
