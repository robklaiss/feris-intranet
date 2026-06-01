ALTER TABLE purchase_orders ADD COLUMN linked_contract_snapshot TEXT;
ALTER TABLE purchase_orders ADD COLUMN live_sync_fields TEXT;

ALTER TABLE delivery_notes ADD COLUMN client_id INTEGER;
ALTER TABLE delivery_notes ADD COLUMN contract_id INTEGER;
ALTER TABLE delivery_notes ADD COLUMN identifier_number TEXT;
ALTER TABLE delivery_notes ADD COLUMN contract_type TEXT;
ALTER TABLE delivery_notes ADD COLUMN tax_id TEXT;

CREATE TABLE IF NOT EXISTS delivery_note_source_orders (
    delivery_note_id INTEGER NOT NULL,
    purchase_order_id INTEGER NOT NULL,
    PRIMARY KEY (delivery_note_id, purchase_order_id),
    FOREIGN KEY (delivery_note_id) REFERENCES delivery_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO delivery_note_source_orders (delivery_note_id, purchase_order_id)
SELECT id, purchase_order_id
FROM delivery_notes
WHERE purchase_order_id IS NOT NULL;

UPDATE delivery_notes
SET
    client_id = COALESCE(
        client_id,
        (SELECT purchase_orders.client_id FROM purchase_orders WHERE purchase_orders.id = delivery_notes.purchase_order_id)
    ),
    contract_id = COALESCE(
        contract_id,
        (SELECT purchase_orders.contract_id FROM purchase_orders WHERE purchase_orders.id = delivery_notes.purchase_order_id)
    ),
    identifier_number = COALESCE(
        identifier_number,
        (SELECT COALESCE(purchase_orders.identifier_number, contracts.reference_number)
         FROM purchase_orders
         LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
         WHERE purchase_orders.id = delivery_notes.purchase_order_id)
    ),
    contract_type = COALESCE(
        contract_type,
        (SELECT COALESCE(purchase_orders.contract_type, contracts.contract_type)
         FROM purchase_orders
         LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
         WHERE purchase_orders.id = delivery_notes.purchase_order_id)
    ),
    tax_id = COALESCE(
        tax_id,
        (SELECT COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id)
         FROM purchase_orders
         LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
         LEFT JOIN clients ON clients.id = purchase_orders.client_id
         WHERE purchase_orders.id = delivery_notes.purchase_order_id)
    )
WHERE purchase_order_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_purchase_orders_contract_sync ON purchase_orders(contract_id, is_provisional);
CREATE INDEX IF NOT EXISTS idx_delivery_notes_contract_id ON delivery_notes(contract_id);
CREATE INDEX IF NOT EXISTS idx_delivery_notes_client_id ON delivery_notes(client_id);
