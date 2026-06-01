ALTER TABLE audit_log ADD COLUMN document_id INTEGER;

UPDATE audit_log
SET document_id = COALESCE(document_id, entity_id)
WHERE document_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_audit_log_document_timeline
    ON audit_log(document_type, document_id, created_at);

CREATE INDEX IF NOT EXISTS idx_audit_log_user_timeline
    ON audit_log(user_id, created_at);
