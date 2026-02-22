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
$cliente_id = (int) ($_POST['cliente_id'] ?? 0);

if (!$cliente_id) {
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

    // 🔥 REGRA INTELIGENTE DE RENOVAÇÃO
    if ($vencimentoAtual > $hoje) {
        // Ainda não venceu → soma 1 mês em cima do vencimento atual
        $novaDataVencimento = clone $vencimentoAtual;
        $novaDataVencimento->modify('+1 month');
    } else {
        // Já venceu → soma 1 mês a partir de hoje
        $novaDataVencimento = new DateTime('+1 month');
    }

    // 💾 Atualiza pagamento
    $stmtUpdate = $conn->prepare("
        UPDATE pagamentos
        SET 
            data_compra = NOW(),
            data_vencimento = :novaData,
            status = 'Ativo'
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':novaData' => $novaDataVencimento->format('Y-m-d'),
        ':id' => $pg['id']
    ]);

    // 🟢 Garante cliente ativo
    $stmtCliente = $conn->prepare("
        UPDATE clientes
        SET ativo = true
        WHERE id = :cliente_id
    ");
    $stmtCliente->execute([':cliente_id' => $cliente_id]);

    // 🎨 Define cor da célula de vencimento
    $diff = (int)$hoje->diff($novaDataVencimento)->format("%r%a");

    $classVenc = '';
    if ($diff <= 1) {
        $classVenc = 'bg-red-500 text-white';
    } elseif ($diff <= 5) {
        $classVenc = 'bg-purple-500 text-white';
    } elseif ($diff <= 10) {
        $classVenc = 'bg-yellow-400';
    }

    echo json_encode([
        'success' => true,
        'msg' => 'Pagamento registrado com sucesso!',
        'vencimento' => $novaDataVencimento->format('d/m/Y'),
        'classVenc' => $classVenc
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'msg' => 'Erro: ' . $e->getMessage()
    ]);
}