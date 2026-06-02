CREATE TABLE IF NOT EXISTS goods_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_purchase_order_id INTEGER NOT NULL,
    supplier_id INTEGER NOT NULL,
    receipt_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    received_by INTEGER,
    received_at TEXT,
    delivery_note_number TEXT,
    invoice_number TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(receipt_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'cancelled', 'closed')),
    FOREIGN KEY (supplier_purchase_order_id) REFERENCES supplier_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_goods_receipts_search
    ON goods_receipts(status, supplier_purchase_order_id, supplier_id, receipt_number);

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    goods_receipt_id INTEGER NOT NULL,
    supplier_purchase_order_item_id INTEGER NOT NULL,
    raw_material_inventory_id INTEGER,
    description TEXT NOT NULL,
    unit TEXT NOT NULL,
    ordered_quantity NUMERIC NOT NULL,
    previously_received_quantity NUMERIC NOT NULL DEFAULT 0,
    received_quantity NUMERIC NOT NULL DEFAULT 0,
    rejected_quantity NUMERIC NOT NULL DEFAULT 0,
    accepted_quantity NUMERIC NOT NULL DEFAULT 0,
    internal_code TEXT,
    material_type TEXT NOT NULL,
    lot_number TEXT,
    location TEXT,
    cost NUMERIC,
    quality_status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(description)) > 0),
    CHECK (length(trim(unit)) > 0),
    CHECK (ordered_quantity > 0),
    CHECK (previously_received_quantity >= 0),
    CHECK (received_quantity >= 0),
    CHECK (rejected_quantity >= 0),
    CHECK (accepted_quantity >= 0),
    CHECK (accepted_quantity + rejected_quantity <= received_quantity),
    CHECK (cost IS NULL OR cost >= 0),
    CHECK (quality_status IN ('pending', 'accepted', 'rejected')),
    FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_purchase_order_item_id) REFERENCES supplier_purchase_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (raw_material_inventory_id) REFERENCES raw_material_inventory(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_goods_receipt_items_receipt
    ON goods_receipt_items(goods_receipt_id, supplier_purchase_order_item_id, quality_status);

ALTER TABLE raw_material_inventory ADD COLUMN source_goods_receipt_item_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_raw_material_inventory_receipt_source
    ON raw_material_inventory(source_goods_receipt_item_id, lot_number, supplier_name);
