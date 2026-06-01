CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    tax_id TEXT,
    addresses TEXT,
    contacts TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER,
    date TEXT NOT NULL,
    contract_number TEXT NOT NULL UNIQUE,
    reference_number TEXT,
    contract_type TEXT,
    tax_id TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    is_provisional INTEGER NOT NULL DEFAULT 0,
    provisional_data TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER,
    contract_id INTEGER,
    order_number TEXT NOT NULL UNIQUE,
    order_date TEXT NOT NULL,
    identifier_number TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    notes TEXT,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    is_manual INTEGER NOT NULL DEFAULT 0,
    is_provisional INTEGER NOT NULL DEFAULT 0,
    provisional_data TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_order_id INTEGER NOT NULL,
    contract_item_id INTEGER,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_item_id) REFERENCES contract_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS delivery_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_order_id INTEGER NOT NULL,
    note_number TEXT NOT NULL UNIQUE,
    note_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    notes TEXT,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS delivery_note_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    delivery_note_id INTEGER NOT NULL,
    purchase_order_item_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_note_id) REFERENCES delivery_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS remissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    remission_number TEXT NOT NULL UNIQUE,
    remission_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    notes TEXT,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS remission_source_notes (
    remission_id INTEGER NOT NULL,
    delivery_note_id INTEGER NOT NULL,
    PRIMARY KEY (remission_id, delivery_note_id),
    FOREIGN KEY (remission_id) REFERENCES remissions(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_note_id) REFERENCES delivery_notes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS remission_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    remission_id INTEGER NOT NULL,
    delivery_note_item_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remission_id) REFERENCES remissions(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_note_item_id) REFERENCES delivery_note_items(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT NOT NULL UNIQUE,
    invoice_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    notes TEXT,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    billing_status TEXT NOT NULL DEFAULT 'pending',
    billing_payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_source_remissions (
    invoice_id INTEGER NOT NULL,
    remission_id INTEGER NOT NULL,
    PRIMARY KEY (invoice_id, remission_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (remission_id) REFERENCES remissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    remission_item_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    unit_measure TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 0,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    total_item NUMERIC NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (remission_item_id) REFERENCES remission_items(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS numerators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    module TEXT NOT NULL UNIQUE,
    prefix TEXT NOT NULL,
    current_value INTEGER NOT NULL DEFAULT 0,
    padding INTEGER NOT NULL DEFAULT 6,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    changes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_contracts_client_id ON contracts(client_id);
CREATE INDEX IF NOT EXISTS idx_contract_items_contract_id ON contract_items(contract_id);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_contract_id ON purchase_orders(contract_id);
CREATE INDEX IF NOT EXISTS idx_purchase_order_items_contract_item_id ON purchase_order_items(contract_item_id);
CREATE INDEX IF NOT EXISTS idx_delivery_note_items_po_item_id ON delivery_note_items(purchase_order_item_id);
CREATE INDEX IF NOT EXISTS idx_remission_items_dn_item_id ON remission_items(delivery_note_item_id);
CREATE INDEX IF NOT EXISTS idx_invoice_items_remission_item_id ON invoice_items(remission_item_id);

