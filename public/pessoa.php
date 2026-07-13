<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$pessoa = buscarPessoa($id);

if (!$pessoa) {
    header('Location: index.php');
    exit;
}

// Ações via POST (adicionar relações, nomes e mídias)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_pai' && !empty($_POST['pai_mae_id'])) {
        adicionarPaiMae($id, (int) $_POST['pai_mae_id']);
    } elseif ($acao === 'add_filho' && !empty($_POST['filho_id'])) {
        adicionarPaiMae((int) $_POST['filho_id'], $id);
    } elseif ($acao === 'add_conjuge' && !empty($_POST['conjuge_id'])) {
        adicionarUniao($id, (int) $_POST['conjuge_id'], $_POST['tipo_uniao'] ?? 'casamento', $_POST['data_uniao'] ?? null);
    } elseif ($acao === 'editar_uniao' && !empty($_POST['uniao_id'])) {
        atualizarUniao(
            (int) $_POST['uniao_id'],
            $_POST['tipo_uniao_editar'] ?? 'casamento',
            $_POST['data_inicio_editar'] ?? null,
            $_POST['data_fim_editar'] ?? null,
            $_POST['status_uniao_editar'] ?? 'ativo'
        );
    } elseif ($acao === 'remove_pai' && !empty($_POST['pai_mae_id'])) {
        removerPaiMae($id, (int) $_POST['pai_mae_id']);
    } elseif ($acao === 'remove_filho' && !empty($_POST['filho_id'])) {
        removerPaiMae((int) $_POST['filho_id'], $id);
    } elseif ($acao === 'remove_uniao' && !empty($_POST['uniao_id'])) {
        removerUniao((int) $_POST['uniao_id']);
    } elseif ($acao === 'add_nome' && !empty($_POST['nome'])) {
        adicionarNomeAdicional($id, trim($_POST['nome']), $_POST['tipo_nome'] ?? 'casamento', !empty($_POST['uniao_id']) ? (int) $_POST['uniao_id'] : null, trim($_POST['observacao_nome'] ?? ''));
    } elseif ($acao === 'remove_nome' && !empty($_POST['nome_id'])) {
        removerNomeAdicional((int) $_POST['nome_id']);
    } elseif ($acao === 'add_midia' && !empty($_FILES['arquivos']['name'][0])) {
        $total = count($_FILES['arquivos']['name']);
        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $nomeOriginal = $_FILES['arquivos']['name'][$i];
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            $tipo = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'foto' : 'documento';
            $pasta = $tipo === 'foto' ? 'uploads/fotos/' : 'uploads/documentos/';
            $nomeArquivo = $pasta . uniqid('midia_') . '.' . $ext;
            move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], __DIR__ . '/' . $nomeArquivo);
            $titulo = $total === 1 ? trim($_POST['titulo'] ?? '') : pathinfo($nomeOriginal, PATHINFO_FILENAME);
            adicionarMidia([$id], $tipo, $nomeArquivo, $titulo);
        }
    } elseif ($acao === 'desvincular_midia' && !empty($_POST['midia_id'])) {
        desvincularMidiaDePessoa((int) $_POST['midia_id'], $id);
    } elseif ($acao === 'vincular_midia_existente' && !empty($_POST['midia_id'])) {
        vincularMidiaAPessoa((int) $_POST['midia_id'], $id);
    } elseif ($acao === 'excluir_pessoa') {
        excluirPessoa($id);
        header('Location: index.php');
        exit;
    }

    header('Location: pessoa.php?id=' . $id);
    exit;
}

$pais = listarPais($id);
$filhos = listarFilhos($id);
$conjuges = listarConjuges($id);
$midias = listarMidias($id);
$midiasDisponiveis = listarMidiasNaoVinculadas($id);
$nomesAdicionais = listarNomesAdicionais($id);
$todasPessoas = listarPessoas();
$idadeAtual = idade($pessoa['data_nascimento'], $pessoa['data_falecimento'], (bool) $pessoa['falecido']);

$rotulosTipoNome = [
    'casamento' => 'Nome de casamento',
    'religioso' => 'Nome religioso',
    'profissional' => 'Nome profissional',
    'outro' => 'Outro',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pessoa['nome_completo']) ?> - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a href="index.php">🌳 Árvore Familiar</a>
    <nav><a href="index.php">Voltar</a></nav>
