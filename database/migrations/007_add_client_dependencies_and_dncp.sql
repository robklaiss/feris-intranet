CREATE TABLE IF NOT EXISTS client_dependencies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    address TEXT,
    city TEXT,
    phone TEXT,
    email TEXT,
    operational_contact_name TEXT,
    operational_contact_phone TEXT,
    reception_contact_name TEXT,
    reception_contact_phone TEXT,
    billing_contact_id INTEGER,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_contact_id) REFERENCES client_billing_contacts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS client_billing_contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    dependency_id INTEGER,
    name TEXT NOT NULL,
    role TEXT,
    phone TEXT,
    email TEXT,
    ruc TEXT,
    business_name TEXT,
    address TEXT,
    is_default INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (dependency_id) REFERENCES client_dependencies(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_dncp_data (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL UNIQUE,
    tender_id TEXT,
    contract_number TEXT,
    customer_purchase_order_number TEXT,
    public_entity TEXT,
    requesting_dependency TEXT,
    procurement_modality TEXT,
    procurement_code TEXT,
    contract_date TEXT,
    valid_from TEXT,
    valid_until TEXT,
    currency TEXT,
    fiscal_business_name TEXT,
    fiscal_ruc TEXT,
    billing_contact_id INTEGER,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_contact_id) REFERENCES client_billing_contacts(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_client_dependencies_client_id ON client_dependencies(client_id, name);
CREATE INDEX IF NOT EXISTS idx_client_dependencies_search ON client_dependencies(name, city);
CREATE INDEX IF NOT EXISTS idx_client_billing_contacts_client_id ON client_billing_contacts(client_id, is_default);
CREATE INDEX IF NOT EXISTS idx_client_billing_contacts_dependency_id ON client_billing_contacts(dependency_id);
CREATE INDEX IF NOT EXISTS idx_client_billing_contacts_search ON client_billing_contacts(name, ruc, business_name);
CREATE INDEX IF NOT EXISTS idx_contract_dncp_contract_id ON contract_dncp_data(contract_id);
CREATE INDEX IF NOT EXISTS idx_contract_dncp_tender_contract ON contract_dncp_data(tender_id, contract_number);
