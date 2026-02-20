<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Não autorizado']);
    exit;
}

$cliente_id = (int) ($_POST['cliente_id'] ?? 0);
if (!$cliente_id) {
    echo json_encode(['success' => false, 'msg' => 'ID inválido']);
    exit;
}

// Verifica se já existe pagamento ativo
$stmt = $conn->prepare("SELECT * FROM pagamentos WHERE cliente_id = :cliente_id AND status = 'Ativo' LIMIT 1");
$stmt->execute([':cliente_id' => $cliente_id]);
$pg = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pg) {
    // Atualiza pagamento existente
    $stmtUpdate = $conn->prepare("
        UPDATE pagamentos
        SET data_compra = NOW(),
            data_vencimento = NOW() + INTERVAL '1 month'
        WHERE id = :id
    ");
    $stmtUpdate->execute([':id' => $pg['id']]);

    $novaDataVencimento = new DateTime('+1 month');

} else {
    // Cria novo pagamento
    // Busca primeiro plano do cliente
    $stmtPlano = $conn->prepare("SELECT id, valor FROM planos ORDER BY id ASC LIMIT 1");
    $stmtPlano->execute();
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

    if (!$plano) {
        echo json_encode(['success' => false, 'msg' => 'Nenhum plano disponível']);
        exit;
    }

    $stmtInsert = $conn->prepare("
        INSERT INTO pagamentos (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
        VALUES (:cliente_id, :plano_id, :valor, NOW(), NOW() + INTERVAL '1 month', 'Ativo')
    ");
    $stmtInsert->execute([
        ':cliente_id' => $cliente_id,
        ':plano_id' => $plano['id'],
        ':valor' => $plano['valor']
    ]);

    $novaDataVencimento = new DateTime('+1 month');
}

// Determina cor da célula de vencimento
$hoje = new DateTime();
$diff = (int)$hoje->diff($novaDataVencimento)->format("%r%a");

$classVenc = '';
if ($diff <= 1) $classVenc = 'bg-red-500 text-white';
elseif ($diff <= 5) $classVenc = 'bg-purple-500 text-white';
elseif ($diff <= 10) $classVenc = 'bg-yellow-400';

echo json_encode([
    'success' => true,
    'msg' => 'Pagamento registrado com sucesso!',
    'vencimento' => $novaDataVencimento->format('d/m/Y'),
    'classVenc' => $classVenc
]);