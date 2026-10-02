<?php
declare(strict_types=1);

/** Processa POSTs. Retorna a URL de retorno. Lança RuntimeException com mensagem amigável para erros de validação. */
function handle_action(string $a): string
{
    $ret = safe_return($_POST['return'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $u = user();

    switch ($a) {
        /* ---------------- Clientes ---------------- */
        case 'client_save':
            require_can('clients.edit');
            $nome = post('nome');
            if ($nome === '') throw new RuntimeException('Informe o nome do cliente.');
            $d = ['nome' => $nome, 'empresa' => post_or_null('empresa'), 'email' => post_or_null('email'),
                'telefone' => post_or_null('telefone'), 'whatsapp' => post_or_null('whatsapp'), 'documento' => post_or_null('documento'),
                'site' => post_or_null('site'), 'notas' => post_or_null('notas'),
                'status' => post('status') === 'inativo' ? 'inativo' : 'ativo'];
            if ($id) { update('clients', $id, $d); flash('Cliente atualizado.'); }
            else { $id = insert('clients', $d + ['criado_em' => now()]); flash('Cliente cadastrado! Agora adicione senhas, vencimentos e links.'); $ret = url('cliente', ['id' => $id]); }
            audit('client_save', $nome);
            return $ret;

        case 'client_delete':
            require_can('delete');
            q('DELETE FROM clients WHERE id = ?', [$id]);
            audit('client_delete', "#$id"); flash('Cliente excluído com todos os dados vinculados.');
            return url('clientes');

        /* ---------------- Senhas ---------------- */
        case 'cred_save':
            require_can('creds.edit');
            $cid = (int) post('client_id');
            if (!row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Escolha o cliente.');
            $cat = array_key_exists(post('categoria'), CRED_CATS) ? post('categoria') : 'outro';
            $titulo = post('titulo') ?: CRED_CATS[$cat][0];
            $d = ['client_id' => $cid, 'categoria' => $cat, 'titulo' => $titulo, 'url' => post_or_null('url'),
                'login' => post_or_null('login'), 'notas' => post_or_null('notas'), 'atualizado_em' => now()];
            $senha = (string) ($_POST['senha'] ?? '');
            if ($id) {
                if ($senha !== '') $d['senha_enc'] = encrypt_secret($senha);
                update('credentials', $id, $d); flash('Acesso atualizado.');
            } else {
                insert('credentials', $d + ['senha_enc' => encrypt_secret($senha), 'criado_por' => $u['id'], 'criado_em' => now()]);
                flash('Acesso salvo com segurança 🔐');
            }
            audit('cred_save', $titulo);
            return $ret;

        case 'cred_delete':
            require_can('delete');
            q('DELETE FROM credentials WHERE id = ?', [$id]); audit('cred_delete', "#$id"); flash('Acesso excluído.');
            return $ret;

        /* ---------------- Vencimentos ---------------- */
        case 'bill_save':
            require_can('billing.edit');
            $cid = (int) post('client_id');
            if (!row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Escolha o cliente.');
            $venc = post('vencimento');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) throw new RuntimeException('Informe a data de vencimento.');
            $tipo = array_key_exists(post('tipo'), BILL_TYPES) ? post('tipo') : 'outro';
            $rec = in_array(post('recorrencia'), ['mensal', 'anual'], true) ? post('recorrencia') : 'nenhuma';
            $valor = post('valor') === '' ? null : (float) str_replace(['.', ','], ['', '.'], post('valor'));
            $d = ['client_id' => $cid, 'tipo' => $tipo, 'descricao' => post('descricao') ?: BILL_TYPES[$tipo],
                'fornecedor' => post_or_null('fornecedor'), 'valor' => $valor, 'vencimento' => $venc, 'recorrencia' => $rec,
                'avisar_email' => isset($_POST['avisar_email']) ? 1 : 0, 'avisar_whatsapp' => isset($_POST['avisar_whatsapp']) ? 1 : 0,
                'notas' => post_or_null('notas')];
            if ($id) { update('billings', $id, $d); flash('Vencimento atualizado.'); }
            else { insert('billings', $d + ['status' => 'pendente', 'criado_em' => now()]); flash('Vencimento cadastrado. Você será avisado com 30, 15 e 7 dias de antecedência 🔔'); }
            audit('bill_save', $d['descricao']);
            return $ret;

        case 'bill_pay':
            require_can('billing.edit');
            $b = row('SELECT * FROM billings WHERE id = ?', [$id]);
            if (!$b) throw new RuntimeException('Vencimento não encontrado.');
            if ($b['recorrencia'] === 'nenhuma') {
                update('billings', $id, ['status' => 'pago', 'pago_em' => now()]);
                flash('Marcado como pago ✅');
            } else {
                $next = add_months($b['vencimento'], $b['recorrencia'] === 'mensal' ? 1 : 12);
                update('billings', $id, ['vencimento' => $next, 'pago_em' => now()]);
                flash('Pago! Próximo vencimento: ' . fmt_date($next) . ' ✅');
            }
            audit('bill_pay', $b['descricao']);
            return $ret;

        case 'bill_reopen':
            require_can('billing.edit');
            update('billings', $id, ['status' => 'pendente', 'pago_em' => null]); flash('Vencimento reaberto.');
            return $ret;

        case 'bill_delete':
            require_can('delete');
            q('DELETE FROM billings WHERE id = ?', [$id]); audit('bill_delete', "#$id"); flash('Vencimento excluído.');
            return $ret;

        /* ---------------- Links (Drive, Canva, Agenda) ---------------- */
        case 'link_save':
            require_can('links.edit');
            $link = post('url');
            if (!preg_match('~^https?://~i', $link)) throw new RuntimeException('Cole um link completo começando com https://');
            $tipo = array_key_exists(post('tipo'), LINK_TYPES) ? post('tipo') : guess_link_type($link);
            $d = ['client_id' => post('client_id') === '' ? null : (int) post('client_id'), 'tipo' => $tipo,
                'titulo' => post('titulo') ?: LINK_TYPES[$tipo], 'url' => $link, 'notas' => post_or_null('notas')];
            if ($id) { update('links', $id, $d); flash('Link atualizado.'); }
            else { insert('links', $d + ['criado_em' => now()]); flash('Link adicionado 📁'); }
            return $ret;

        case 'link_delete':
            require_can('delete');
            q('DELETE FROM links WHERE id = ?', [$id]); flash('Link removido.');
            return $ret;

        /* ---------------- Equipe ---------------- */
        case 'user_save':
            require_can('users');
            $email = strtolower(post('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('nome') === '') throw new RuntimeException('Informe nome e e-mail válidos.');
            $papel = array_key_exists(post('papel'), ROLES) ? post('papel') : 'designer';
            $dup = row('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id]);
            if ($dup) throw new RuntimeException('Já existe um usuário com este e-mail.');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $senha = (string) ($_POST['senha'] ?? '');
            if ($id) {
                $old = row('SELECT * FROM users WHERE id = ?', [$id]);
                $admins = (int) val("SELECT COUNT(*) FROM users WHERE papel = 'admin' AND ativo = 1 AND id <> ?", [$id]);
                if ($old && $admins === 0 && ($papel !== 'admin' || !$ativo)) throw new RuntimeException('Mantenha pelo menos um administrador ativo.');
                $d = ['nome' => post('nome'), 'email' => $email, 'papel' => $papel, 'telefone' => post_or_null('telefone'), 'ativo' => $ativo];
                if ($senha !== '') { if (strlen($senha) < 8) throw new RuntimeException('A senha precisa ter ao menos 8 caracteres.'); $d['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT); }
                update('users', $id, $d); flash('Usuário atualizado.');
            } else {
                if (strlen($senha) < 8) throw new RuntimeException('Defina uma senha com ao menos 8 caracteres.');
                insert('users', ['nome' => post('nome'), 'email' => $email, 'papel' => $papel, 'telefone' => post_or_null('telefone'),
                    'ativo' => $ativo, 'senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'criado_em' => now()]);
                flash('Usuário criado 🎉');
            }
            audit('user_save', $email);
            return $ret;

        case 'user_delete':
            require_can('users');
            if ($id === (int) $u['id']) throw new RuntimeException('Você não pode excluir a si mesmo.');
            $t = row('SELECT papel FROM users WHERE id = ?', [$id]);
            if ($t && $t['papel'] === 'admin' && (int) val("SELECT COUNT(*) FROM users WHERE papel='admin' AND ativo=1 AND id<>?", [$id]) === 0)
                throw new RuntimeException('Mantenha pelo menos um administrador ativo.');
            q('DELETE FROM users WHERE id = ?', [$id]); audit('user_delete', "#$id"); flash('Usuário excluído.');
            return $ret;

        case 'pwd_change':
            $cur = (string) ($_POST['atual'] ?? ''); $new = (string) ($_POST['nova'] ?? '');
            $row = row('SELECT senha_hash FROM users WHERE id = ?', [$u['id']]);
            if (!password_verify($cur, $row['senha_hash'])) throw new RuntimeException('Senha atual incorreta.');
            if (strlen($new) < 8) throw new RuntimeException('A nova senha precisa ter ao menos 8 caracteres.');
            update('users', (int) $u['id'], ['senha_hash' => password_hash($new, PASSWORD_DEFAULT)]); flash('Senha alterada.');
            return $ret;

        /* ---------------- Configurações ---------------- */
        case 'settings_save':
            require_can('settings');
            foreach (['empresa_nome', 'alert_emails', 'mail_from', 'wa_provider', 'wa_phone', 'wa_apikey', 'wa_webhook_url'] as $k) set_setting($k, post($k));
            flash('Configurações salvas.');
            return $ret;

        case 'alert_test':
            require_can('settings');
            $res = [];
            $ok = send_mail(alert_recipients(), 'Teste de alerta — ' . setting('empresa_nome', 'BayApp'),
                mail_template('Teste de e-mail', [['descricao' => 'Alerta de teste', 'cliente' => 'BayApp', 'tipo' => 'outro', 'valor' => null, 'dias' => 7, 'vencimento' => date('Y-m-d', strtotime('+7 day'))]]));
            $res[] = 'E-mail: ' . ($ok ? 'enviado' : 'falhou');
            [$wok, $det] = send_whatsapp('✅ Teste de alerta do ' . setting('empresa_nome', 'BayApp'));
            $res[] = 'WhatsApp: ' . ($wok ? 'enviado' : "não enviado ($det)");
            flash(implode(' · ', $res), $ok || $wok ? 'ok' : 'err');
            return $ret;

        case 'alert_run':
            require_can('settings');
            $r = run_alerts();
            flash("Verificação concluída: {$r['verificados']} vencimento(s) analisados, {$r['alertas']} alerta(s) novo(s).");
            return $ret;
    }
    throw new RuntimeException('Ação desconhecida.');
}
