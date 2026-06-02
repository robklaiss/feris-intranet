CREATE TABLE IF NOT EXISTS quality_control_checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    sewing_order_id INTEGER,
    external_work_order_id INTEGER,
    external_work_receipt_id INTEGER,
    qc_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    checked_by INTEGER,
    checked_at TEXT,
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
    CHECK (length(trim(qc_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'partially_approved', 'approved', 'rejected', 'rework_required', 'cancelled', 'closed')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (sewing_order_id) REFERENCES sewing_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_order_id) REFERENCES external_work_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_receipt_id) REFERENCES external_work_receipts(id) ON DELETE RESTRICT,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_quality_control_checks_search
    ON quality_control_checks(status, production_order_id, sewing_order_id, external_work_order_id, external_work_receipt_id, checked_at);

CREATE TABLE IF NOT EXISTS quality_control_check_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quality_control_check_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    sewing_order_item_id INTEGER,
    external_work_receipt_item_id INTEGER,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    quantity_received NUMERIC NOT NULL,
    quantity_approved NUMERIC NOT NULL DEFAULT 0,
    quantity_rejected NUMERIC NOT NULL DEFAULT 0,
    quantity_rework NUMERIC NOT NULL DEFAULT 0,
    model_ok INTEGER NOT NULL DEFAULT 0,
    size_ok INTEGER NOT NULL DEFAULT 0,
    quantity_ok INTEGER NOT NULL DEFAULT 0,
    sewing_ok INTEGER NOT NULL DEFAULT 0,
    finishing_ok INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_received > 0),
    CHECK (quantity_approved >= 0),
    CHECK (quantity_rejected >= 0),
    CHECK (quantity_rework >= 0),
    CHECK (quantity_approved + quantity_rejected + quantity_rework <= quantity_received),
    CHECK (model_ok IN (0, 1)),
    CHECK (size_ok IN (0, 1)),
    CHECK (quantity_ok IN (0, 1)),
    CHECK (sewing_ok IN (0, 1)),
    CHECK (finishing_ok IN (0, 1)),
    CHECK (status IN ('pending', 'approved', 'rejected', 'rework_required', 'partial')),
    FOREIGN KEY (quality_control_check_id) REFERENCES quality_control_checks(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (sewing_order_item_id) REFERENCES sewing_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (external_work_receipt_item_id) REFERENCES external_work_receipt_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_quality_control_check_items_check
    ON quality_control_check_items(quality_control_check_id, production_order_item_id, sewing_order_item_id, external_work_receipt_item_id, status);

CREATE TABLE IF NOT EXISTS quality_rework_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quality_control_check_id INTEGER NOT NULL,
    quality_control_check_item_id INTEGER NOT NULL,
    production_order_id INTEGER NOT NULL,
    sewing_order_id INTEGER,
    seamster_id INTEGER,
    rework_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    reason TEXT,
    quantity NUMERIC NOT NULL,
    assigned_to TEXT,
    due_date TEXT,
    completed_at TEXT,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(rework_number)) > 0),
    CHECK (quantity > 0),
    CHECK (status IN ('draft', 'assigned', 'completed', 'cancelled', 'closed')),
    FOREIGN KEY (quality_control_check_id) REFERENCES quality_control_checks(id) ON DELETE CASCADE,
    FOREIGN KEY (quality_control_check_item_id) REFERENCES quality_control_check_items(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (sewing_order_id) REFERENCES sewing_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (seamster_id) REFERENCES seamsters(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_quality_rework_orders_search
    ON quality_rework_orders(status, production_order_id, sewing_order_id, seamster_id, quality_control_check_id, due_date);

PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS production_orders_phase11 (
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
    CHECK (production_stage IN ('pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting', 'in_cutting', 'waiting_external_work', 'external_work_sent', 'external_work_received', 'in_sewing', 'quality_control', 'rework_required')),
    FOREIGN KEY (customer_purchase_order_id) REFERENCES customer_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO production_orders_phase11
SELECT * FROM production_orders;

DROP TABLE production_orders;
ALTER TABLE production_orders_phase11 RENAME TO production_orders;

CREATE INDEX IF NOT EXISTS idx_production_orders_search
    ON production_orders(status, production_stage, customer_purchase_order_id, contract_id, client_id, dependency_id);

PRAGMA foreign_keys=on;
