CREATE TABLE IF NOT EXISTS contract_item_specs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    item_code TEXT NOT NULL,
    product_type TEXT,
    product_category TEXT NOT NULL DEFAULT 'textil',
    description TEXT,
    is_textile INTEGER NOT NULL DEFAULT 1,
    size TEXT,
    color TEXT,
    fabric TEXT,
    grammage TEXT,
    measurements TEXT,
    finishing TEXT,
    has_embroidery INTEGER NOT NULL DEFAULT 0,
    embroidery_details TEXT,
    has_screen_printing INTEGER NOT NULL DEFAULT 0,
    screen_printing_details TEXT,
    logo_position TEXT,
    quantity NUMERIC NOT NULL,
    unit TEXT,
    label TEXT,
    destination_dependency_id INTEGER,
    technical_notes TEXT,
    attachment_path TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(item_code)) > 0),
    CHECK (quantity > 0),
    CHECK (product_category IN ('textil', 'consumo', 'otro')),
    CHECK (status IN ('draft', 'confirmed', 'cancelled')),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (destination_dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_contract_item_specs_contract_id
    ON contract_item_specs(contract_id, status, item_code);

CREATE INDEX IF NOT EXISTS idx_contract_item_specs_search
    ON contract_item_specs(item_code, description, product_type, label);

CREATE INDEX IF NOT EXISTS idx_contract_item_specs_destination
    ON contract_item_specs(destination_dependency_id);
