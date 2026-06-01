ALTER TABLE purchase_orders ADD COLUMN contract_type TEXT;
ALTER TABLE purchase_orders ADD COLUMN tax_id TEXT;

ALTER TABLE delivery_notes ADD COLUMN delivery_address TEXT;
ALTER TABLE delivery_notes ADD COLUMN receiver_name TEXT;
ALTER TABLE delivery_notes ADD COLUMN receiver_signature TEXT;
ALTER TABLE delivery_notes ADD COLUMN issuer_name TEXT;
ALTER TABLE delivery_notes ADD COLUMN issuer_signature TEXT;

ALTER TABLE remissions ADD COLUMN client_id INTEGER;
ALTER TABLE remissions ADD COLUMN contract_id INTEGER;
ALTER TABLE remissions ADD COLUMN reference_number TEXT;
ALTER TABLE remissions ADD COLUMN contract_type TEXT;
ALTER TABLE remissions ADD COLUMN tax_id TEXT;
ALTER TABLE remissions ADD COLUMN origin_address TEXT;
ALTER TABLE remissions ADD COLUMN destination_address TEXT;
ALTER TABLE remissions ADD COLUMN transfer_start_date TEXT;
ALTER TABLE remissions ADD COLUMN transfer_end_date TEXT;
ALTER TABLE remissions ADD COLUMN vehicle_brand TEXT;
ALTER TABLE remissions ADD COLUMN vehicle_plate TEXT;
ALTER TABLE remissions ADD COLUMN carrier_name TEXT;
ALTER TABLE remissions ADD COLUMN carrier_tax_id TEXT;
ALTER TABLE remissions ADD COLUMN driver_name TEXT;
ALTER TABLE remissions ADD COLUMN driver_document TEXT;

ALTER TABLE invoices ADD COLUMN client_id INTEGER;
ALTER TABLE invoices ADD COLUMN contract_id INTEGER;
ALTER TABLE invoices ADD COLUMN reference_number TEXT;
ALTER TABLE invoices ADD COLUMN contract_type TEXT;
ALTER TABLE invoices ADD COLUMN tax_id TEXT;
ALTER TABLE invoices ADD COLUMN billing_address TEXT;
ALTER TABLE invoices ADD COLUMN sale_condition TEXT NOT NULL DEFAULT 'contado';
