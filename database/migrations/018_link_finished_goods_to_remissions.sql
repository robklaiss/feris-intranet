PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS remission_items_phase13 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    remission_id INTEGER NOT NULL,
    delivery_note_item_id INTEGER,
    finished_goods_inventory_id INTEGER,
    packaging_order_id INTEGER,
    production_order_id INTEGER,
    contract_item_spec_id INTEGER,
    item_code TEXT,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    label TEXT,
    quantity_available_before NUMERIC,
    quantity_available_after NUMERIC,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remission_id) REFERENCES remissions(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_note_item_id) REFERENCES delivery_note_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (finished_goods_inventory_id) REFERENCES finished_goods_inventory(id) ON DELETE RESTRICT,
    FOREIGN KEY (packaging_order_id) REFERENCES packaging_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL,
    CHECK (quantity > 0)
);

INSERT INTO remission_items_phase13 (
    id, remission_id, delivery_note_item_id, product_name, unit_measure, quantity, unit_price,
    total_item, created_at, updated_at
)
SELECT
    id, remission_id, delivery_note_item_id, product_name, unit_measure, quantity, unit_price,
    total_item, created_at, updated_at
FROM remission_items;

DROP TABLE remission_items;
ALTER TABLE remission_items_phase13 RENAME TO remission_items;

PRAGMA foreign_keys=on;

CREATE INDEX IF NOT EXISTS idx_remission_items_dn_item_id ON remission_items(delivery_note_item_id);
CREATE INDEX IF NOT EXISTS idx_remission_items_finished_goods_id ON remission_items(finished_goods_inventory_id);
CREATE INDEX IF NOT EXISTS idx_remission_items_traceability
    ON remission_items(remission_id, finished_goods_inventory_id, packaging_order_id, production_order_id, item_code);