</header>

<div class="container">
    <div class="card" style="display:flex; gap:20px; flex-wrap:wrap;">
        <img src="<?= htmlspecialchars(urlFotoOuPlaceholder($pessoa['foto_perfil'])) ?>" style="width:160px; height:160px; object-fit:cover; border-radius:10px;">
        <div style="flex:1; min-width:220px;">
            <h1 style="margin:0 0 2px;"><?= htmlspecialchars($pessoa['nome_completo']) ?></h1>
            <p style="color:#999; font-size:0.8em; margin:0 0 10px;">
                nome de nascimento<?= $pessoa['apelido'] ? ' · apelido "' . htmlspecialchars($pessoa['apelido']) . '"' : '' ?>
                <?php if ($pessoa['origem'] === 'gedcom'): ?>
                    · <span title="Importado de um arquivo GEDCOM">📥 importado (GEDCOM #<?= (int) $pessoa['importacao_id'] ?>)</span>
                <?php endif; ?>
            </p>

            <?php if ($pessoa['data_nascimento']): ?>
                <p>🎂 <?= date('d/m/Y', strtotime($pessoa['data_nascimento'])) ?><?= $pessoa['local_nascimento'] ? ' — ' . htmlspecialchars($pessoa['local_nascimento']) : '' ?>
                <?php if ($idadeAtual !== null && !$pessoa['falecido']): ?> (<?= $idadeAtual ?> anos)<?php endif; ?>
                <?php if ($pessoa['local_nascimento_lat']): ?> <a href="https://www.openstreetmap.org/?mlat=<?= $pessoa['local_nascimento_lat'] ?>&mlon=<?= $pessoa['local_nascimento_lng'] ?>&zoom=12" target="_blank" style="font-size:0.8em;">(ver no mapa)</a><?php endif; ?></p>
            <?php endif; ?>

            <?php if ($pessoa['falecido']): ?>
                <p>🕊️ Falecido(a)
                <?php if ($pessoa['data_falecimento']): ?>
                    em <?= date('d/m/Y', strtotime($pessoa['data_falecimento'])) ?>
                <?php else: ?>
                    <span style="color:#999;">(data desconhecida)</span>
                <?php endif; ?>
                <?= $pessoa['local_falecimento'] ? ' — ' . htmlspecialchars($pessoa['local_falecimento']) : '' ?>
                <?php if ($idadeAtual !== null): ?> (aos <?= $idadeAtual ?> anos)<?php endif; ?>
                <?php if ($pessoa['local_falecimento_lat']): ?> <a href="https://www.openstreetmap.org/?mlat=<?= $pessoa['local_falecimento_lat'] ?>&mlon=<?= $pessoa['local_falecimento_lng'] ?>&zoom=12" target="_blank" style="font-size:0.8em;">(ver no mapa)</a><?php endif; ?></p>
            <?php endif; ?>

            <?php if ($pessoa['biografia']): ?><p><?= nl2br(htmlspecialchars($pessoa['biografia'])) ?></p><?php endif; ?>

            <a href="pessoa_editar.php?id=<?= $id ?>" class="btn">Editar dados</a>
            <a href="arvore.php?foco=<?= $id ?>" class="btn btn-secundario">Ver na árvore</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Excluir esta pessoa e todas as suas relações e mídias?');">
                <input type="hidden" name="acao" value="excluir_pessoa">
                <button type="submit" class="btn-perigo">Excluir pessoa</button>
            </form>
        </div>
    </div>

    <!-- Nomes adicionais -->
    <div class="card">
        <h2>Outros nomes</h2>
        <p style="color:#666; font-size:0.9em; margin-top:-8px;">Sobrenomes ou nomes adotados ao longo da vida (ex: nome de casada). O nome de nascimento acima continua sendo o principal.</p>
        <?php if (empty($nomesAdicionais)): ?>
            <p style="color:#999;">Nenhum outro nome registrado.</p>
        <?php else: ?>
            <ul class="relacoes-lista">
                <?php foreach ($nomesAdicionais as $n): ?>
                    <li>
                        <span><strong><?= htmlspecialchars($n['nome']) ?></strong> <span style="color:#777; font-size:0.85em;">(<?= htmlspecialchars($rotulosTipoNome[$n['tipo']] ?? $n['tipo']) ?><?= $n['observacao'] ? ' — ' . htmlspecialchars($n['observacao']) : '' ?>)</span></span>
                        <form method="post" onsubmit="return confirm('Remover este nome?');">
                            <input type="hidden" name="acao" value="remove_nome">
                            <input type="hidden" name="nome_id" value="<?= $n['id'] ?>">
                            <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="acao" value="add_nome">
            <div style="flex:1; min-width:180px;">
                <label>Novo nome</label>
                <input type="text" name="nome" placeholder="Ex: Maria Silva Santos" required>
            </div>
            <div>
                <label>Tipo</label>
                <select name="tipo_nome">
                    <option value="casamento">Nome de casamento</option>
                    <option value="religioso">Nome religioso</option>
                    <option value="profissional">Nome profissional</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <?php if (!empty($conjuges)): ?>
            <div>
                <label>Relacionado à união com</label>
                <select name="uniao_id">
                    <option value="">—</option>
                    <?php foreach ($conjuges as $c): ?>
                        <option value="<?= $c['uniao_id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit">Adicionar</button>
        </form>
    </div>

    <!-- Pais -->
    <div class="card">
        <h2>Pais</h2>
        <ul class="relacoes-lista">
            <?php foreach ($pais as $p): ?>
                <li>
                    <a href="pessoa.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['nome_completo']) ?></a>
                    <form method="post" onsubmit="return confirm('Remover este vínculo?');">
                        <input type="hidden" name="acao" value="remove_pai">
                        <input type="hidden" name="pai_mae_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="acao" value="add_pai">
            <div style="flex:1; min-width:180px;">
                <label>Vincular pai/mãe já cadastrado(a)</label>
                <select name="pai_mae_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Adicionar</button>
        </form>
        <p style="margin-top:10px;">
            <a href="pessoa_editar.php?vincular_a=<?= $id ?>&tipo_vinculo=pai_mae" class="btn btn-secundario">+ Cadastrar novo pai/mãe</a>
        </p>
    </div>

    <!-- Cônjuges -->
    <div class="card">
        <h2>Cônjuges / Uniões</h2>
        <ul class="relacoes-lista">
            <?php foreach ($conjuges as $c): $editId = 'edit-uniao-' . $c['uniao_id']; ?>
                <li style="display:block;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <span>
                            <a href="pessoa.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a>
                            <span style="color:#777; font-size:0.85em;">
                                — <?= htmlspecialchars($c['tipo']) ?><?= $c['data_inicio'] ? ', desde ' . date('d/m/Y', strtotime($c['data_inicio'])) : '' ?><?= $c['data_fim'] ? ' até ' . date('d/m/Y', strtotime($c['data_fim'])) : '' ?>
                                (<?= htmlspecialchars($c['status']) ?>)
                            </span>
                        </span>
                        <span>
                            <button type="button" class="btn-secundario" style="margin:0; padding:4px 10px;" onclick="document.getElementById('<?= $editId ?>').classList.toggle('aberto')">Editar</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Remover esta união?');">
                                <input type="hidden" name="acao" value="remove_uniao">
                                <input type="hidden" name="uniao_id" value="<?= $c['uniao_id'] ?>">
                                <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                            </form>
                        </span>
                    </div>

                    <form method="post" id="<?= $editId ?>" class="form-edicao-uniao" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-top:10px;">
                        <input type="hidden" name="acao" value="editar_uniao">
                        <input type="hidden" name="uniao_id" value="<?= $c['uniao_id'] ?>">
                        <div>
                            <label>Tipo</label>
                            <select name="tipo_uniao_editar">
                                <?php foreach (['casamento' => 'Casamento', 'uniao_estavel' => 'União estável', 'namoro' => 'Namoro', 'outro' => 'Outro'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $c['tipo'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Data de início</label>
                            <input type="date" name="data_inicio_editar" value="<?= htmlspecialchars($c['data_inicio'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Data de fim (se houver)</label>
                            <input type="date" name="data_fim_editar" value="<?= htmlspecialchars($c['data_fim'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status_uniao_editar">
                                <?php foreach (['ativo' => 'Ativo', 'divorciado' => 'Divorciado(a)', 'viuvo' => 'Viúvo(a)', 'encerrado' => 'Encerrado'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $c['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Salvar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="acao" value="add_conjuge">
            <div style="flex:1; min-width:180px;">
                <label>Vincular cônjuge já cadastrado(a)</label>
                <select name="conjuge_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Tipo</label>
                <select name="tipo_uniao">
                    <option value="casamento">Casamento</option>
                    <option value="uniao_estavel">União estável</option>
                    <option value="namoro">Namoro</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div>
                <label>Data de início</label>
                <input type="date" name="data_uniao">
            </div>
            <button type="submit">Adicionar</button>
        </form>
        <p style="margin-top:10px;">
            <a href="pessoa_editar.php?vincular_a=<?= $id ?>&tipo_vinculo=conjuge" class="btn btn-secundario">+ Cadastrar novo cônjuge</a>
        </p>
    </div>

    <!-- Filhos -->
    <div class="card">
        <h2>Filhos</h2>
        <ul class="relacoes-lista">
            <?php foreach ($filhos as $f): ?>
                <li>
                    <a href="pessoa.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['nome_completo']) ?></a>
                    <form method="post" onsubmit="return confirm('Remover este vínculo?');">
                        <input type="hidden" name="acao" value="remove_filho">
                        <input type="hidden" name="filho_id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="acao" value="add_filho">
            <div style="flex:1; min-width:180px;">
                <label>Vincular filho(a) já cadastrado(a)</label>
                <select name="filho_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Adicionar</button>
        </form>
        <p style="margin-top:10px;">
            <a href="pessoa_editar.php?vincular_a=<?= $id ?>&tipo_vinculo=filho" class="btn btn-secundario">+ Cadastrar novo filho(a)</a>
        </p>
    </div>

    <!-- Fotos e documentos -->
    <div class="card">
        <h2>Fotos e documentos</h2>
        <p style="color:#666; font-size:0.9em; margin-top:-8px;">Certidões de nascimento, casamento, batismo, fotos antigas etc. Totalmente opcional. Um mesmo arquivo pode ficar vinculado a mais de uma pessoa (ex: a certidão de casamento vinculada ao marido e à esposa).</p>
        <div class="midias-grid">
            <?php foreach ($midias as $m):
                $outrasPessoas = listarPessoasDaMidia($m['id'], $id);
            ?>
                <div>
                    <?php if ($m['tipo'] === 'foto'): ?>
                        <img src="<?= htmlspecialchars(urlFotoOuPlaceholder($m['caminho_arquivo'])) ?>" alt="">
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($m['caminho_arquivo']) ?>" target="_blank" class="btn" style="width:100%; text-align:center;">📄 <?= htmlspecialchars($m['titulo'] ?: 'Documento') ?></a>
                    <?php endif; ?>
                    <?php if (!empty($outrasPessoas)): ?>
                        <p style="font-size:0.75em; color:#777; margin:4px 0;">
                            Também vinculada a: <?= htmlspecialchars(implode(', ', array_column($outrasPessoas, 'nome_completo'))) ?>
                        </p>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Desvincular este arquivo desta pessoa? (se não estiver vinculado a mais ninguém, será apagado)');" style="margin-top:4px;">
                        <input type="hidden" name="acao" value="desvincular_midia">
                        <input type="hidden" name="midia_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px; width:100%;">Desvincular</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top:16px;">
            <input type="hidden" name="acao" value="add_midia">
            <label>Título (opcional, usado apenas se enviar um único arquivo)</label>
            <input type="text" name="titulo">
            <label>Arquivos (fotos ou documentos — pode selecionar vários de uma vez)</label>
            <input type="file" name="arquivos[]" multiple>
            <button type="submit">Enviar</button>
        </form>

        <?php if (!empty($midiasDisponiveis)): ?>
            <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-top:20px; border-top:1px solid #eee; padding-top:16px;">
                <input type="hidden" name="acao" value="vincular_midia_existente">
                <div style="flex:1; min-width:220px;">
                    <label>Ou vincular um arquivo já cadastrado no sistema</label>
                    <select name="midia_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($midiasDisponiveis as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= $m['tipo'] === 'foto' ? '🖼️' : '📄' ?> <?= htmlspecialchars($m['titulo'] ?: 'sem título') ?> (vinculada a: <?= htmlspecialchars($m['vinculada_a']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Vincular</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-edicao-uniao { display: none !important; }
    .form-edicao-uniao.aberto { display: flex !important; }
</style>
</body>
</html>
