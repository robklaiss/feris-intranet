CREATE TABLE IF NOT EXISTS external_work_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    cutting_order_id INTEGER NOT NULL,
    supplier_id INTEGER,
    external_work_number TEXT NOT NULL UNIQUE,
    work_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    send_note_number TEXT,
    sent_at TEXT,
    expected_return_date TEXT,
    returned_at TEXT,
    next_stage TEXT NOT NULL DEFAULT 'sewing',
    notes TEXT,
    created_by INTEGER,
    confirmed_by INTEGER,
    confirmed_at TEXT,
    cancelled_by INTEGER,
    cancelled_at TEXT,
    closed_by INTEGER,
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(external_work_number)) > 0),
    CHECK (work_type IN ('embroidery', 'screen_printing', 'both', 'other')),
    CHECK (status IN ('draft', 'confirmed', 'sent', 'partially_returned', 'returned', 'cancelled', 'closed')),
    CHECK (next_stage IN ('sewing', 'quality_control')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (cutting_order_id) REFERENCES cutting_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_external_work_orders_search
    ON external_work_orders(status, work_type, production_order_id, cutting_order_id, supplier_id, expected_return_date);

CREATE TABLE IF NOT EXISTS external_work_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_work_order_id INTEGER NOT NULL,
    cutting_order_item_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    quantity_sent NUMERIC NOT NULL,
    quantity_returned NUMERIC NOT NULL DEFAULT 0,
    quantity_rejected NUMERIC NOT NULL DEFAULT 0,
    work_details TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_sent > 0),
    CHECK (quantity_returned >= 0),
    CHECK (quantity_rejected >= 0),
    CHECK (quantity_returned <= quantity_sent),
    CHECK (quantity_rejected <= quantity_sent),
    CHECK (status IN ('pending', 'sent', 'partially_returned', 'returned', 'rejected', 'cancelled')),
    FOREIGN KEY (external_work_order_id) REFERENCES external_work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (cutting_order_item_id) REFERENCES cutting_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_external_work_order_items_order
    ON external_work_order_items(external_work_order_id, cutting_order_item_id, production_order_item_id, status);

CREATE TABLE IF NOT EXISTS external_work_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_work_order_id INTEGER NOT NULL,
    receipt_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    received_by INTEGER,
    received_at TEXT,
    next_stage TEXT NOT NULL DEFAULT 'sewing',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(receipt_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'cancelled', 'closed')),
    CHECK (next_stage IN ('sewing', 'quality_control')),
    FOREIGN KEY (external_work_order_id) REFERENCES external_work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_external_work_receipts_order
    ON external_work_receipts(external_work_order_id, status, received_at);

CREATE TABLE IF NOT EXISTS external_work_receipt_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_work_receipt_id INTEGER NOT NULL,
    external_work_order_item_id INTEGER NOT NULL,
    quantity_received NUMERIC NOT NULL,
    quantity_accepted NUMERIC NOT NULL DEFAULT 0,
    quantity_rejected NUMERIC NOT NULL DEFAULT 0,
    quality_notes TEXT,
    next_stage TEXT NOT NULL DEFAULT 'sewing',
    status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (quantity_received > 0),
    CHECK (quantity_accepted >= 0),
    CHECK (quantity_rejected >= 0),
    CHECK (quantity_accepted + quantity_rejected <= quantity_received),
    CHECK (next_stage IN ('sewing', 'quality_control')),
    CHECK (status IN ('pending', 'accepted', 'rejected', 'partial', 'cancelled')),
    FOREIGN KEY (external_work_receipt_id) REFERENCES external_work_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (external_work_order_item_id) REFERENCES external_work_order_items(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_external_work_receipt_items_receipt
    ON external_work_receipt_items(external_work_receipt_id, external_work_order_item_id, status);

PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS production_orders_phase9 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_purchase_order_id INTEGER NOT NULL,
    contract_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    dependency_id INTEGER,
    production_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    production_stage TEXT NOT NULL DEFAULT 'pending',
    planned_start_date TEXT,
    planned_end_date TEXT,
    notes TEXT,
    created_by INTEGER,
    confirmed_by INTEGER,
    confirmed_at TEXT,
    cancelled_by INTEGER,
    cancelled_at TEXT,
    closed_by INTEGER,
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(production_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'cancelled', 'closed')),
    CHECK (production_stage IN ('pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting', 'in_cutting', 'waiting_external_work', 'external_work_sent', 'external_work_received', 'in_sewing', 'quality_control')),
    FOREIGN KEY (customer_purchase_order_id) REFERENCES customer_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO production_orders_phase9
SELECT * FROM production_orders;

DROP TABLE production_orders;
ALTER TABLE production_orders_phase9 RENAME TO production_orders;

CREATE INDEX IF NOT EXISTS idx_production_orders_search
    ON production_orders(status, production_stage, customer_purchase_order_id, contract_id, client_id, dependency_id);

PRAGMA foreign_keys=on;
