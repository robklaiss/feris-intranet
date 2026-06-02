CREATE TABLE IF NOT EXISTS seamsters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    document_number TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(name)) > 0),
    CHECK (status IN ('active', 'inactive'))
);

CREATE INDEX IF NOT EXISTS idx_seamsters_search
    ON seamsters(status, name, document_number);

CREATE TABLE IF NOT EXISTS sewing_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    cutting_order_id INTEGER,
    external_work_order_id INTEGER,
    external_work_receipt_id INTEGER,
    seamster_id INTEGER NOT NULL,
    sewing_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    assigned_at TEXT,
    expected_completion_date TEXT,
    started_at TEXT,
    completed_at TEXT,
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
    CHECK (length(trim(sewing_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'in_progress', 'partially_completed', 'completed', 'cancelled', 'closed')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (cutting_order_id) REFERENCES cutting_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_order_id) REFERENCES external_work_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_receipt_id) REFERENCES external_work_receipts(id) ON DELETE RESTRICT,
    FOREIGN KEY (seamster_id) REFERENCES seamsters(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_sewing_orders_search
    ON sewing_orders(status, seamster_id, production_order_id, cutting_order_id, external_work_order_id, external_work_receipt_id, assigned_at);

CREATE TABLE IF NOT EXISTS sewing_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sewing_order_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    cutting_order_item_id INTEGER,
    external_work_receipt_item_id INTEGER,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    quantity_assigned NUMERIC NOT NULL,
    quantity_completed NUMERIC NOT NULL DEFAULT 0,
    quantity_rejected NUMERIC NOT NULL DEFAULT 0,
    quantity_pending NUMERIC NOT NULL,
    unit TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_assigned > 0),
    CHECK (quantity_completed >= 0),
    CHECK (quantity_rejected >= 0),
    CHECK (quantity_pending >= 0),
    CHECK (quantity_completed + quantity_rejected <= quantity_assigned),
    CHECK (status IN ('pending', 'in_progress', 'partially_completed', 'completed', 'rejected', 'cancelled')),
    FOREIGN KEY (sewing_order_id) REFERENCES sewing_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (cutting_order_item_id) REFERENCES cutting_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_receipt_item_id) REFERENCES external_work_receipt_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_sewing_order_items_order
    ON sewing_order_items(sewing_order_id, production_order_item_id, cutting_order_item_id, external_work_receipt_item_id, status);

CREATE TABLE IF NOT EXISTS sewing_progress_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sewing_order_id INTEGER NOT NULL,
    sewing_order_item_id INTEGER,
    quantity_completed NUMERIC NOT NULL DEFAULT 0,
    quantity_rejected NUMERIC NOT NULL DEFAULT 0,
    progress_date TEXT NOT NULL,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (quantity_completed >= 0),
    CHECK (quantity_rejected >= 0),
    CHECK (quantity_completed + quantity_rejected > 0),
    FOREIGN KEY (sewing_order_id) REFERENCES sewing_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (sewing_order_item_id) REFERENCES sewing_order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_sewing_progress_entries_order
    ON sewing_progress_entries(sewing_order_id, sewing_order_item_id, progress_date);
