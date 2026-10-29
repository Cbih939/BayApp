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
            $senha = (string) ($_POST['senha'] ?? '');
            if ($senha !== '' && strlen($senha) < 8) throw new RuntimeException('A senha precisa ter ao menos 8 caracteres.');
            $pemail = strtolower((string) ($d['email'] ?? ''));
            $pemail = filter_var($pemail, FILTER_VALIDATE_EMAIL) ? $pemail : '';
            if ($senha !== '' && $pemail === '') throw new RuntimeException('Informe um e-mail válido para criar o acesso ao portal.');
            $novo = !$id;
            if ($id) { update('clients', $id, $d); flash('Cliente atualizado.'); }
            else {
                $id = insert('clients', $d + ['criado_em' => now()]);
                flash('Cliente cadastrado! Agora adicione senhas, vencimentos e links.');
                $ret = url('cliente', ['id' => $id]);
            }
            if ($pemail !== '' && ($novo || $senha !== '')) {
                $gerada = false;
                if ($senha === '') {
                    // Cadastro novo sem senha informada: gera senha forte (sem caracteres ambíguos).
                    $alfabeto = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                    for ($i = 0; $i < 12; $i++) $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                    $gerada = true;
                }
                $pu = row('SELECT id FROM client_users WHERE client_id = ? AND email = ?', [$id, $pemail]);
                if ($pu) {
                    update('client_users', (int) $pu['id'], ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'ativo' => 1]);
                    flash('Senha do portal redefinida.');
                } elseif (row('SELECT id FROM client_users WHERE email = ?', [$pemail])) {
                    flash('Já existe um acesso ao portal com o e-mail ' . $pemail . ' em outro cliente. Crie outro na aba Portal.', 'warn');
                    $gerada = false;
                } else {
                    insert('client_users', ['client_id' => $id, 'nome' => $nome, 'email' => $pemail, 'ativo' => 1,
                        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'criado_em' => now()]);
                    flash('Acesso ao portal criado.');
                }
                if ($gerada) flash('Login do portal: ' . $pemail . ' · Senha: ' . $senha . ' (anote agora, ela não será exibida novamente).', 'keep');
            }
            audit('client_save', $nome);
            return $ret;

        case 'client_delete':
            require_can('delete');
            files_unlink(rows('SELECT arquivo FROM files WHERE client_id = ?', [$id]));
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
            require_can('creds.delete');
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
            $model = array_key_exists(post('modelo'), BILL_MODELS) ? post('modelo') : 'fixo';
            $posted = post('recorrencia');   // só vem preenchido ao editar registros antigos (ex.: trimestral)
            $rec = match ($model) { 'variavel' => 'mensal', 'unico' => 'nenhuma', default => isset(REC_MONTHS[$posted]) ? $posted : 'anual' };
            $valor = parse_money(post('valor'));
            $d = ['client_id' => $cid, 'tipo' => $tipo, 'descricao' => post('descricao') ?: BILL_TYPES[$tipo],
                'fornecedor' => post_or_null('fornecedor'), 'valor' => $valor, 'vencimento' => $venc, 'recorrencia' => $rec, 'modelo' => $model,
                'avisar_email' => isset($_POST['avisar_email']) ? 1 : 0, 'avisar_whatsapp' => isset($_POST['avisar_whatsapp']) ? 1 : 0,
                'avisar_cliente' => isset($_POST['avisar_cliente']) ? 1 : 0, 'notas' => post_or_null('notas')];
            if ($id) { update('billings', $id, $d); flash('Serviço atualizado.'); }
            else { insert('billings', $d + ['status' => 'pendente', 'criado_em' => now()]); flash('Serviço cadastrado. Você será avisado com 30, 15 e 7 dias de antecedência 🔔'); }
            audit('bill_save', $d['descricao']);
            return $ret;

        case 'bill_pay':
            require_can('billing.edit');
            $b = row('SELECT * FROM billings WHERE id = ?', [$id]);
            if (!$b) throw new RuntimeException('Serviço não encontrado.');
            if ($b['status'] === 'pago') throw new RuntimeException('Este serviço já está pago.');
            record_payment($b, (int) $u['id']);
            if (!isset(REC_MONTHS[$b['recorrencia']])) {
                update('billings', $id, ['status' => 'pago', 'pago_em' => now()]);
                flash('Marcado como pago ✅');
            } else {
                $next = add_months($b['vencimento'], REC_MONTHS[$b['recorrencia']]);
                $upd = ['vencimento' => $next, 'pago_em' => now()];
                if (bill_model($b) === 'variavel') $upd['valor'] = null;   // o valor muda a cada mês: informar de novo
                update('billings', $id, $upd);
                flash('Pago! Próximo vencimento: ' . fmt_date($next) . (array_key_exists('valor', $upd) ? ' — informe o valor do próximo mês ✅' : ' ✅'));
            }
            audit('bill_pay', $b['descricao']);
            return $ret;

        case 'bill_reopen':
            require_can('billing.edit');
            $last = val('SELECT MAX(id) FROM payments WHERE billing_id = ?', [$id]);
            if ($last) q('DELETE FROM payments WHERE id = ?', [$last]);
            update('billings', $id, ['status' => 'pendente', 'pago_em' => null]); flash('Serviço reaberto.');
            return $ret;

        case 'bill_delete':
            require_can('delete');
            files_unlink(rows('SELECT arquivo FROM files WHERE billing_id = ?', [$id]));
            q('DELETE FROM billings WHERE id = ?', [$id]); audit('bill_delete', "#$id"); flash('Serviço excluído.');
            return $ret;

        /* ---------------- Arquivos do pagamento (boleto, quitação…) ---------------- */
        case 'file_upload':
            require_can('billing.edit');
            $bid = (int) post('billing_id'); $comp = post('competencia');
            $b = row('SELECT id, client_id FROM billings WHERE id = ?', [$bid]);
            if (!$b) throw new RuntimeException('Serviço não encontrado.');
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $comp)) throw new RuntimeException('Competência inválida.');
            $kind = array_key_exists(post('kind'), FILE_KINDS) ? post('kind') : 'boleto';
            [$n, $errs] = save_uploads(['billing_id' => $bid, 'client_id' => (int) $b['client_id'], 'competencia' => $comp], $kind, (int) $u['id']);
            audit('file_upload', "$n arquivo(s) · serviço #$bid · $comp");
            if ($n) flash("$n arquivo(s) enviado(s) ✅" . ($errs ? ' — recusado(s): ' . implode('; ', $errs) : ''));
            else flash(implode('; ', $errs) ?: 'Nenhum arquivo foi enviado.', 'err');
            return $ret;

        case 'file_delete':
            require_can('billing.edit');
            $f = row('SELECT id, arquivo, nome FROM files WHERE id = ?', [$id]);
            if (!$f) throw new RuntimeException('Arquivo não encontrado.');
            files_unlink([$f]); q('DELETE FROM files WHERE id = ?', [$id]); audit('file_delete', $f['nome']); flash('Arquivo removido.');
            return $ret;

        /* ---------------- Vencimentos internos da Bay Groups ---------------- */
        case 'ibill_save':
            require_can('billing.edit');
            $venc = post('vencimento');
            if (!valid_date($venc)) throw new RuntimeException('Informe a data de vencimento.');
            $desc = post('descricao');
            if ($desc === '') throw new RuntimeException('Informe a descrição.');
            $d = ['categoria' => array_key_exists(post('categoria'), INTERNAL_CATS) ? post('categoria') : 'outro', 'descricao' => mb_substr($desc, 0, 200),
                'fornecedor' => post_or_null('fornecedor'), 'valor' => parse_money(post('valor')), 'vencimento' => $venc,
                'recorrencia' => array_key_exists(post('recorrencia'), REC_LABELS) ? post('recorrencia') : 'nenhuma',
                'avisar_email' => isset($_POST['avisar_email']) ? 1 : 0, 'avisar_whatsapp' => isset($_POST['avisar_whatsapp']) ? 1 : 0, 'notas' => post_or_null('notas')];
            if ($id) { update('internal_bills', $id, $d); flash('Vencimento interno atualizado.'); }
            else { insert('internal_bills', $d + ['status' => 'pendente', 'criado_em' => now()]); flash('Vencimento interno cadastrado. Você será avisado com 30, 15 e 7 dias de antecedência 🔔'); }
            audit('ibill_save', $desc);
            return $ret;

        case 'ibill_pay':
            require_can('billing.edit');
            $b = row('SELECT * FROM internal_bills WHERE id = ?', [$id]);
            if (!$b) throw new RuntimeException('Vencimento não encontrado.');
            if ($b['status'] === 'pago') throw new RuntimeException('Este vencimento já está pago.');
            record_internal_payment($b, (int) $u['id']);
            if (!isset(REC_MONTHS[$b['recorrencia']])) { update('internal_bills', $id, ['status' => 'pago', 'pago_em' => now()]); flash('Marcado como pago ✅'); }
            else {
                $next = add_months($b['vencimento'], REC_MONTHS[$b['recorrencia']]);
                update('internal_bills', $id, ['vencimento' => $next, 'pago_em' => now()]);
                flash('Pago! Próximo vencimento: ' . fmt_date($next) . ' ✅');
            }
            audit('ibill_pay', $b['descricao']);
            return $ret;

        case 'ibill_reopen':
            require_can('billing.edit');
            $last = val('SELECT MAX(id) FROM internal_payments WHERE bill_id = ?', [$id]);
            if ($last) q('DELETE FROM internal_payments WHERE id = ?', [$last]);
            update('internal_bills', $id, ['status' => 'pendente', 'pago_em' => null]); flash('Vencimento reaberto.');
            return $ret;

        case 'ibill_delete':
            require_can('delete');
            q('DELETE FROM internal_bills WHERE id = ?', [$id]); audit('ibill_delete', "#$id"); flash('Vencimento interno excluído.');
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
            require_can('links.delete');
            q('DELETE FROM links WHERE id = ?', [$id]); flash('Link removido.');
            return $ret;

        /* ---------------- Tarefas ---------------- */
        case 'task_save':
            $titulo = post('titulo');
            if ($titulo === '') throw new RuntimeException('Informe o título da tarefa.');
            $cid = post('client_id') === '' ? null : (int) post('client_id');
            if ($cid !== null && !row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Cliente não encontrado.');
            $aid = post('assignee_id') === '' ? null : (int) post('assignee_id');
            if ($aid !== null && !row('SELECT id FROM users WHERE id = ? AND ativo = 1', [$aid])) throw new RuntimeException('Responsável inválido.');
            $prazo = post('prazo');
            if ($prazo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) throw new RuntimeException('Data de prazo inválida.');
            $status = array_key_exists(post('status'), TASK_STATUS) ? post('status') : 'a_fazer';
            $d = ['titulo' => mb_substr($titulo, 0, 200), 'descricao' => post_or_null('descricao'), 'link' => clean_link(post('link')), 'client_id' => $cid, 'assignee_id' => $aid,
                'prioridade' => array_key_exists(post('prioridade'), TASK_PRIO) ? post('prioridade') : 'media',
                'status' => $status, 'prazo' => $prazo === '' ? null : $prazo, 'atualizado_em' => now()];
            $old = $id ? row('SELECT status, assignee_id FROM tasks WHERE id = ?', [$id]) : null;
            if ($id && !$old) throw new RuntimeException('Tarefa não encontrada.');
            $d['concluida_em'] = $status === 'feito' ? (($old && $old['status'] === 'feito') ? val('SELECT concluida_em FROM tasks WHERE id = ?', [$id]) : now()) : null;
            if ($id) { update('tasks', $id, $d); flash('Tarefa atualizada.'); }
            else { $id = insert('tasks', $d + ['criado_por' => $u['id'], 'criado_em' => now()]); flash('Tarefa criada ✅'); }
            if (isset($_POST['item_texto']) && is_array($_POST['item_texto'])) task_save_items($id, $_POST['item_texto'], (array) ($_POST['item_feito'] ?? []));
            if ($aid && $aid !== (int) $u['id'] && (!$old || (int) $old['assignee_id'] !== $aid)) {
                $r = row('SELECT nome, email FROM users WHERE id = ?', [$aid]);
                if ($r && filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                    send_mail([$r['email']], 'Nova tarefa para você — ' . setting('empresa_nome', 'BayApp'),
                        mail_layout('Olá, ' . explode(' ', $r['nome'])[0] . '!', '<p style="margin:0 0 10px">' . e($u['nome']) . ' atribuiu uma tarefa a você:</p><p style="margin:0;font-size:16px"><b>'
                        . e($d['titulo']) . '</b>' . ($d['prazo'] ? '<br><span style="color:#767996;font-size:13px">Prazo: ' . fmt_date($d['prazo']) . '</span>' : '') . '</p>'
                        . (app_url() !== '' ? mail_button(app_url() . '/index.php?p=tarefas', 'Abrir tarefas') : '')));
                }
            }
            audit('task_save', $d['titulo']);
            return $ret;

        case 'task_move':
            task_move_to($id, post('status'));
            return $ret;

        case 'task_delete':
            require_can('delete');
            q('DELETE FROM tasks WHERE id = ?', [$id]); audit('task_delete', "#$id"); flash('Tarefa excluída.');
            return $ret;

        /* ---------------- Pacotes e contratos ---------------- */
        case 'plan_save':
            require_can('clients.edit');
            $nome = post('nome');
            if ($nome === '') throw new RuntimeException('Informe o nome do pacote.');
            $d = ['nome' => mb_substr($nome, 0, 120), 'descricao' => post_or_null('descricao'), 'valor' => parse_money(post('valor')), 'ativo' => isset($_POST['ativo']) ? 1 : 0];
            if ($id) { update('plans', $id, $d); flash('Pacote atualizado.'); }
            else { $id = insert('plans', $d + ['criado_em' => now()]); flash('Pacote criado 📦'); }
            q('DELETE FROM plan_items WHERE plan_id = ?', [$id]);
            foreach ((array) ($_POST['item_tipo'] ?? []) as $i => $tipo) {
                $qtd = (int) (((array) ($_POST['item_qtd'] ?? []))[$i] ?? 0);
                if (!isset(DELIVERY_TYPES[$tipo]) || $qtd < 1) continue;
                $desc = trim((string) (((array) ($_POST['item_desc'] ?? []))[$i] ?? ''));
                insert('plan_items', ['plan_id' => $id, 'tipo' => $tipo, 'quantidade' => min($qtd, 999), 'descricao' => $desc === '' ? null : mb_substr($desc, 0, 160)]);
            }
            audit('plan_save', $nome);
            return $ret;

        case 'plan_delete':
            require_can('delete');
            q('DELETE FROM plans WHERE id = ?', [$id]); audit('plan_delete', "#$id"); flash('Pacote excluído (contratos ligados ficam sem pacote).');
            return $ret;

        case 'contract_save':
            require_can('clients.edit');
            $cid = (int) post('client_id');
            if (!row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Cliente não encontrado.');
            $ini = post('inicio'); $fim = post('fim');
            if (!valid_date($ini)) throw new RuntimeException('Informe a data de início do contrato.');
            if ($fim !== '' && !valid_date($fim)) throw new RuntimeException('Data de término inválida.');
            if ($fim !== '' && $fim < $ini) throw new RuntimeException('O término não pode ser antes do início.');
            $pid = post('plan_id') === '' ? null : (int) post('plan_id');
            if ($pid !== null && !row('SELECT id FROM plans WHERE id = ?', [$pid])) throw new RuntimeException('Pacote não encontrado.');
            $d = ['client_id' => $cid, 'plan_id' => $pid, 'inicio' => $ini, 'fim' => $fim === '' ? null : $fim, 'valor' => parse_money(post('valor')),
                'periodicidade' => array_key_exists(post('periodicidade'), REC_LABELS) ? post('periodicidade') : 'mensal',
                'url' => clean_link(post('url')), 'notas' => post_or_null('notas'), 'status' => post('status') === 'encerrado' ? 'encerrado' : 'ativo'];
            if ($id) { update('contracts', $id, $d); flash('Contrato atualizado.'); }
            else {
                if ($d['status'] === 'ativo') q("UPDATE contracts SET status = 'encerrado' WHERE client_id = ? AND status = 'ativo'", [$cid]);
                insert('contracts', $d + ['criado_em' => now()]); flash('Contrato cadastrado 📄');
            }
            audit('contract_save', "cliente #$cid");
            return $ret;

        case 'contract_delete':
            require_can('delete');
            q('DELETE FROM contracts WHERE id = ?', [$id]); audit('contract_delete', "#$id"); flash('Contrato excluído.');
            return $ret;

        /* ---------------- Calendário de postagem ---------------- */
        case 'post_save':
            require_can('posts.edit');
            $cid = (int) post('client_id');
            if (!row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Escolha o cliente.');
            $dia = post('dia'); $hora = post('hora');
            if (!valid_date($dia)) throw new RuntimeException('Informe a data da postagem.');
            if ($hora !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new RuntimeException('Horário inválido.');
            $status = array_key_exists(post('status'), POST_STATUS) ? post('status') : 'rascunho';
            $old = $id ? row('SELECT status, publicado_em FROM posts WHERE id = ?', [$id]) : null;
            if ($id && !$old) throw new RuntimeException('Post não encontrado.');
            $d = ['client_id' => $cid, 'dia' => $dia, 'hora' => $hora === '' ? null : $hora,
                'canal' => array_key_exists(post('canal'), POST_CHANNELS) ? post('canal') : 'outro',
                'formato' => array_key_exists(post('formato'), POST_FORMATS) ? post('formato') : 'post',
                'titulo' => post_or_null('titulo') === null ? null : mb_substr(post('titulo'), 0, 200), 'legenda' => post_or_null('legenda'),
                'midia_url' => clean_link(post('midia_url')), 'status' => $status, 'atualizado_em' => now(),
                'publicado_em' => $status === 'concluido' ? (($old && $old['publicado_em']) ?: now()) : null];
            $img = save_post_image();
            $prev = $id ? row('SELECT imagem FROM posts WHERE id = ?', [$id]) : null;
            if ($img) $d += ['imagem' => $img[0], 'imagem_nome' => $img[1]];
            elseif ($prev && $prev['imagem'] && isset($_POST['remover_imagem'])) $d += ['imagem' => null, 'imagem_nome' => null];
            if ($id) { update('posts', $id, $d); flash('Post atualizado.'); }
            else { insert('posts', $d + ['criado_por' => $u['id'], 'criado_em' => now()]); flash('Post agendado no calendário 📅'); }
            if ($prev && $prev['imagem'] && array_key_exists('imagem', $d)) files_unlink([['arquivo' => $prev['imagem']]]);
            audit('post_save', $dia);
            return $ret;

        case 'post_status':
            require_can('posts.edit');
            $st = post('status');
            if (!isset(POST_STATUS[$st])) throw new RuntimeException('Status inválido.');
            if (!row('SELECT id FROM posts WHERE id = ?', [$id])) throw new RuntimeException('Post não encontrado.');
            update('posts', $id, ['status' => $st, 'publicado_em' => $st === 'concluido' ? now() : null, 'atualizado_em' => now()]);
            flash('Post marcado como ' . mb_strtolower(POST_STATUS[$st]) . '.');
            return $ret;

        case 'post_delete':
            require_can('delete');
            files_unlink(rows('SELECT imagem AS arquivo FROM posts WHERE id = ? AND imagem IS NOT NULL', [$id]));
            q('DELETE FROM posts WHERE id = ?', [$id]); audit('post_delete', "#$id"); flash('Post excluído.');
            return $ret;

        /* ---------------- Portal do cliente ---------------- */
        case 'portal_user_save':
            require_can('clients.edit');
            $cid = (int) post('client_id');
            if (!row('SELECT id FROM clients WHERE id = ?', [$cid])) throw new RuntimeException('Cliente não encontrado.');
            $email = strtolower(post('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('nome') === '') throw new RuntimeException('Informe nome e e-mail válidos.');
            if (row('SELECT id FROM client_users WHERE email = ? AND id <> ?', [$email, $id])) throw new RuntimeException('Já existe um acesso ao portal com este e-mail.');
            $senha = (string) ($_POST['senha'] ?? '');
            $d = ['client_id' => $cid, 'nome' => post('nome'), 'email' => $email, 'ativo' => isset($_POST['ativo']) ? 1 : 0];
            if ($senha !== '' && strlen($senha) < 8) throw new RuntimeException('A senha precisa ter ao menos 8 caracteres.');
            if ($senha !== '') $d['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
            if ($id) { update('client_users', $id, $d); flash('Acesso ao portal atualizado.'); }
            else {
                if ($senha === '') throw new RuntimeException('Defina uma senha com ao menos 8 caracteres.');
                insert('client_users', $d + ['criado_em' => now()]); flash('Acesso ao portal criado. Envie o link e a senha ao cliente 🎉');
            }
            audit('portal_user_save', $email);
            return $ret;

        case 'portal_user_delete':
            require_can('delete');
            q('DELETE FROM client_users WHERE id = ?', [$id]); audit('portal_user_delete', "#$id"); flash('Acesso ao portal removido.');
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

        case 'user_toggle':
            require_can('users');
            if ($id === (int) $u['id']) throw new RuntimeException('Você não pode desativar a si mesmo.');
            $t = row('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$t) throw new RuntimeException('Usuário não encontrado.');
            if ($t['ativo'] && $t['papel'] === 'admin' && (int) val("SELECT COUNT(*) FROM users WHERE papel='admin' AND ativo=1 AND id<>?", [$id]) === 0)
                throw new RuntimeException('Mantenha pelo menos um administrador ativo.');
            update('users', $id, ['ativo' => $t['ativo'] ? 0 : 1]);
            audit('user_toggle', $t['email'] . ($t['ativo'] ? ' → inativo' : ' → ativo'));
            flash($t['ativo'] ? 'Usuário desativado. Ele não consegue mais entrar.' : 'Usuário reativado.');
            return $ret;

        case 'user_reset':
            require_can('users');
            $t = row('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$t) throw new RuntimeException('Usuário não encontrado.');
            $nova = gen_password();
            update('users', $id, ['senha_hash' => password_hash($nova, PASSWORD_DEFAULT)]);
            audit('user_reset', $t['email']);
            flash('Nova senha de ' . $t['nome'] . ' (' . $t['email'] . '): ' . $nova . ' (anote agora, ela não será exibida novamente).', 'keep');
            return $ret;

        case 'portal_user_toggle':
            require_can('clients.edit');
            $t = row('SELECT * FROM client_users WHERE id = ?', [$id]);
            if (!$t) throw new RuntimeException('Acesso não encontrado.');
            update('client_users', $id, ['ativo' => $t['ativo'] ? 0 : 1]);
            audit('portal_user_toggle', $t['email'] . ($t['ativo'] ? ' → inativo' : ' → ativo'));
            flash($t['ativo'] ? 'Acesso ao portal desativado.' : 'Acesso ao portal reativado.');
            return $ret;

        case 'portal_user_reset':
            require_can('clients.edit');
            $t = row('SELECT * FROM client_users WHERE id = ?', [$id]);
            if (!$t) throw new RuntimeException('Acesso não encontrado.');
            $nova = gen_password();
            update('client_users', $id, ['senha_hash' => password_hash($nova, PASSWORD_DEFAULT), 'ativo' => 1]);
            audit('portal_user_reset', $t['email']);
            flash('Nova senha do portal de ' . $t['nome'] . ' (' . $t['email'] . '): ' . $nova . ' (anote agora, ela não será exibida novamente).', 'keep');
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
            foreach (['empresa_nome', 'alert_emails', 'mail_from', 'smtp_host', 'smtp_user', 'wa_phone', 'wa_apikey', 'wa_webhook_url',
                'wa_instance', 'wa_client_token', 'wa_meta_template', 'wa_meta_lang'] as $k) set_setting($k, post($k));
            set_setting('smtp_port', (string) ((int) post('smtp_port') ?: ''));
            set_setting('smtp_secure', in_array(post('smtp_secure'), ['ssl', 'tls', 'none'], true) ? post('smtp_secure') : 'ssl');
            if (post('smtp_pass') !== '') set_setting('smtp_pass', encrypt_secret(post('smtp_pass')));
            set_setting('wa_provider', array_key_exists(post('wa_provider'), WA_PROVIDERS) ? post('wa_provider') : 'nenhum');
            foreach (['cli_notify', 'cli_email', 'cli_whatsapp'] as $k) set_setting($k, isset($_POST[$k]) ? '1' : '0');
            set_setting('cli_marcos', implode(',', parse_marcos(post('cli_marcos'))));
            flash('Configurações salvas.');
            return $ret;

        case 'report_whatsapp':
            if (!whatsapp_configured()) throw new RuntimeException('Configure o WhatsApp em Configurações para enviar direto pelo app.');
            [$ok, $det] = send_whatsapp(report_text(report_build(report_filters($_POST))));
            audit('report_whatsapp', $ok ? 'enviado' : $det);
            flash($ok ? 'Relatório enviado ao WhatsApp.' : "Não foi possível enviar ao WhatsApp ($det).", $ok ? 'ok' : 'err');
            return $ret;

        case 'alert_test':
            require_can('settings');
            $res = [];
            $to = post('test_to');
            $ok = $wok = false;
            if ($to === '' || str_contains($to, '@')) {
                $ok = send_mail($to !== '' ? [$to] : alert_recipients(), 'Teste de alerta — ' . setting('empresa_nome', 'BayApp'),
                    mail_template('Teste de e-mail', [['descricao' => 'Alerta de teste', 'cliente' => 'BayApp', 'tipo' => 'outro', 'valor' => null, 'dias' => 7, 'vencimento' => date('Y-m-d', strtotime('+7 day'))]]));
                $res[] = 'E-mail: ' . ($ok ? 'enviado' : 'falhou (' . mail_error() . ')');
            }
            if ($to === '' || !str_contains($to, '@')) {
                [$wok, $det] = send_whatsapp('✅ Teste de alerta do ' . setting('empresa_nome', 'BayApp'), $to !== '' ? $to : null);
                $res[] = 'WhatsApp: ' . ($wok ? 'enviado' : "não enviado ($det)");
            }
            flash(implode(' · ', $res), $ok || $wok ? 'ok' : 'err');
            return $ret;

        case 'alert_run':
            require_can('settings');
            $r = run_alerts();
            $c = run_client_alerts();
            flash("Verificação concluída: {$r['verificados']} vencimento(s) analisados, {$r['alertas']} alerta(s) novo(s) para a equipe."
                . ($c['desligado'] ? '' : " Clientes: {$c['email']} e-mail(s) e {$c['whatsapp']} WhatsApp(s) enviados" . ($c['erros'] ? ", {$c['erros']} com erro (veja o histórico)." : '.')));
            return $ret;
    }
    throw new RuntimeException('Ação desconhecida.');
}
