<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

header('Content-Type: application/json');

// 🔐 Verifica login
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Não autorizado']);
    exit;
}

// 📥 Recebe ID
$cliente_id = isset($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : 0;

if ($cliente_id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'ID inválido']);
    exit;
}

try {

    // 🔎 Busca último pagamento do cliente
    $stmt = $conn->prepare("
        SELECT *
        FROM pagamentos
        WHERE cliente_id = :cliente_id
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':cliente_id' => $cliente_id]);
    $pg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pg) {
        echo json_encode(['success' => false, 'msg' => 'Cliente não possui plano cadastrado']);
        exit;
    }

    $hoje = new DateTime();
    $vencimentoAtual = new DateTime($pg['data_vencimento']);

    // 🔥 REGRA DE RENOVAÇÃO INTELIGENTE
    if ($vencimentoAtual > $hoje) {
        // ainda válido → soma em cima
        $novaDataVencimento = clone $vencimentoAtual;
    } else {
        // vencido → começa hoje
        $novaDataVencimento = clone $hoje;
    }

    $novaDataVencimento->modify('+1 month');

    // 🔎 Busca valor do plano
    $stmtPlano = $conn->prepare("
        SELECT valor 
        FROM planos 
        WHERE id = :plano_id
        LIMIT 1
    ");
    $stmtPlano->execute([':plano_id' => $pg['plano_id']]);
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

    if (!$plano) {
        echo json_encode(['success' => false, 'msg' => 'Plano não encontrado']);
        exit;
    }

    $valor_pago = (float)$plano['valor'];
    $stmtCheck = $conn->prepare("
    SELECT COUNT(*) 
    FROM pagamentos
    WHERE cliente_id = :cliente_id
    AND status = 'Pago'
    AND DATE_TRUNC('month', data_compra) = DATE_TRUNC('month', CURRENT_DATE)
");

    $stmtCheck->execute([':cliente_id' => $cliente_id]);
    $jaPagou = $stmtCheck->fetchColumn();

    if ($jaPagou > 0) {
        echo json_encode([
            'success' => false,
            'msg' => 'Cliente já pagou este mês!'
        ]);
        exit;
    }


    // ✅ INSERE NOVO PAGAMENTO (HISTÓRICO REAL)
    $stmtInsert = $conn->prepare("
        INSERT INTO pagamentos 
        (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
        VALUES 
        (:cliente_id, :plano_id, :valor_pago, NOW(), :data_vencimento, 'Pago')
    ");

    $stmtInsert->execute([
        ':cliente_id' => $cliente_id,
        ':plano_id' => $pg['plano_id'],
        ':valor_pago' => $valor_pago,
        ':data_vencimento' => $novaDataVencimento->format('Y-m-d')
    ]);

    // 🟢 Atualiza cliente para ativo
    $stmtCliente = $conn->prepare("
        UPDATE clientes 
        SET ativo = true 
        WHERE id = :cliente_id
    ");
    $stmtCliente->execute([':cliente_id' => $cliente_id]);

    echo json_encode([
        'success' => true,
        'msg' => 'Pagamento registrado com sucesso!',
        'novo_vencimento' => $novaDataVencimento->format('d/m/Y')
    ]);
} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'msg' => 'Erro no banco: ' . $e->getMessage()
    ]);
} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'msg' => 'Erro geral: ' . $e->getMessage()
    ]);
}
