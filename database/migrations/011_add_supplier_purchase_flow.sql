CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    ruc TEXT,
    contact_name TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    payment_terms TEXT,
    delivery_terms TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(name)) > 0),
    CHECK (status IN ('active', 'inactive'))
);

CREATE INDEX IF NOT EXISTS idx_suppliers_search
    ON suppliers(status, name, ruc);

CREATE TABLE IF NOT EXISTS purchase_requisitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_check_id INTEGER NOT NULL UNIQUE,
    production_order_id INTEGER NOT NULL,
    requisition_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    requested_by INTEGER,
    requested_at TEXT,
    approved_by INTEGER,
    approved_at TEXT,
    cancelled_by INTEGER,
    cancelled_at TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(requisition_number)) > 0),
    CHECK (status IN ('draft', 'requested', 'quoted', 'approved', 'cancelled', 'closed')),
    FOREIGN KEY (stock_check_id) REFERENCES stock_checks(id) ON DELETE RESTRICT,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_purchase_requisitions_search
    ON purchase_requisitions(status, stock_check_id, production_order_id);

CREATE TABLE IF NOT EXISTS purchase_requisition_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_requisition_id INTEGER NOT NULL,
    stock_check_item_id INTEGER,
    required_material_type TEXT NOT NULL,
    required_description TEXT NOT NULL,
    required_unit TEXT NOT NULL,
    missing_quantity NUMERIC NOT NULL,
    requested_quantity NUMERIC NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(required_material_type)) > 0),
    CHECK (length(trim(required_description)) > 0),
    CHECK (length(trim(required_unit)) > 0),
    CHECK (missing_quantity > 0),
    CHECK (requested_quantity > 0),
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_check_item_id) REFERENCES stock_check_items(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_purchase_requisition_items_requisition
    ON purchase_requisition_items(purchase_requisition_id, stock_check_item_id);

CREATE TABLE IF NOT EXISTS supplier_quote_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_requisition_id INTEGER NOT NULL,
    supplier_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    sent_at TEXT,
    response_due_date TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (status IN ('draft', 'sent', 'received', 'cancelled')),
    UNIQUE (purchase_requisition_id, supplier_id),
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_supplier_quote_requests_requisition
    ON supplier_quote_requests(purchase_requisition_id, status, supplier_id);

CREATE TABLE IF NOT EXISTS supplier_quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_requisition_id INTEGER NOT NULL,
    supplier_id INTEGER NOT NULL,
    quote_number TEXT NOT NULL,
    quote_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    currency TEXT NOT NULL DEFAULT 'PYG',
    subtotal NUMERIC NOT NULL DEFAULT 0,
    tax_amount NUMERIC NOT NULL DEFAULT 0,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    delivery_days INTEGER,
    payment_terms TEXT,
    attachment_path TEXT,
    notes TEXT,
    received_at TEXT,
    approved_by INTEGER,
    approved_at TEXT,
    rejected_by INTEGER,
    rejected_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(quote_number)) > 0),
    CHECK (length(trim(quote_date)) > 0),
    CHECK (status IN ('draft', 'received', 'approved', 'rejected', 'cancelled')),
    CHECK (length(trim(currency)) > 0),
    CHECK (subtotal >= 0),
    CHECK (tax_amount >= 0),
    CHECK (total_amount >= 0),
    CHECK (delivery_days IS NULL OR delivery_days >= 0),
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_supplier_quotes_requisition
    ON supplier_quotes(purchase_requisition_id, status, supplier_id);

CREATE TABLE IF NOT EXISTS supplier_quote_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_quote_id INTEGER NOT NULL,
    purchase_requisition_item_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    unit TEXT NOT NULL,
    quantity NUMERIC NOT NULL,
    unit_price NUMERIC NOT NULL,
    total_price NUMERIC NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(description)) > 0),
    CHECK (length(trim(unit)) > 0),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (total_price >= 0),
    FOREIGN KEY (supplier_quote_id) REFERENCES supplier_quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_requisition_item_id) REFERENCES purchase_requisition_items(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_supplier_quote_items_quote
    ON supplier_quote_items(supplier_quote_id, purchase_requisition_item_id);

CREATE TABLE IF NOT EXISTS supplier_purchase_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_quote_id INTEGER NOT NULL UNIQUE,
    purchase_requisition_id INTEGER NOT NULL,
    supplier_id INTEGER NOT NULL,
    supplier_po_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    iso_form_number TEXT,
    requested_by INTEGER,
    approved_by INTEGER,
    approved_at TEXT,
    order_date TEXT NOT NULL,
    expected_delivery_date TEXT,
    currency TEXT NOT NULL DEFAULT 'PYG',
    subtotal NUMERIC NOT NULL DEFAULT 0,
    tax_amount NUMERIC NOT NULL DEFAULT 0,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    payment_terms TEXT,
    delivery_terms TEXT,
    purchase_reason TEXT,
    supplier_comparison_summary TEXT,
    product_specifications TEXT,
    quality_requirements TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(supplier_po_number)) > 0),
    CHECK (status IN ('draft', 'confirmed', 'sent', 'partially_received', 'received', 'cancelled', 'closed')),
    CHECK (length(trim(order_date)) > 0),
    CHECK (length(trim(currency)) > 0),
    CHECK (subtotal >= 0),
    CHECK (tax_amount >= 0),
    CHECK (total_amount >= 0),
    FOREIGN KEY (supplier_quote_id) REFERENCES supplier_quotes(id) ON DELETE RESTRICT,
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_supplier_purchase_orders_search
    ON supplier_purchase_orders(status, purchase_requisition_id, supplier_id);

CREATE TABLE IF NOT EXISTS supplier_purchase_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_purchase_order_id INTEGER NOT NULL,
    purchase_requisition_item_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    unit TEXT NOT NULL,
    quantity NUMERIC NOT NULL,
    unit_price NUMERIC NOT NULL,
    total_price NUMERIC NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(description)) > 0),
    CHECK (length(trim(unit)) > 0),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (total_price >= 0),
    FOREIGN KEY (supplier_purchase_order_id) REFERENCES supplier_purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_requisition_item_id) REFERENCES purchase_requisition_items(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_supplier_purchase_order_items_order
    ON supplier_purchase_order_items(supplier_purchase_order_id, purchase_requisition_item_id);
