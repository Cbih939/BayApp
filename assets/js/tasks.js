(() => {
  const board = document.getElementById('kanban'); if (!board) return;
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  let dragging = null;
  board.addEventListener('dragstart', e => { const c = e.target.closest('.task'); if (!c) return; dragging = c; c.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.id); });
  board.addEventListener('dragend', () => { dragging?.classList.remove('dragging'); dragging = null; board.querySelectorAll('.kcol').forEach(c => c.classList.remove('over')); });
  board.addEventListener('dragover', e => { const col = e.target.closest('.kcol'); if (!col || !dragging) return; e.preventDefault(); board.querySelectorAll('.kcol').forEach(c => c.classList.toggle('over', c === col)); });
  board.addEventListener('drop', async e => {
    const col = e.target.closest('.kcol'); if (!col || !dragging) return; e.preventDefault();
    const card = dragging, from = card.closest('.kcol');
    if (from === col) return;
    col.querySelector('.kbody').prepend(card);
    const body = new URLSearchParams({ id: card.dataset.id, status: col.dataset.status });
    try {
      const r = await fetch(board.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf }, body });
      if (!r.ok) throw 0;
      location.reload(); // recarrega para atualizar contadores, prazos e setas
    } catch (err) { from.querySelector('.kbody').prepend(card); alert('Não foi possível mover a tarefa. Tente de novo.'); }
  });
})();
