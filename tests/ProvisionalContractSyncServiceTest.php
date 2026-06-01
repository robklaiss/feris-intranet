<?php

declare(strict_types=1);

use App\Services\ProvisionalContractSyncService;
use App\Support\Database;

$db = Database::connection();
$db->beginTransaction();

try {
    $contract = $db->query('SELECT * FROM contracts WHERE id = 1')->fetch();

    $db->prepare(
        'UPDATE purchase_orders
         SET is_provisional = 1,
             linked_contract_snapshot = :snapshot,
             live_sync_fields = :live_sync_fields,
             identifier_number = :identifier_number,
             contract_type = :contract_type,
             tax_id = :tax_id
         WHERE id = 1'
    )->execute([
        'snapshot' => json_encode([
            'client_id' => $contract['client_id'],
            'identifier_number' => $contract['reference_number'],
            'contract_type' => $contract['contract_type'],
            'tax_id' => $contract['tax_id'],
        ], JSON_UNESCAPED_UNICODE),
        'live_sync_fields' => json_encode(['identifier_number', 'contract_type', 'tax_id'], JSON_UNESCAPED_UNICODE),
        'identifier_number' => $contract['reference_number'],
        'contract_type' => $contract['contract_type'],
        'tax_id' => $contract['tax_id'],
    ]);

    $updatedContract = $contract;
    $updatedContract['reference_number'] = 'ID-SYNC-001';
    $updatedContract['contract_type'] = 'Contrato provisorio sincronizado';
    $updatedContract['tax_id'] = '80000000-1';
    $updatedContract['is_provisional'] = 1;

    (new ProvisionalContractSyncService())->syncOrdersForContract(1, $contract, $updatedContract);

    $order = $db->query('SELECT * FROM purchase_orders WHERE id = 1')->fetch();

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $assert($order['identifier_number'] === 'ID-SYNC-001', 'La orden debe sincronizar el Nro. ID del contrato provisorio.');
    $assert($order['contract_type'] === 'Contrato provisorio sincronizado', 'La orden debe sincronizar la modalidad del contrato provisorio.');
    $assert($order['tax_id'] === '80000000-1', 'La orden debe sincronizar el RUC del contrato provisorio.');
} finally {
    $db->rollBack();
}

return true;
