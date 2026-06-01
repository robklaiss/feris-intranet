CREATE TABLE IF NOT EXISTS licitaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    call_number TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    institution TEXT NOT NULL,
    publish_date TEXT,
    opening_date TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS licitacion_checklists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    licitacion_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    is_attached INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licitacion_id) REFERENCES licitaciones(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS licitacion_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    licitacion_id INTEGER NOT NULL,
    category TEXT NOT NULL DEFAULT 'adjunto',
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL,
    mime_type TEXT,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licitacion_id) REFERENCES licitaciones(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_licitacion_checklists_licitacion_id ON licitacion_checklists(licitacion_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_licitacion_files_licitacion_id ON licitacion_files(licitacion_id, category, created_at);
