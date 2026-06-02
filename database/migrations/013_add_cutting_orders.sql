CREATE TABLE IF NOT EXISTS cutting_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    production_order_id INTEGER NOT NULL,
    stock_check_id INTEGER,
    cutting_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    cut_by TEXT,
    planned_date TEXT,
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
    CHECK (length(trim(cutting_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'in_progress', 'completed', 'cancelled', 'closed')),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (stock_check_id) REFERENCES stock_checks(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_cutting_orders_search
    ON cutting_orders(status, production_order_id, stock_check_id, planned_date);

CREATE TABLE IF NOT EXISTS cutting_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cutting_order_id INTEGER NOT NULL,
    production_order_item_id INTEGER NOT NULL,
    contract_item_spec_id INTEGER,
    item_code TEXT NOT NULL,
    product_type TEXT,
    description TEXT,
    size TEXT,
    color TEXT,
    fabric TEXT,
    measurements TEXT,
    quantity_to_cut NUMERIC NOT NULL,
    quantity_cut NUMERIC NOT NULL DEFAULT 0,
    unit TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity_to_cut > 0),
    CHECK (quantity_cut >= 0),
    CHECK (quantity_cut <= quantity_to_cut),
    CHECK (status IN ('pending', 'cut', 'partial', 'cancelled')),
    FOREIGN KEY (cutting_order_id) REFERENCES cutting_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_item_id) REFERENCES production_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_item_spec_id) REFERENCES contract_item_specs(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_cutting_order_items_order
    ON cutting_order_items(cutting_order_id, production_order_item_id, status);

CREATE TABLE IF NOT EXISTS cutting_order_materials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cutting_order_id INTEGER NOT NULL,
    cutting_order_item_id INTEGER,
    raw_material_reservation_id INTEGER NOT NULL,
    raw_material_inventory_id INTEGER NOT NULL,
    internal_code TEXT NOT NULL,
    material_type TEXT NOT NULL,
    description TEXT,
    unit TEXT NOT NULL,
    reserved_quantity NUMERIC NOT NULL,
    consumed_quantity NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'reserved',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(internal_code)) > 0),
    CHECK (length(trim(material_type)) > 0),
    CHECK (length(trim(unit)) > 0),
    CHECK (reserved_quantity > 0),
    CHECK (consumed_quantity >= 0),
    CHECK (consumed_quantity <= reserved_quantity),
    CHECK (status IN ('reserved', 'consumed', 'released', 'cancelled')),
    FOREIGN KEY (cutting_order_id) REFERENCES cutting_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (cutting_order_item_id) REFERENCES cutting_order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (raw_material_reservation_id) REFERENCES raw_material_reservations(id) ON DELETE RESTRICT,
    FOREIGN KEY (raw_material_inventory_id) REFERENCES raw_material_inventory(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_cutting_order_materials_order
    ON cutting_order_materials(cutting_order_id, raw_material_reservation_id, status);

PRAGMA foreign_keys=off;

CREATE TABLE IF NOT EXISTS production_orders_phase8 (
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
    CHECK (production_stage IN ('pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting', 'in_cutting', 'waiting_external_work', 'in_sewing')),
    FOREIGN KEY (customer_purchase_order_id) REFERENCES customer_purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO production_orders_phase8
SELECT * FROM production_orders;

DROP TABLE production_orders;
ALTER TABLE production_orders_phase8 RENAME TO production_orders;

CREATE INDEX IF NOT EXISTS idx_production_orders_search
    ON production_orders(status, production_stage, customer_purchase_order_id, contract_id, client_id, dependency_id);

PRAGMA foreign_keys=on;
