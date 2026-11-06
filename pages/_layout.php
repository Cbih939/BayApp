<?php
$me = user();
$title = $p === 'cliente' ? 'Cliente' : ($p === 'seguranca' ? 'Minha segurança' : $NAV[$p][0]);
$chatN = chat_unread_team();
$due = can('billing.edit') ? (int) val("SELECT COUNT(*) FROM internal_bills WHERE status = 'pendente' AND vencimento <= ?", [date('Y-m-d', strtotime('+7 day'))]) : 0;
$payLate = can('billing.view') ? (int) val("SELECT COUNT(*) FROM billings WHERE status = 'pendente' AND vencimento < ?" . client_scope('client_id'), [date('Y-m-d')]) : 0;
$taskOver = tasks_overdue_mine((int) $me['id']);
?><!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — <?= e(setting('empresa_nome', 'BayApp')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png"><link rel="apple-touch-icon" href="assets/img/favicon.png">
<?= inline_css() ?>
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
</head><body><?= css_stale_banner() ?>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url() ?>"><?= brand_logo(setting('empresa_nome', 'BayGroups')) ?></a>
    <nav aria-label="Menu principal">
      <?php $alerts = $taskOver + $due + $payLate; $current = $p === 'cliente' ? 'clientes' : (isset($NAV[$p]) && !in_array($p, array_merge($MENU, $MENU_BOTTOM), true) ? 'dashboard' : $p);
      $link = function (string $slug) use ($NAV, $current, $chatN, $alerts): void { [$label, $ico, $perm] = $NAV[$slug]; if ($perm && !can($perm)) return; ?>
        <a href="<?= url($slug) ?>" data-tour="<?= $slug ?>" class="<?= $current === $slug ? 'active' : '' ?>" <?= $current === $slug ? 'aria-current="page"' : '' ?>><?= icon($ico, 18) ?><span><?= e($label) ?></span>
          <?php if ($slug === 'clientes'): ?><em class="badge" data-chat-badge title="Mensagens não lidas" <?= $chatN ? '' : 'hidden' ?>><?= $chatN ?></em><?php endif; ?>
          <?php if ($slug === 'dashboard' && $alerts): ?><em class="badge" title="Tarefas suas em atraso e vencimentos próximos"><?= $alerts ?></em><?php endif; ?></a>
      <?php };
      foreach ($MENU as $slug) $link($slug); ?>
      <div class="nav-spacer"></div>
      <?php foreach ($MENU_BOTTOM as $slug) $link($slug); ?>
    </nav>
    <div class="side-foot">
      <div class="me"><span class="avatar sm"><?= e(initials($me['nome'])) ?></span>
        <div><b><?= e($me['nome']) ?></b><small><?= e(ROLES[$me['papel']]) ?></small></div></div>
      <div class="side-btns"><button class="theme-tg" id="themeBtn" title="Tema claro/escuro" aria-label="Alternar tema claro/escuro"><span class="tg-l"><?= icon('sun', 15) ?></span><span class="tg-d"><?= icon('moon', 15) ?></span></button>
        <a class="icon-btn" href="<?= url('seguranca') ?>" title="Minha segurança: senha e verificação em duas etapas"><?= icon('shield') ?></a>
        <a class="icon-btn" href="<?= url('logout') ?>" title="Sair"><?= icon('logout') ?></a></div>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <main class="main">
    <header class="topbar"><button class="icon-btn menu-btn" id="menuBtn"><?= icon('menu') ?></button>
      <h2 class="page-title"><?php
        $crumbs = $pageCrumb ?? (isset($NAV[$p]) && !in_array($p, array_merge($MENU, $MENU_BOTTOM), true) ? [[url('dashboard'), 'Início'], [null, $title]] : [[null, $title]]);
        foreach ($crumbs as $i => [$href, $label]): if ($i): ?><i class="crumb-sep">/</i><?php endif; if ($href): ?><a class="crumb" href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span><?= e($label) ?></span><?php endif; endforeach; ?></h2>
      <div class="searchbox"><?= icon('search', 18) ?><input type="search" id="quickFilter" placeholder="Filtrar nesta página…"></div></header>
    <div class="content" id="content"><?= $content ?></div>
  </main>
</div>

<div class="toasts" id="toasts">
  <?php foreach (pull_flash() as [$type, $msg]): ?><div class="toast <?= e($type) ?>"><?= e($msg) ?></div><?php endforeach; ?>
</div>


<div class="tour" id="tour" hidden><div class="tour-spot" id="tourSpot"></div>
  <div class="tour-box" id="tourBox"><b id="tourTitle"></b><p id="tourText"></p>
    <div class="tour-nav"><button class="btn ghost sm" id="tourSkip">Pular</button><span id="tourStep" class="muted"></span><button class="btn primary sm" id="tourNext">Próximo</button></div></div></div>
<?= inline_js('assets/js/app.js') ?>
<?= inline_js('assets/js/work.js') ?>
<script>window.BAY_CHAT={api:'index.php?p=chat_api',unread:<?= $chatN ?>}</script>
<?= inline_js('assets/js/chat.js') ?>
</body></html>
