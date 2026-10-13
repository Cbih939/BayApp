(() => {
  const cfg = window.BAY_CHAT || {};
  const $ = (s, r = document) => r.querySelector(s);
  const csrf = $('meta[name=csrf]')?.content || '';
  const baseTitle = document.title;
  const POLL = 5000, IDLE = 15000;

  /* indicador de não lidas (menu + título da aba) */
  const setUnread = n => {
    document.querySelectorAll('[data-chat-badge]').forEach(b => { b.textContent = n; b.hidden = !n; });
    document.title = (n ? `(${n}) ` : '') + baseTitle;
  };
  setUnread(cfg.unread || 0);

  const getJSON = async url => { const r = await fetch(url, { credentials: 'same-origin', cache: 'no-store' }); if (!r.ok) throw new Error(r.status); return r.json(); };

  const box = $('#chat');
  if (!box) {
    if (cfg.api) setInterval(() => { if (!document.hidden) getJSON(cfg.api + '&a=unread').then(d => setUnread(d.unread)).catch(() => {}); }, IDLE);
    return;
  }

  const log = $('#chatLog'), form = $('#chatForm'), input = $('#chatInput'), sendBtn = $('#chatSend');
  const api = box.dataset.api, client = box.dataset.client, viewer = box.dataset.viewer;
  let last = 0, lastDay = '';
  const pad = n => String(n).padStart(2, '0');

  const addMsg = m => {
    if (m.id <= last) return;
    last = m.id;
    const d = new Date(m.criado_em.replace(' ', 'T'));
    const day = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
    if (day !== lastDay) { lastDay = day; const s = document.createElement('div'); s.className = 'chat-day'; s.textContent = day; log.append(s); }
    const mine = m.sender === viewer;
    const el = document.createElement('div'); el.className = 'msg ' + (mine ? 'mine' : 'theirs') + (m.sender === 'team' ? ' team' : '');
    const who = document.createElement('small'); who.textContent = (mine ? 'Você' : m.autor) + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    const body = document.createElement('p'); body.textContent = m.corpo;
    el.append(who, body); log.append(el);
  };
  const render = (msgs, forceScroll) => {
    const near = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
    const before = last;
    msgs.forEach(addMsg);
    if (last !== before || forceScroll) { if (near || forceScroll) log.scrollTop = log.scrollHeight; }
    $('.chat-empty', log)?.remove();
    if (!last && !$('.chat-empty', log)) { const p = document.createElement('p'); p.className = 'chat-empty muted'; p.textContent = box.dataset.empty; log.append(p); }
  };

  try { render(JSON.parse(box.dataset.msgs || '[]'), true); } catch (e) { render([], true); }

  /* presença: a equipe vê o cliente; o cliente só vê o atendente que já respondeu (decidido no servidor) */
  const setPresence = d => {
    const el = $('#presence'); if (!el) return;
    const team = el.dataset.viewer === 'team';
    const on = team ? !!d.online : !!d.atendente;
    $('.dot', el).classList.toggle('on', on);
    $('span', el).textContent = team ? (on ? 'Cliente online' : 'Cliente offline') : (on ? d.atendente.split(' ')[0] + ' está online' : 'Equipe offline');
  };

  const poll = async () => {
    if (document.hidden) return;
    try {
      const d = await getJSON(`${api}&a=poll&client_id=${client}&after=${last}`);
      render(d.messages); setUnread(d.unread); setPresence(d);
      if (viewer === 'team') refreshList();
    } catch (e) { /* tenta de novo no próximo ciclo */ }
  };
  setInterval(poll, POLL); poll();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

  /* lista de conversas (equipe) */
  const list = $('#convList');
  const refreshList = async () => {
    if (!list) return;
    const d = await getJSON(api + '&a=list');
    const cur = +list.dataset.current;
    list.replaceChildren(...d.conversas.map(c => {
      const a = document.createElement('a'); a.className = 'conv' + (c.id === cur ? ' active' : ''); a.href = `index.php?p=chat&id=${c.id}`;
      const wrap = document.createElement('span'); wrap.className = 'avatar-wrap';
      const av = document.createElement('span'); av.className = 'avatar'; av.textContent = c.nome.split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase();
      const dot = document.createElement('i'); dot.className = 'dot' + (c.online ? ' on' : ''); dot.title = c.online ? 'Online' : 'Offline'; wrap.append(av, dot);
      const mn = document.createElement('span'); mn.className = 'conv-main';
      const b = document.createElement('b'); b.textContent = c.nome; const s = document.createElement('small'); s.textContent = c.preview; mn.append(b, s);
      a.append(wrap, mn);
      if (c.unread && c.id !== cur) { const e = document.createElement('em'); e.className = 'badge'; e.textContent = c.unread; a.append(e); }
      return a;
    }));
  };

  /* envio */
  input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
  input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const corpo = input.value.trim(); if (!corpo) return;
    sendBtn.disabled = true;
    try {
      const fd = new FormData(); fd.set('a', 'send'); fd.set('client_id', client); fd.set('corpo', corpo);
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': csrf } });
      const d = await r.json();
      if (!r.ok) throw new Error(d.erro || 'Não foi possível enviar.');
      input.value = ''; input.style.height = 'auto'; render(d.messages, true); setUnread(d.unread); setPresence(d);
    } catch (err) {
      const t = document.createElement('div'); t.className = 'toast err'; t.textContent = err.message; $('#toasts')?.append(t); setTimeout(() => t.remove(), 4200);
    } finally { sendBtn.disabled = false; input.focus(); }
  });
})();
