CREATE TABLE IF NOT EXISTS packaging_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    quality_control_check_id INTEGER NOT NULL,
    packaging_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    packed_by INTEGER,
    packed_at TEXT,
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
    CHECK (length(trim(packaging_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'packed', 'cancelled', 'closed')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (quality_control_check_id) REFERENCES quality_control_checks(id) ON DELETE RESTRICT,
    FOREIGN KEY (packed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_packaging_orders_search
    ON packaging_orders(status, production_order_id, quality_control_check_id, packed_at);

CREATE TABLE IF NOT EXISTS packaging_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    packaging_order_id INTEGER NOT NULL,
    quality_control_check_item_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    quantity_approved NUMERIC NOT NULL,
    quantity_to_pack NUMERIC NOT NULL,
    quantity_packed NUMERIC NOT NULL DEFAULT 0,
    unit TEXT,
    label TEXT,
    package_code TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_approved >= 0),
    CHECK (quantity_to_pack > 0),
    CHECK (quantity_packed >= 0),
    CHECK (quantity_packed <= quantity_to_pack),
    CHECK (status IN ('pending', 'packed', 'partial', 'cancelled')),
    FOREIGN KEY (packaging_order_id) REFERENCES packaging_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (quality_control_check_item_id) REFERENCES quality_control_check_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_packaging_order_items_order
    ON packaging_order_items(packaging_order_id, quality_control_check_item_id, production_order_item_id, status);

CREATE TABLE IF NOT EXISTS finished_goods_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_code TEXT NOT NULL UNIQUE,
    packaging_order_id INTEGER NOT NULL,
    packaging_order_item_id INTEGER NOT NULL,
    production_order_id INTEGER NOT NULL,
    contract_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    dependency_id INTEGER,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    quantity_available NUMERIC NOT NULL,
    quantity_reserved NUMERIC NOT NULL DEFAULT 0,
    quantity_remitted NUMERIC NOT NULL DEFAULT 0,
    unit TEXT,
    label TEXT,
    package_code TEXT,
    location TEXT,
    status TEXT NOT NULL DEFAULT 'available',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    CHECK (length(trim(internal_code)) > 0),
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_available >= 0),
    CHECK (quantity_reserved >= 0),
    CHECK (quantity_remitted >= 0),
    CHECK (status IN ('available', 'reserved', 'remitted', 'depleted', 'cancelled')),
    FOREIGN KEY (packaging_order_id) REFERENCES packaging_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (packaging_order_item_id) REFERENCES packaging_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_finished_goods_inventory_search
    ON finished_goods_inventory(status, client_id, contract_id, dependency_id, item_code, size, label);

PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS production_orders_phase12 (
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
    CHECK (production_stage IN ('pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting', 'in_cutting', 'waiting_external_work', 'external_work_sent', 'external_work_received', 'in_sewing', 'quality_control', 'rework_required', 'packaging', 'completed')),
    FOREIGN KEY (customer_purchase_order_id) REFERENCES customer_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO production_orders_phase12
SELECT * FROM production_orders;

DROP TABLE production_orders;
ALTER TABLE production_orders_phase12 RENAME TO production_orders;

CREATE INDEX IF NOT EXISTS idx_production_orders_search
    ON production_orders(status, production_stage, customer_purchase_order_id, contract_id, client_id, dependency_id);

PRAGMA foreign_keys=on;
