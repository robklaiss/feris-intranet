CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    last_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE audit_log ADD COLUMN user_id INTEGER;
ALTER TABLE audit_log ADD COLUMN username TEXT;
ALTER TABLE audit_log ADD COLUMN document_type TEXT;
ALTER TABLE audit_log ADD COLUMN document_number TEXT;
ALTER TABLE audit_log ADD COLUMN previous_state TEXT;
ALTER TABLE audit_log ADD COLUMN new_state TEXT;
ALTER TABLE audit_log ADD COLUMN payload_summary TEXT;

UPDATE contracts
SET status = CASE
    WHEN status IN ('active', 'vigente') THEN 'confirmed'
    WHEN status IN ('cancelled', 'anulado') THEN 'cancelled'
    WHEN status IN ('closed', 'cerrado') THEN 'closed'
    ELSE 'draft'
END;

UPDATE purchase_orders
SET status = CASE
    WHEN status IN ('approved', 'issued', 'partial', 'confirmed') THEN 'confirmed'
    WHEN status IN ('cancelled', 'anulado') THEN 'cancelled'
    WHEN status IN ('closed', 'cerrado') THEN 'closed'
    ELSE 'draft'
END;

UPDATE delivery_notes
SET status = CASE
    WHEN status IN ('approved', 'issued', 'partial', 'confirmed') THEN 'confirmed'
    WHEN status IN ('cancelled', 'anulado') THEN 'cancelled'
    WHEN status IN ('closed', 'cerrado') THEN 'closed'
    ELSE 'draft'
END;

UPDATE remissions
SET status = CASE
    WHEN status IN ('approved', 'issued', 'partial', 'confirmed') THEN 'confirmed'
    WHEN status IN ('cancelled', 'anulado') THEN 'cancelled'
    WHEN status IN ('closed', 'cerrado') THEN 'closed'
    ELSE 'draft'
END;

UPDATE invoices
SET status = CASE
    WHEN status IN ('approved', 'issued', 'partial', 'confirmed') THEN 'confirmed'
    WHEN status IN ('cancelled', 'anulado') THEN 'cancelled'
    WHEN status IN ('closed', 'cerrado') THEN 'closed'
    ELSE 'draft'
END;

INSERT OR IGNORE INTO users (id, username, password_hash, full_name, role, status)
VALUES
    (1, 'admin', '$2y$12$I8TkV260zDgK9MKjDXw4Hu7Td2lesufeKD8eFHWU9xkc0T57Titp2', 'Administrador local', 'admin', 'active'),
    (2, 'operador', '$2y$12$ClVfal4c0NyfaNNa8RrK5e3ZslzKfEYvOE8rPwNOhxF2PL9WIuqoW', 'Operador local', 'operador', 'active'),
    (3, 'consulta', '$2y$12$DX0GKlRLM10xUqQ3sgnu6egSPvnW5NamWUGDbckX8AeqhiX0C0j.u', 'Consulta local', 'consulta', 'active');

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_audit_log_document ON audit_log(document_type, entity_id, created_at);
