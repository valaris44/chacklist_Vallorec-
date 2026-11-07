// Checklist loader and form logic - Vanilla JS

(() => {
  // Utilidades
  const el = id => document.getElementById(id);
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);
  const toast = (msg, timeout=3000) => {
    const t = el('toast');
    t.textContent = msg; t.style.display = 'block';
    setTimeout(()=> t.style.display = 'none', timeout);
  };

  // State
  let items = [];
  let currentSignatureData = null;

  // Status mapping (normalize)
  const statusMap = {
    'verificado': 'conforme', 'conforme': 'conforme',
    'atencao': 'atencao', 'nao_conforme': 'nao_conforme', 'na': 'na'
  };

  // Load items JSON and render
  async function loadTemplateItems(){
    try{
      const r = await fetch('../data/checklist_items.json');
      const j = await r.json();
      items = j.items.map(it => ({...it, status:'', obs:'', photos:[]}));
      renderItems();
    }catch(e){
      console.error(e); toast('Falha ao carregar itens');
    }
  }

  function statusClass(status){
    const s = statusMap[status] || '';
    return s;
  }

  function renderItems(){
    const container = el('itens-container');
    container.innerHTML = '';
    items.forEach((it, idx) => {
      const itemCard = document.createElement('div');
      itemCard.className = 'item';
      itemCard.dataset.index = idx;
      itemCard.innerHTML = `
        <div class="item-main">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <strong>${it.section || ''}</strong>
            <span id="badge-${idx}" class="status ${statusClass(it.status)}" style="min-width:72px;justify-content:center">${it.status ? it.status : 'Pendente'}</span>
          </div>
          <div>${it.description || ''}</div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <select id="status-${idx}">
              <option value="">-- Selecionar status --</option>
              <option value="conforme">Conforme</option>
              <option value="atencao">Atenção</option>
              <option value="nao_conforme">Não conforme</option>
              <option value="na">N/A</option>
            </select>
            <button type="button" class="btn secondary" data-action="add-photo" data-idx="${idx}">Adicionar foto</button>
            <button type="button" class="btn secondary" data-action="remove-item" data-idx="${idx}">Remover</button>
          </div>
          <textarea id="obs-${idx}" rows="2" placeholder="Observações...">${it.obs || ''}</textarea>
          <div class="photos" id="photos-${idx}">${(it.photos||[]).map(p=>' <img class="thumb" src="'+p+'">').join('')}</div>
        </div>
        <div style="text-align:right">
          <small class="hint">Fotos por item (max 5)</small>
        </div>
      `;
      container.appendChild(itemCard);

      // set select value and listeners
      const sel = itemCard.querySelector(`#status-${idx}`);
      sel.value = it.status || '';
      sel.addEventListener('change', (e) => {
        items[idx].status = e.target.value;
        const badge = el(`badge-${idx}`);
        badge.textContent = e.target.value || 'Pendente';
        badge.className = 'status ' + statusClass(e.target.value);
        updateProgress();
      });

      const obs = itemCard.querySelector(`#obs-${idx}`);
      obs.addEventListener('input', (e)=> items[idx].obs = e.target.value);

      // actions
      itemCard.querySelectorAll('[data-action]').forEach(btn=>{
        btn.addEventListener('click', (ev)=>{
          const action = btn.dataset.action;
          const i = parseInt(btn.dataset.idx,10);
          if(action === 'add-photo') openFileSelector(i);
          if(action === 'remove-item') { items.splice(i,1); renderItems(); updateProgress(); }
        });
      });
    });
    updateProgress();
  }

  // Add new item
  el('add-item').addEventListener('click', ()=>{
    items.push({section:'Custom', description:'', status:'', obs:'', photos:[]});
    renderItems();
  });

  // Photo selector logic (hidden input)
  const fileInput = document.createElement('input');
  fileInput.type = 'file'; fileInput.accept='image/*'; fileInput.multiple = true;
  fileInput.addEventListener('change', async (e)=>{
    const files = Array.from(e.target.files).slice(0,5);
    const idx = fileInput._targetIdx;
    for(const f of files){
      const data = await toDataURL(f);
      items[idx].photos.push(data);
    }
    renderItems();
    fileInput.value = '';
  });
  function openFileSelector(idx){ fileInput._targetIdx = idx; fileInput.click(); }

  function toDataURL(file){
    return new Promise((res, rej)=>{
      const fr = new FileReader();
      fr.onload = () => res(fr.result);
      fr.onerror = rej;
      fr.readAsDataURL(file);
    });
  }

  // Progress bar
  function updateProgress(){
    const total = items.length || 1;
    const done = items.filter(i=> (i.status && i.status !== '') ).length;
    const percent = Math.round((done/total)*100);
    el('progress-bar').style.width = percent + '%';
    // color change
    if(percent === 100) el('progress-bar').style.background = 'linear-gradient(90deg,var(--green),#22C55E)';
    else if(percent >= 60) el('progress-bar').style.background = 'linear-gradient(90deg,#F59E0B,#FBBF24)';
    else el('progress-bar').style.background = 'linear-gradient(90deg,#CBD5E1,#94A3B8)';
  }

  // Signature canvas (mouse + touch)
  const canvas = el('assinatura-canvas');
  const ctx = canvas.getContext('2d');
  let drawing = false;
  let last = null;

  function getRatio(){ return window.devicePixelRatio || 1; }

  function resizeCanvas(){
     const ratio = getRatio();
     const w = canvas.clientWidth;
     const h = canvas.clientHeight;
     // set canvas size in device pixels and reset transform (avoids scale accumulation)
     canvas.width = Math.floor(w * ratio);
     canvas.height = Math.floor(h * ratio);
     ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
     ctx.lineWidth = 2.4;
     ctx.lineCap = 'round';
     ctx.strokeStyle = '#0F172A';
     // clear any artifacts
     ctx.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
   }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  function pointerPos(e){
    if(e.touches && e.touches.length) e = e.touches[0];
    const rect = canvas.getBoundingClientRect();
    return {x: e.clientX - rect.left, y: e.clientY - rect.top};
  }
  canvas.addEventListener('pointerdown', (ev)=> { drawing = true; last = pointerPos(ev); });
  canvas.addEventListener('pointermove', (ev)=> {
    if(!drawing) return;
    const p = pointerPos(ev);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
  });
  window.addEventListener('pointerup', ()=> { if(drawing) { drawing = false; currentSignatureData = canvas.toDataURL('image/png'); } });

  el('clear-sign').addEventListener('click', ()=> {
    const ratio = getRatio();
    ctx.clearRect(0,0,canvas.width / ratio, canvas.height / ratio);
    currentSignatureData = null;
  });

  // Form submit
  el('checklist-form').addEventListener('submit', async (e)=>{
    e.preventDefault();
    // validations
    const date = el('data').value;
    const nome = el('nome').value;
    const matricula = el('matricula').value;
    if(!date || !nome || !matricula) { toast('Preencha data, nome e matrícula'); return; }
    // attach signature
    const payload = {
      data: {
        date,
        area: el('area').value,
        nome,
        matricula,
        setor: el('setor').value,
        responsavel: el('responsavel').value,
        obs_geral: el('obs-geral').value,
        signature: currentSignatureData,
        items: items.map(i=>({
          section: i.section || '',
          description: i.description || '',
          status: i.status || '',
          obs: i.obs || '',
          photos: i.photos || []
        }))
      }
    };

    const submitBtn = document.querySelector('#checklist-form button[type="submit"]');
    submitBtn.disabled = true;
    // send
    try{
      el('progress-bar').style.transition = 'width 0.4s ease';
      const res = await fetch('../api/checklist_submit.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });

      const rawText = await res.text();
      let serverResp;
      try {
        serverResp = JSON.parse(rawText);
      } catch(err) {
        // server returned non-JSON (HTML or text) — show useful excerpt
        serverResp = { text: rawText };
      }
      if(!res.ok) {
        console.error('Erro no servidor', res.status, serverResp);
        const message = serverResp.error || (serverResp.text ? serverResp.text.substring(0,1000) : 'Erro no envio');
        toast(message);
        if(serverResp.hint) console.info('Server hint:', serverResp.hint);
        return;
      }
      // success: show warning if present
      if(serverResp.warning) {
        toast('Gerado, porém: ' + serverResp.warning);
      } else {
        toast('Checklist enviado com sucesso');
      }
      // reset form minimally
      el('checklist-form').reset();
      items = []; await loadTemplateItems();
      ctx.clearRect(0,0,canvas.width,canvas.height); currentSignatureData = null;
      loadHistory();
    }catch(ex){
      console.error(ex);
      // If fetch failed (network), show concise message
      toast('Falha ao enviar checklist: ' + (ex.message || 'erro desconhecido'));
    }finally{
      if(submitBtn) submitBtn.disabled = false;
    }
  });

  // History
  async function loadHistory(){
    try{
      const r = await fetch('../api/checklist_history.php');
      const j = await r.json();
      const c = el('history-container');
      if(!j.data || j.data.length===0){ c.innerText = '(Nenhum registro carregado)'; return; }
      c.innerHTML = j.data.map(it => {
        const pdfLink = it.pdf_download ? `<a href="${escapeHtml(it.pdf_download)}" target="_blank" rel="noopener noreferrer">PDF</a>` : '';
        return `<div style="display:flex;justify-content:space-between;padding:8px;border-bottom:1px solid var(--border)">
          <div><strong>${escapeHtml(it.nome)}</strong><div class="hint">${escapeHtml(it.date)} • ${escapeHtml(it.area)}</div></div>
          <div style="display:flex;gap:8px;align-items:center">${pdfLink} <button class="btn secondary" data-id="${it.id}" data-action="view">Ver</button></div>
        </div>`;
      }).join('');
      // attach view handlers
      c.querySelectorAll('button[data-action="view"]').forEach(b=>{
        b.addEventListener('click', async ()=> {
          const id = b.dataset.id;
          const r = await fetch(`../api/checklist_detail.php?id=${id}`);
          const d = await r.json();
          if(!r.ok) throw new Error(d.message || 'Falha ao carregar detalhes');
          const modal = el('detail-modal'); el('detail-body').innerHTML = renderDetail(d);
          modal.hidden = false;
          el('close-modal').focus();
        });
      });
    }catch(e){ console.error(e); toast('Erro ao carregar histórico'); }
  }
  function renderDetail(d){
    if(!d||!d.data) return 'Erro ao carregar detalhes';
    const ck = d.data;
    return `<div><strong>${escapeHtml(ck.nome)}</strong> — ${escapeHtml(ck.date)} • ${escapeHtml(ck.area)} <div class="hint">Matrícula: ${escapeHtml(ck.matricula)} • ${escapeHtml(ck.setor || '')}</div>
      <hr>
      ${(ck.items||[]).map(it=>`<div style="margin-bottom:12px"><div><strong>${escapeHtml(it.description)}</strong> <span class="status ${statusClass(it.status)}" style="margin-left:8px">${escapeHtml(it.status)}</span></div><div>${escapeHtml(it.obs||'')}</div><div class="detail-photos">${(it.photos||[]).map(photo=>{const src=typeof photo==='string'?'../'+photo:photo.download;return src?`<a href="${escapeHtml(src)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(src)}" class="thumb" alt="Evidência do item"></a>`:''}).join('')}</div></div>`).join('')}
      <hr>
      <div><strong>Observações gerais</strong><div>${escapeHtml(ck.obs_geral || '')}</div></div>
      <div style="margin-top:12px">${ck.pdf_path ? `<a href="../${escapeHtml(ck.pdf_path)}" target="_blank" rel="noopener noreferrer" class="btn primary">Baixar PDF</a>` : ''}</div>
      </div>`;
  }

  const detailModal = el('detail-modal');
  const closeDetailModal = () => { detailModal.hidden = true; el('load-history').focus(); };
  el('close-modal').addEventListener('click', closeDetailModal);
  detailModal.addEventListener('click', event => {
    if(event.target.matches('[data-close-modal]')) closeDetailModal();
  });
  document.addEventListener('keydown', event => {
    if(event.key === 'Escape' && !detailModal.hidden) closeDetailModal();
  });
  el('load-history').addEventListener('click', loadHistory);

  // small initialization
  loadTemplateItems();
  loadHistory();
})();