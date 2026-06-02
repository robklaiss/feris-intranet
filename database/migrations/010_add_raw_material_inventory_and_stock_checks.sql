CREATE TABLE IF NOT EXISTS raw_material_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_code TEXT NOT NULL UNIQUE,
    material_type TEXT NOT NULL,
    description TEXT NOT NULL,
    unit TEXT NOT NULL,
    quantity_available NUMERIC NOT NULL DEFAULT 0,
    quantity_reserved NUMERIC NOT NULL DEFAULT 0,
    minimum_stock NUMERIC NOT NULL DEFAULT 0,
    supplier_name TEXT,
    supplier_ruc TEXT,
    lot_number TEXT,
    location TEXT,
    cost NUMERIC,
    related_item_code TEXT,
    related_product_type TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(internal_code)) > 0),
    CHECK (length(trim(material_type)) > 0),
    CHECK (length(trim(description)) > 0),
    CHECK (length(trim(unit)) > 0),
    CHECK (quantity_available >= 0),
    CHECK (quantity_reserved >= 0),
    CHECK (minimum_stock >= 0),
    CHECK (cost IS NULL OR cost >= 0),
    CHECK (status IN ('active', 'inactive', 'depleted')),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_raw_material_inventory_search
    ON raw_material_inventory(status, material_type, related_item_code, related_product_type);

CREATE TABLE IF NOT EXISTS stock_checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    check_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    checked_by INTEGER,
    checked_at TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(check_number)) > 0),
    CHECK (status IN ('draft', 'sufficient', 'insufficient', 'reserved', 'cancelled', 'closed')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_stock_checks_order
    ON stock_checks(production_order_id, status);

CREATE TABLE IF NOT EXISTS stock_check_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_check_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    raw_material_inventory_id INTEGER,
    required_material_type TEXT NOT NULL,
    required_description TEXT NOT NULL,
    required_unit TEXT NOT NULL,
    required_quantity NUMERIC NOT NULL,
    available_quantity NUMERIC NOT NULL DEFAULT 0,
    reserved_quantity NUMERIC NOT NULL DEFAULT 0,
    missing_quantity NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(required_material_type)) > 0),
    CHECK (length(trim(required_description)) > 0),
    CHECK (length(trim(required_unit)) > 0),
    CHECK (required_quantity > 0),
    CHECK (available_quantity >= 0),
    CHECK (reserved_quantity >= 0),
    CHECK (missing_quantity >= 0),
    CHECK (status IN ('sufficient', 'insufficient', 'reserved')),
    FOREIGN KEY (stock_check_id) REFERENCES stock_checks(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (raw_material_inventory_id) REFERENCES raw_material_inventory(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_stock_check_items_check
    ON stock_check_items(stock_check_id, production_order_item_id, status);

CREATE TABLE IF NOT EXISTS raw_material_reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    stock_check_item_id INTEGER NOT NULL,
    raw_material_inventory_id INTEGER NOT NULL,
    reserved_quantity NUMERIC NOT NULL,
    status TEXT NOT NULL DEFAULT 'reserved',
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TEXT,
    consumed_at TEXT,
    notes TEXT,
    CHECK (reserved_quantity > 0),
    CHECK (status IN ('reserved', 'released', 'consumed', 'cancelled')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_check_item_id) REFERENCES stock_check_items(id) ON DELETE CASCADE,
    FOREIGN KEY (raw_material_inventory_id) REFERENCES raw_material_inventory(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_raw_material_reservations_active
    ON raw_material_reservations(production_order_id, raw_material_inventory_id, status);

PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS production_orders_phase5 (
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
    CHECK (production_stage IN ('pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting')),
    FOREIGN KEY (customer_purchase_order_id) REFERENCES customer_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO production_orders_phase5
SELECT * FROM production_orders;

DROP TABLE production_orders;
ALTER TABLE production_orders_phase5 RENAME TO production_orders;

CREATE INDEX IF NOT EXISTS idx_production_orders_search
    ON production_orders(status, production_stage, customer_purchase_order_id, contract_id, client_id, dependency_id);

PRAGMA foreign_keys=on;
