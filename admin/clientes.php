<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$editarCliente = null;

/* =========================
   BUSCAR PLANOS
========================= */
$stmtPlanos = $conn->query("SELECT id, nome, valor FROM planos ORDER BY nome ASC");
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ADICIONAR CLIENTE
========================= */
if (isset($_POST['adicionar'])) {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $lugar = trim($_POST['lugar'] ?? '');
    $ativo = $_POST['ativo'] ?? 0;
    $plano_id = $_POST['plano_id'] ?? null;

    if ($nome && $email) {

        // Inserir cliente
        $stmt = $conn->prepare("
            INSERT INTO clientes (nome, email, telefone, ativo, lugar)
            VALUES (:nome, :email, :telefone, :ativo, :lugar)
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefone,
            ':ativo' => $ativo,
            ':lugar' => $lugar
        ]);

        $cliente_id = $conn->lastInsertId(); // pega o id gerado automaticamente

        // Inserir pagamento se plano foi selecionado
        if ($plano_id) {
            $valor_pago = null;
            foreach ($planos as $plano) {
                if ($plano['id'] == $plano_id) {
                    $valor_pago = $plano['valor'];
                    break;
                }
            }

            if ($valor_pago !== null) {
                $stmtPg = $conn->prepare("
                    INSERT INTO pagamentos 
                    (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
                    VALUES (:cliente_id, :plano_id, :valor_pago, NOW(), NOW() + INTERVAL '1 month', 'Ativo')
                ");
                $stmtPg->execute([
                    ':cliente_id' => $cliente_id,
                    ':plano_id' => $plano_id,
                    ':valor_pago' => $valor_pago
                ]);
            }
        }

        $mensagem = "Cliente adicionado com sucesso!";
    } else {
        $mensagem = "Preencha os campos obrigatórios.";
    }
}

/* =========================
   EDITAR CLIENTE
========================= */
if (isset($_GET['editar'])) {

    $id = (int) $_GET['editar'];

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $editarCliente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar plano ativo do cliente
    $stmtPg = $conn->prepare("
        SELECT plano_id FROM pagamentos 
        WHERE cliente_id = :cliente_id AND status = 'Ativo' LIMIT 1
    ");
    $stmtPg->execute([':cliente_id' => $id]);
    $pg = $stmtPg->fetch(PDO::FETCH_ASSOC);
    if ($pg) {
        $editarCliente['plano_id'] = $pg['plano_id'];
    }
}

/* =========================
   ATUALIZAR CLIENTE
========================= */
if (isset($_POST['atualizar'])) {

    $id = (int) ($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $lugar = trim($_POST['lugar'] ?? '');
    $ativo = $_POST['ativo'] ?? 0;
    $plano_id = $_POST['plano_id'] ?? null;

    if ($id && $nome && $email) {

        // Atualizar cliente
        $stmt = $conn->prepare("
            UPDATE clientes 
            SET nome = :nome, email = :email, telefone = :telefone, ativo = :ativo, lugar = :lugar
            WHERE id = :id
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefone,
            ':ativo' => $ativo,
            ':lugar' => $lugar,
            ':id' => $id
        ]);

        // Atualizar ou criar pagamento
        if ($plano_id) {
            $valor_pago = null;
            foreach ($planos as $plano) {
                if ($plano['id'] == $plano_id) {
                    $valor_pago = $plano['valor'];
                    break;
                }
            }

            if ($valor_pago !== null) {

                // Verifica se existe pagamento ativo
                $stmtCheck = $conn->prepare("
                    SELECT id FROM pagamentos 
                    WHERE cliente_id = :cliente_id AND status = 'Ativo' LIMIT 1
                ");
                $stmtCheck->execute([':cliente_id' => $id]);
                $pgExist = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($pgExist) {
                    // Atualiza plano existente
                    $stmtUpdate = $conn->prepare("
                        UPDATE pagamentos 
                        SET plano_id = :plano_id, valor_pago = :valor_pago
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':plano_id' => $plano_id,
                        ':valor_pago' => $valor_pago,
                        ':id' => $pgExist['id']
                    ]);
                } else {
                    // Cria novo pagamento ativo
                    $stmtPg = $conn->prepare("
                        INSERT INTO pagamentos 
                        (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
                        VALUES (:cliente_id, :plano_id, :valor_pago, NOW(), NOW() + INTERVAL '1 month', 'Ativo')
                    ");
                    $stmtPg->execute([
                        ':cliente_id' => $id,
                        ':plano_id' => $plano_id,
                        ':valor_pago' => $valor_pago
                    ]);
                }
            }
        }

        $mensagem = "Cliente atualizado com sucesso!";
    }
}

/* =========================
   EXCLUIR CLIENTE
========================= */
if (isset($_GET['excluir'])) {
    $id = (int) $_GET['excluir'];

    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Opcional: deletar pagamentos relacionados
    $stmt = $conn->prepare("DELETE FROM pagamentos WHERE cliente_id = :id");
    $stmt->execute([':id' => $id]);

    header("Location: clientes.php");
    exit;
}

/* =========================
   LISTAR CLIENTES COM PLANOS
========================= */
$stmt = $conn->query("
    SELECT 
        c.id, c.nome, c.email, c.telefone, c.ativo, c.lugar,
        p.nome AS plano_nome, p.valor AS plano_valor
    FROM clientes c
    LEFT JOIN pagamentos pg ON c.id = pg.cliente_id AND pg.status = 'Ativo'
    LEFT JOIN planos p ON pg.plano_id = p.id
    ORDER BY c.nome ASC
");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="bg-white shadow p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold">Cadastro de Clientes</h1>
    <div class="space-x-4">
        <a href="index.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Home</a>
        <a href="planos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Planos</a>
        <a href="pagamentos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Pagamentos</a>
        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
    </div>
</div>

<div class="p-8">

    <?php if ($mensagem): ?>
        <div class="bg-green-100 text-green-700 p-3 mb-4 rounded">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-6 rounded shadow mb-6">
        <div class="grid grid-cols-3 gap-4">
            <input type="hidden" name="id" value="<?= $editarCliente['id'] ?? '' ?>">

            <input type="text" name="nome" placeholder="Nome" required
                   class="border p-2 rounded"
                   value="<?= htmlspecialchars($editarCliente['nome'] ?? '') ?>">

            <input type="email" name="email" placeholder="Email" required
                   class="border p-2 rounded"
                   value="<?= htmlspecialchars($editarCliente['email'] ?? '') ?>">

            <input type="text" name="telefone" placeholder="Telefone"
                   class="border p-2 rounded"
                   value="<?= htmlspecialchars($editarCliente['telefone'] ?? '') ?>">

            <input type="text" name="lugar" placeholder="Lugar"
                   class="border p-2 rounded"
                   value="<?= htmlspecialchars($editarCliente['lugar'] ?? '') ?>">

            <select name="ativo" class="border p-2 rounded">
                <option value="1" <?= (($editarCliente['ativo'] ?? 1) == 1) ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= (($editarCliente['ativo'] ?? 1) == 0) ? 'selected' : '' ?>>Inativo</option>
            </select>

            <select name="plano_id" class="border p-2 rounded">
                <option value="">-- Selecionar Plano --</option>
                <?php foreach ($planos as $plano): ?>
                    <option value="<?= $plano['id'] ?>"
                        <?= (isset($editarCliente['plano_id']) && $editarCliente['plano_id'] == $plano['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($plano['nome'] . ' (R$ ' . number_format($plano['valor'], 2, ',', '.') . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($editarCliente): ?>
            <button type="submit" name="atualizar" class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded">Atualizar Cliente</button>
            <a href="clientes.php" class="mt-4 inline-block bg-gray-500 text-white px-4 py-2 rounded">Cancelar</a>
        <?php else: ?>
            <button type="submit" name="adicionar" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Adicionar Cliente</button>
        <?php endif; ?>
    </form>

    <div class="bg-white p-6 rounded shadow">
        <table class="w-full border">
            <thead>
            <tr class="bg-gray-200">
                <th class="p-2">ID</th>
                <th class="p-2">Nome</th>
                <th class="p-2">Email</th>
                <th class="p-2">Telefone</th>
                <th class="p-2">Lugar</th>
                <th class="p-2">Status</th>
                <th class="p-2">Plano</th>
                <th class="p-2">Valor</th>
                <th class="p-2">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $cliente): ?>
                <tr class="border-t">
                    <td class="p-2"><?= $cliente['id'] ?></td>
                    <td class="p-2"><?= htmlspecialchars($cliente['nome']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($cliente['email']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($cliente['telefone']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($cliente['lugar']) ?></td>
                    <td class="p-2">
                        <?= $cliente['ativo']
                            ? '<span class="px-2 py-1 text-sm font-medium text-green-700 bg-green-100 rounded-full">Ativo</span>'
                            : '<span class="px-2 py-1 text-sm font-medium text-red-700 bg-red-100 rounded-full">Inativo</span>' ?>
                    </td>
                    <td class="p-2"><?= htmlspecialchars($cliente['plano_nome'] ?? 'Sem plano') ?></td>
                    <td class="p-2"><?= isset($cliente['plano_valor']) ? 'R$ ' . number_format($cliente['plano_valor'], 2, ',', '.') : '-' ?></td>
                    <td class="p-2 space-x-2">
                        <a href="?editar=<?= $cliente['id'] ?>" class="text-yellow-600">Editar</a>
                        <a href="?excluir=<?= $cliente['id'] ?>" class="text-red-600" onclick="return confirm('Excluir cliente?')">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>