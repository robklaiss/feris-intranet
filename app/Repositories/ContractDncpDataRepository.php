<?php

declare(strict_types=1);

namespace App\Repositories;

final class ContractDncpDataRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByContractId(int $contractId): ?array
    {
        return $this->fetchOne(
            'SELECT contract_dncp_data.*, client_billing_contacts.name AS billing_contact_name
             FROM contract_dncp_data
             LEFT JOIN client_billing_contacts ON client_billing_contacts.id = contract_dncp_data.billing_contact_id
             WHERE contract_dncp_data.contract_id = :contract_id',
            ['contract_id' => $contractId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(int $contractId, array $data): void
    {
        $params = $this->params($contractId, $data);

        if ($this->findByContractId($contractId)) {
            $this->execute(
                'UPDATE contract_dncp_data SET
                    tender_id = :tender_id,
                    contract_number = :contract_number,
                    customer_purchase_order_number = :customer_purchase_order_number,
                    public_entity = :public_entity,
                    requesting_dependency = :requesting_dependency,
                    procurement_modality = :procurement_modality,
                    procurement_code = :procurement_code,
                    contract_date = :contract_date,
                    valid_from = :valid_from,
                    valid_until = :valid_until,
                    currency = :currency,
                    fiscal_business_name = :fiscal_business_name,
                    fiscal_ruc = :fiscal_ruc,
                    billing_contact_id = :billing_contact_id,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE contract_id = :contract_id',
                $params
            );

            return;
        }

        $this->execute(
            'INSERT INTO contract_dncp_data (
                contract_id, tender_id, contract_number, customer_purchase_order_number, public_entity,
                requesting_dependency, procurement_modality, procurement_code, contract_date, valid_from,
                valid_until, currency, fiscal_business_name, fiscal_ruc, billing_contact_id, notes, updated_at
            ) VALUES (
                :contract_id, :tender_id, :contract_number, :customer_purchase_order_number, :public_entity,
                :requesting_dependency, :procurement_modality, :procurement_code, :contract_date, :valid_from,
                :valid_until, :currency, :fiscal_business_name, :fiscal_ruc, :billing_contact_id, :notes, CURRENT_TIMESTAMP
            )',
            $params
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hasPayload(array $data): bool
    {
        foreach ($data as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
            if (is_numeric($value) && (string) $value !== '0') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function params(int $contractId, array $data): array
    {
        return [
            'contract_id' => $contractId,
            'tender_id' => $this->nullable($data['dncp_tender_id'] ?? $data['tender_id'] ?? null),
            'contract_number' => $this->nullable($data['dncp_contract_number'] ?? $data['contract_number'] ?? null),
            'customer_purchase_order_number' => $this->nullable($data['dncp_customer_purchase_order_number'] ?? $data['customer_purchase_order_number'] ?? null),
            'public_entity' => $this->nullable($data['dncp_public_entity'] ?? $data['public_entity'] ?? null),
            'requesting_dependency' => $this->nullable($data['dncp_requesting_dependency'] ?? $data['requesting_dependency'] ?? null),
            'procurement_modality' => $this->nullable($data['dncp_procurement_modality'] ?? $data['procurement_modality'] ?? null),
            'procurement_code' => $this->nullable($data['dncp_procurement_code'] ?? $data['procurement_code'] ?? null),
            'contract_date' => $this->nullable($data['dncp_contract_date'] ?? $data['contract_date'] ?? null),
            'valid_from' => $this->nullable($data['dncp_valid_from'] ?? $data['valid_from'] ?? null),
            'valid_until' => $this->nullable($data['dncp_valid_until'] ?? $data['valid_until'] ?? null),
            'currency' => $this->nullable($data['dncp_currency'] ?? $data['currency'] ?? null),
            'fiscal_business_name' => $this->nullable($data['dncp_fiscal_business_name'] ?? $data['fiscal_business_name'] ?? null),
            'fiscal_ruc' => $this->nullable($data['dncp_fiscal_ruc'] ?? $data['fiscal_ruc'] ?? null),
            'billing_contact_id' => !empty($data['dncp_billing_contact_id'] ?? $data['billing_contact_id'] ?? null)
                ? (int) ($data['dncp_billing_contact_id'] ?? $data['billing_contact_id'])
                : null,
            'notes' => $this->nullable($data['dncp_notes'] ?? $data['notes'] ?? null),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
