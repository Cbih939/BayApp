/* Tarefas (modal + checklist), calendário de postagem e listas dinâmicas de formulário. */
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = $('meta[name=csrf]')?.content || '';
  const post = async (url, data) => {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf }, body: new URLSearchParams(data) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.erro || 'Erro ao salvar');
    return j;
  };
  const toast = (msg, type = '') => {
    const box = $('#toasts'); if (!box) return;
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; box.append(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 3800);
  };
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  const fmtDT = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) + (s.length > 10 ? ' às ' + s.slice(11, 16) : '') : '—';
  const initials = n => (n || '?').trim().split(/\s+/).map((w, i, a) => (i === 0 || i === a.length - 1) ? w[0] : '').join('').toUpperCase();

  /* ---------- listas dinâmicas (checklist da tarefa, itens do pacote) ---------- */
  const addRow = (dlg, name, data = {}) => {
    const box = $(`[data-rows="${name}"]`, dlg), tpl = $(`template[data-tpl="${name}"]`, dlg); if (!box || !tpl) return null;
    const row = tpl.content.firstElementChild.cloneNode(true);
    $$('[data-k]', row).forEach(i => { const v = data[i.dataset.k]; if (v != null) i.value = v; });
    const chk = $('.chk-box', row); if (chk) { chk.checked = +data.feito === 1; row.classList.toggle('done', chk.checked); }
    box.append(row); return row;
  };
  const clearRows = dlg => $$('[data-rows]', dlg).forEach(b => { b.innerHTML = ''; });
  document.addEventListener('bay:reset', e => {
    const dlg = e.target; if (!(dlg instanceof HTMLDialogElement)) return;
    clearRows(dlg); $$('[data-rows][data-start]', dlg).forEach(b => { for (let i = 0; i < +b.dataset.start; i++) addRow(dlg, b.dataset.rows); });
  });
  document.addEventListener('bay:fill', e => {
    const dlg = e.target, items = e.detail?.itens; if (!Array.isArray(items)) return;
    clearRows(dlg); items.forEach(it => addRow(dlg, $('[data-rows]', dlg)?.dataset.rows, it));
  });
  document.addEventListener('change', e => {
    const c = e.target.closest?.('.chk-box'); if (!c) return;
    const row = c.closest('.chk-row'); $('[data-k=feito]', row).value = c.checked ? 1 : 0; row.classList.toggle('done', c.checked);
  });
  document.addEventListener('click', e => {
    const add = e.target.closest('[data-addrow]');
    if (add) { const r = addRow(add.closest('dialog'), add.dataset.addrow); r?.querySelector('input[type=text],input[type=number]')?.focus(); return; }
    const del = e.target.closest('[data-delrow]'); if (del) del.closest('.chk-row,.plan-row')?.remove();
  });
  // Enter no último item do checklist cria o próximo
  document.addEventListener('keydown', e => {
    if (e.key !== 'Enter' || !e.target.matches?.('.chk-row input[type=text]')) return;
    e.preventDefault(); const dlg = e.target.closest('dialog'); addRow(dlg, 'itens')?.querySelector('input[type=text]').focus();
  });

  /* ---------- tarefas: modal de leitura + checklist ---------- */
  const tv = $('#dlg-taskview');
  if (tv) {
    const host = $('#tv-item');
    let cur = null;
    const cardOf = id => $(`.task[data-id="${id}"]`);
    const progress = () => {
      const done = cur.itens.filter(i => +i.feito).length, total = cur.itens.length;
      $('#tv-count').textContent = total ? `(${done}/${total})` : '';
      $('#tv-bar').style.width = total ? Math.round(100 * done / total) + '%' : '0%';
      $('.tv-bar', tv).hidden = !total;
      host.dataset.item = JSON.stringify(cur);
      const card = cardOf(cur.id);
      if (card) {
        card.dataset.item = JSON.stringify(cur);
        let p = $('.tprog', card);
        if (total && !p) { p = el('div', 'tprog'); p.innerHTML = '<span class="bar"><i></i></span><small></small>'; ($('.chips', card) || card).before(p); }
        if (p) { p.hidden = !total; $('i', p).style.width = total ? Math.round(100 * done / total) + '%' : '0%'; $('small', p).textContent = `${done}/${total}`; }
      }
    };
    const li = it => {
      const row = el('li'), lab = el('label', 'tv-i' + (+it.feito ? ' done' : '')), c = el('input'); c.type = 'checkbox'; c.checked = !!+it.feito;
      c.addEventListener('change', async () => {
        try { await post('index.php?p=task_api', { a: 'item_toggle', id: it.id, feito: c.checked ? 1 : 0 }); it.feito = c.checked ? 1 : 0; lab.classList.toggle('done', c.checked); progress(); }
        catch (err) { c.checked = !c.checked; toast(err.message, 'err'); }
      });
      lab.append(c, el('span', '', it.texto)); row.append(lab); return row;
    };
    const openTask = card => {
      cur = JSON.parse(card.dataset.item); cur.itens = cur.itens || [];
      $('#tv-client').textContent = cur.cliente || 'Sem cliente';
      $('#tv-title').textContent = cur.titulo;
      const chips = $('#tv-chips'); chips.innerHTML = '';
      [[cur.status_label, ''], [cur.prio_label, 'prio-chip'], [cur.prazo_label, 'tag-' + cur.prazo_cls], [cur.responsavel ? 'Resp.: ' + cur.responsavel : '', 'dim']].forEach(([t, c]) => { if (t) chips.append(el('span', 'chip ' + c, t)); });
      const a = $('#tv-link'); a.hidden = !cur.link; if (cur.link) { a.href = cur.link; $('span', a).textContent = cur.link.replace(/^https?:\/\/(www\.)?/, '').slice(0, 48); }
      const d = $('#tv-desc'); d.textContent = cur.descricao || 'Sem detalhes.'; d.classList.toggle('muted', !cur.descricao);
      const ul = $('#tv-list'); ul.innerHTML = ''; cur.itens.forEach(it => ul.append(li(it)));
      if (!cur.itens.length) ul.append(el('li', 'muted', 'Nenhum item no checklist ainda.'));
      const mv = $('#tv-move'); mv.elements.id.value = cur.id; mv.elements.status.value = cur.status;
      progress(); tv.showModal();
    };
    document.addEventListener('click', e => {
      const card = e.target.closest('.task'); if (!card || e.target.closest('a,button,form,input,select,label')) return;
      openTask(card);
    });
    document.addEventListener('keydown', e => {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.classList?.contains('task')) { e.preventDefault(); openTask(e.target); }
    });
    $('#tv-add').addEventListener('submit', async e => {
      e.preventDefault(); const inp = $('#tv-new'), texto = inp.value.trim(); if (!texto) return;
      try {
        const r = await post('index.php?p=task_api', { a: 'item_add', task_id: cur.id, texto });
        const it = { id: r.id, texto, feito: 0 }; cur.itens.push(it);
        const ul = $('#tv-list'); if (cur.itens.length === 1) ul.innerHTML = ''; ul.append(li(it)); inp.value = ''; progress();
      } catch (err) { toast(err.message, 'err'); }
    });
    tv.addEventListener('click', e => { if (e.target.closest('[data-edit]')) tv.close(); }, true);   // abre o editor sem empilhar modais
  }

  /* ---------- calendário de postagem ---------- */
  const dataEl = $('#cal-data');
  if (dataEl) {
    const { posts, client } = JSON.parse(dataEl.textContent);
    const byId = Object.fromEntries(posts.map(p => [p.id, p]));
    const pv = $('#dlg-postview'), dlgPost = $('#dlg-post'), dlgDay = $('#dlg-day');
    const IMG = /\.(png|jpe?g|gif|webp|avif)(\?.*)?$/i;

    const openNew = (pref = {}) => {
      const f = $('form', dlgPost); f.reset(); f.elements.id.value = '';
      $('[data-title]', dlgPost).textContent = $('[data-title]', dlgPost).dataset.title.split('|')[0];
      Object.entries(pref).forEach(([k, v]) => { if (f.elements[k] && v != null) f.elements[k].value = v; });
      dlgPost.showModal();
    };
    const openView = id => {
      const p = byId[id]; if (!p) return;
      dlgDay?.close();
      $('#pv-item').dataset.item = JSON.stringify(p);
      $('#pv-av').textContent = initials(p.cliente); $('#pv-client').textContent = p.cliente;
      $('#pv-sub').textContent = `${p.canal_label} · ${p.formato_label}` + (p.titulo ? ' · ' + p.titulo : '');
      const cap = $('#pv-caption'); cap.textContent = p.legenda || 'Sem legenda.'; cap.classList.toggle('muted', !p.legenda);
      const m = $('#pv-media'); m.innerHTML = '';
      if (p.midia_url && IMG.test(p.midia_url)) { const i = el('img'); i.src = p.midia_url; i.alt = 'Arte do post'; i.loading = 'lazy'; i.referrerPolicy = 'no-referrer'; m.append(i); }
      else if (p.midia_url) { const a = el('a', 'pv-ph', ''); a.href = p.midia_url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.append(el('b', '', 'Abrir arte'), el('small', 'muted', p.midia_url.replace(/^https?:\/\/(www\.)?/, '').slice(0, 50))); m.append(a); }
      else m.append(el('div', 'pv-ph none', 'Nenhuma arte anexada'));
      $('#pv-i-canal').textContent = `${p.canal_label} — ${p.formato_label}`;
      $('#pv-i-status').textContent = 'Status: ' + p.status_label;
      $('#pv-i-when').textContent = (p.status === 'publicado' ? 'Publicado em ' + fmtDT(p.publicado_em) : 'Programado para ' + p.dia_fmt + (p.hora ? ' às ' + p.hora : ''));
      $('#pv-i-by').textContent = 'Criado em ' + fmtDT(p.criado_em) + (p.autor ? ' por ' + p.autor : '');
      const h = $('#pv-hist'); h.innerHTML = '';
      [['Criado' + (p.autor ? ' por ' + p.autor : ''), p.criado_em], p.atualizado_em && p.atualizado_em !== p.criado_em ? ['Última edição', p.atualizado_em] : null, p.publicado_em ? ['Publicado', p.publicado_em] : null]
        .filter(Boolean).forEach(([t, d]) => { const li = el('li'); li.append(el('b', '', t), el('small', 'muted', fmtDT(d))); h.append(li); });
      $$('[data-pv]', pv).forEach(b => b.classList.toggle('active', b.dataset.pv === 'preview')); $$('.pv-pane', pv).forEach(x => x.hidden = x.dataset.pane !== 'preview');
      const pub = $('#pv-pub'); if (pub) { pub.elements.id.value = p.id; pub.hidden = p.status === 'publicado'; }
      const del = $('#pv-del'); if (del) del.elements.id.value = p.id;
      pv.showModal();
    };
    document.addEventListener('click', e => {
      const chip = e.target.closest('[data-post]'); if (chip) { openView(+chip.dataset.post); return; }
      const add = e.target.closest('[data-newpost]'); if (add) { openNew({ dia: add.dataset.newpost || new Date().toISOString().slice(0, 10) }); return; }
      const more = e.target.closest('[data-day]');
      if (more) {
        const d = more.dataset.day, list = $('#day-list'); list.innerHTML = '';
        $('#day-title').textContent = 'Posts de ' + d.slice(8, 10) + '/' + d.slice(5, 7);
        posts.filter(p => p.dia === d).forEach(p => {
          const b = el('button', 'cal-chip st-' + p.status); b.type = 'button'; b.dataset.post = p.id;
          b.append(el('i', 'chb ch-' + p.canal, p.sigla), el('span', 'cal-nm', (client ? '' : p.cliente + ' · ') + (p.titulo || p.formato_label)), el('time', '', p.hora || '')); list.append(b);
        });
        dlgDay.showModal(); return;
      }
      const tab = e.target.closest('[data-pv]');
      if (tab) { $$('[data-pv]', pv).forEach(b => b.classList.toggle('active', b === tab)); $$('.pv-pane', pv).forEach(x => x.hidden = x.dataset.pane !== tab.dataset.pv); }
    });
    $('#pv-clone')?.addEventListener('click', () => {
      const p = JSON.parse($('#pv-item').dataset.item); pv.close();
      openNew({ client_id: p.client_id, dia: p.dia, hora: p.hora || '', canal: p.canal, formato: p.formato, titulo: p.titulo ? p.titulo + ' (cópia)' : '', legenda: p.legenda, midia_url: p.midia_url, status: 'planejado' });
    });
    pv.addEventListener('click', e => { if (e.target.closest('[data-edit]')) pv.close(); }, true);
  }
})();
