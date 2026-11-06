<?php
require_once __DIR__ . '/_reports.php';
?>
<div class="sec-h big"><div><h3>Relatórios gerais</h3><p class="muted">Todos os clientes juntos ou um por vez, em PDF, Excel (CSV) ou resumo pelo WhatsApp.</p></div></div>
<?php render_reports(report_filters($_GET), ['p' => 'relatorios'], false);
