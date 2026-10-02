(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = $('meta[name=csrf]')?.content || '';
  const store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };

  /* toasts */
  const toast = (msg, type = '') => {
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; $('#toasts').append(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 4200);
  };
  $$('#toasts .toast').forEach((t, i) => setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 4500 + i * 600));

  /* tema e menu mobile */
  $('#themeBtn')?.addEventListener('click', () => {
    const n = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = n; store.set('bay-theme', n);
  });
  const sb = $('#sidebar'), scrim = $('#scrim');
  const menu = open => { sb.classList.toggle('open', open); scrim.classList.toggle('show', open); };
  $('#menuBtn')?.addEventListener('click', () => menu(true)); scrim?.addEventListener('click', () => menu(false));

  /* contadores animados */
  $$('.count').forEach(el => {
    const to = +el.dataset.to || 0, t0 = performance.now(), dur = 900;
    const tick = n => { const p = Math.min((n - t0) / dur, 1); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  });

  /* brilho que segue o mouse nos cards */
  document.addEventListener('pointermove', e => {
    const c = e.target.closest?.('.card'); if (!c) return;
    const r = c.getBoundingClientRect(); c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });

  /* filtro rápido */
  $('#quickFilter')?.addEventListener('input', e => {
    const q = e.target.value.toLowerCase().trim();
    $$('.content .card').forEach(c => { const host = c.closest('.anim-up') && c.parentElement.classList.contains('anim-up') ? c.parentElement : c; host.style.display = !q || c.textContent.toLowerCase().includes(q) ? '' : 'none'; });
  });

  /* confirmação de exclusão */
  document.addEventListener('submit', e => { const m = e.target.dataset?.confirm; if (m && !confirm(m)) e.preventDefault(); });

  /* abas */
  $$('[data-tab]').forEach(b => b.addEventListener('click', () => {
    $$('.tab').forEach(x => x.classList.toggle('active', x === b));
    $$('.tabpane').forEach(p => p.classList.toggle('active', p.id === b.dataset.tab));
  }));

  /* modais */
  const setCat = (dlg, cat) => $$('.catchip', dlg).forEach(c => c.classList.toggle('on', c.dataset.cat === cat));
  const resetForm = dlg => {
    const f = $('form', dlg); f.reset();
    if (f.elements.id) f.elements.id.value = '';
    if (f.elements.categoria) f.elements.categoria.value = 'outro';
    $('[data-title]', dlg) && ($('[data-title]', dlg).textContent = $('[data-title]', dlg).dataset.title.split('|')[0]);
    $$('[data-keep]', dlg).forEach(x => x.hidden = true); setCat(dlg, 'outro'); $('details.paste', dlg)?.removeAttribute('open');
    if (f.elements.senha) f.elements.senha.required = f.elements.action.value === 'user_save';
  };
  const openDlg = dlg => { dlg.showModal(); dlg.querySelector('input:not([type=hidden]):not([type=checkbox]),select')?.focus(); };
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (o) { const d = $('#' + o.dataset.open); if (o.hasAttribute('data-new')) resetForm(d); openDlg(d); return; }
    const ed = e.target.closest('[data-edit]');
    if (ed) {
      const d = $('#' + ed.dataset.edit), item = JSON.parse(ed.closest('[data-item]').dataset.item), f = $('form', d);
      resetForm(d);
      Object.entries(item).forEach(([k, v]) => {
        const el = f.elements[k]; if (!el || el.type === 'file') return;
        if (el.type === 'checkbox') el.checked = !!+v; else if (k === 'valor' && v !== null) el.value = String(v).replace('.', ','); else el.value = v ?? '';
      });
      if (f.elements.senha) { f.elements.senha.value = ''; f.elements.senha.required = false; }
      $$('[data-keep]', d).forEach(x => x.hidden = false);
      const t = $('[data-title]', d); if (t) t.textContent = t.dataset.title.split('|')[1];
      setCat(d, item.categoria); openDlg(d); return;
    }
    if (e.target.closest('[data-close]')) { e.target.closest('dialog').close(); return; }
    if (e.target.tagName === 'DIALOG') e.target.close();

    /* copiar / revelar */
    const cp = e.target.closest('[data-copy]');
    if (cp) { copy(cp.dataset.copy); return; }
    const rv = e.target.closest('[data-reveal]');
    if (rv) { reveal(rv.dataset.reveal).then(pw => { const c = $(`.pw[data-id="${rv.dataset.reveal}"]`); if (c) c.textContent = c.dataset.on ? '••••••••••' : pw; if (c) c.dataset.on = c.dataset.on ? '' : '1'; }); return; }
    const cpw = e.target.closest('[data-copy-pw]');
    if (cpw) { reveal(cpw.dataset.copyPw).then(pw => copy(pw)); return; }

    /* gerar senha */
    if (e.target.closest('[data-gen]')) {
      const inp = e.target.closest('.pwwrap').querySelector('input'), chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?', a = new Uint32Array(18);
      crypto.getRandomValues(a); inp.value = [...a].map(n => chars[n % chars.length]).join(''); toast('Senha forte gerada'); return;
    }

    /* tipos rápidos */
    const cc = e.target.closest('.catchip');
    if (cc) {
      const f = cc.closest('form'); f.elements.categoria.value = cc.dataset.cat; setCat(cc.closest('dialog'), cc.dataset.cat);
      if (!f.elements.titulo.value || f.elements.titulo.dataset.auto) { f.elements.titulo.value = cc.dataset.name; f.elements.titulo.dataset.auto = 1; }
      if (!f.elements.url.value || f.elements.url.dataset.auto) { f.elements.url.value = cc.dataset.url; f.elements.url.dataset.auto = 1; }
      f.elements.login.focus(); return;
    }

    /* colar tudo */
    if (e.target.closest('[data-parse]')) {
      const box = e.target.closest('.paste'), f = box.closest('form'), txt = $('textarea', box).value;
      const m = (re) => (txt.match(re) || [])[1]?.trim();
      const url = m(/(https?:\/\/\S+)/i);
      let login = m(/(?:usu[aá]rio|user(?:name)?|login|e-?mail)\s*[:=\-]\s*(\S+)/i), pass = m(/(?:senha|password|pass)\s*[:=\-]\s*(\S+)/i);
      if (!login && !pass) { const p = txt.split(/\n/)[0].match(/^\s*(\S+?)[:|\s]\s*(\S+)\s*$/); if (p) [login, pass] = [p[1], p[2]]; }
      if (url) f.elements.url.value = url; if (login) f.elements.login.value = login; if (pass) f.elements.senha.value = pass;
      toast(login || pass || url ? 'Campos preenchidos' : 'Não entendi o texto colado', login || pass || url ? '' : 'err');
    }
  });
  const copy = async txt => { try { await navigator.clipboard.writeText(txt); toast('Copiado!'); } catch (e) { prompt('Copie:', txt); } };
  const reveal = async id => {
    const r = await fetch('index.php?p=ajax', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf }, body: 'id=' + id });
    if (!r.ok) { toast('Sem permissão', 'err'); throw 0; } return (await r.json()).senha;
  };
  /* detectar tipo do link */
  $$('[data-autotype]').forEach(i => i.addEventListener('input', () => {
    const u = i.value.toLowerCase(), s = i.form.elements.tipo;
    s.value = u.includes('drive.google') ? 'drive' : u.includes('canva.com') ? 'canva' : u.includes('calendar.google') ? 'agenda' : u.includes('docs.google') || u.includes('sheets.google') ? 'docs' : u.includes('figma.com') ? 'figma' : s.value;
  }));
  /* título/url digitados manualmente deixam de ser "automáticos" */
  document.addEventListener('input', e => { if (e.target.dataset?.auto) delete e.target.dataset.auto; });

  /* tour guiado */
  const steps = [
    ['dashboard', 'Seu painel', 'Resumo de clientes, vencimentos e alertas recentes.'],
    ['clientes', 'Clientes', 'Cada cliente tem uma página com senhas, vencimentos e links.'],
    ['senhas', 'Cofre de senhas', 'Todos os acessos criptografados, com copiar e gerar senha forte.'],
    ['vencimentos', 'Vencimentos', 'Domínios, hospedagens e contas com alertas em 30, 15 e 7 dias.'],
    ['links', 'Pastas & Links', 'Drive, Canva e Google Agenda de cada cliente.'],
    ['usuarios', 'Equipe', 'Crie administradores, designers e devs.'],
    ['config', 'Configurações', 'Ligue os alertas por e-mail e WhatsApp e configure o cron.'],
    ['guia', 'Guia', 'Volte aqui sempre que precisar do passo a passo.'],
  ].filter(s => $(`[data-tour="${s[0]}"]`));
  let idx = 0; const tour = $('#tour'), spot = $('#tourSpot'), box = $('#tourBox');
  const show = () => {
    const [k, t, d] = steps[idx], el = $(`[data-tour="${k}"]`); if (window.innerWidth <= 860) menu(true);
    const r = el.getBoundingClientRect();
    Object.assign(spot.style, { left: r.left - 6 + 'px', top: r.top - 4 + 'px', width: r.width + 12 + 'px', height: r.height + 8 + 'px' });
    $('#tourTitle').textContent = t; $('#tourText').textContent = d; $('#tourStep').textContent = `${idx + 1}/${steps.length}`;
    $('#tourNext').textContent = idx === steps.length - 1 ? 'Concluir' : 'Próximo';
    const left = Math.min(r.right + 20, window.innerWidth - 340); box.style.left = Math.max(10, left) + 'px'; box.style.top = Math.min(Math.max(10, r.top - 10), window.innerHeight - 190) + 'px';
  };
  const end = () => { tour.hidden = true; store.set('bay-tour', 'done'); menu(false); };
  const start = () => { idx = 0; tour.hidden = false; show(); };
  $('#tourNext')?.addEventListener('click', () => { if (idx >= steps.length - 1) end(); else { idx++; show(); } });
  $('#tourSkip')?.addEventListener('click', end);
  $('#startTour')?.addEventListener('click', start); $('#startTour2')?.addEventListener('click', start);
  window.addEventListener('resize', () => !tour.hidden && show());
  if (/[?&]welcome=1/.test(location.search) && store.get('bay-tour') !== 'done') setTimeout(start, 700);
})();
