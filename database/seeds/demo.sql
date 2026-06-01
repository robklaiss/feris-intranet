INSERT OR IGNORE INTO numerators (module, prefix, current_value, padding, updated_at)
VALUES
    ('delivery_notes', 'NE-', 7, 6, CURRENT_TIMESTAMP),
    ('remissions', 'REM-', 4, 6, CURRENT_TIMESTAMP),
    ('invoices', 'FAC-', 2, 6, CURRENT_TIMESTAMP),
    ('purchase_orders', 'OC-', 3, 6, CURRENT_TIMESTAMP);

INSERT OR IGNORE INTO clients (id, name, tax_id, addresses, contacts, status)
VALUES
    (1, 'Constructora Central S.A.', '80012345-6', 'Av. Mariscal Lopez 1234, Asuncion', 'Compras: compras@central.com | +595 981 000000', 'active'),
    (2, 'Servicios Industriales del Sur', '80098765-1', 'Ruta PY01 Km 25, Capiata', 'Operaciones: ops@sur.com | +595 971 111111', 'active');

INSERT OR IGNORE INTO contracts (
    id, client_id, date, contract_number, reference_number, contract_type, tax_id, status, notes, total_amount, is_provisional, provisional_data
)
VALUES
    (
        1,
        1,
        '2026-03-01',
        'CT-2026-001',
        'ID-7788',
        'Suministro mensual',
        '80012345-6',
        'confirmed',
        'Contrato demo para materiales galvanizados.',
        102500000,
        0,
        NULL
    );

INSERT OR IGNORE INTO contract_items (id, contract_id, product_name, unit_measure, quantity, unit_price, total_item, notes)
VALUES
    (1, 1, 'Chapón galvanizado 2mm', 'm2', 1200, 65000, 78000000, 'Entrega fraccionada'),
    (2, 1, 'Perfil estructural 4x2', 'unidad', 500, 49000, 24500000, 'Incluye corte');

INSERT OR IGNORE INTO purchase_orders (
    id, client_id, contract_id, order_number, order_date, identifier_number, status, notes, total_amount, is_manual, is_provisional, provisional_data
)
VALUES
    (1, 1, 1, 'OC-000001', '2026-03-03', 'OC-CLIENTE-100', 'confirmed', 'Primera orden vinculada al contrato.', 35750000, 0, 0, NULL);

INSERT OR IGNORE INTO purchase_order_items (id, purchase_order_id, contract_item_id, product_name, unit_measure, quantity, unit_price, total_item)
VALUES
    (1, 1, 1, 'Chapón galvanizado 2mm', 'm2', 350, 65000, 22750000),
    (2, 1, 2, 'Perfil estructural 4x2', 'unidad', 265, 49000, 12985000);

INSERT OR IGNORE INTO delivery_notes (
    id, purchase_order_id, note_number, note_date, status, notes, total_amount
)
VALUES
    (1, 1, 'NE-000001', '2026-03-04', 'confirmed', 'Entrega parcial obra central.', 15665000);

INSERT OR IGNORE INTO delivery_note_items (id, delivery_note_id, purchase_order_item_id, product_name, unit_measure, quantity, unit_price, total_item)
VALUES
    (1, 1, 1, 'Chapón galvanizado 2mm', 'm2', 120, 65000, 7800000),
    (2, 1, 2, 'Perfil estructural 4x2', 'unidad', 160, 49000, 7840000);

INSERT OR IGNORE INTO remissions (
    id, remission_number, remission_date, status, notes, total_amount
)
VALUES
    (1, 'REM-000001', '2026-03-05', 'confirmed', 'Remisión demo a partir de una nota.', 11390000);

INSERT OR IGNORE INTO remission_source_notes (remission_id, delivery_note_id)
VALUES (1, 1);

INSERT OR IGNORE INTO remission_items (id, remission_id, delivery_note_item_id, product_name, unit_measure, quantity, unit_price, total_item)
VALUES
    (1, 1, 1, 'Chapón galvanizado 2mm', 'm2', 90, 65000, 5850000),
    (2, 1, 2, 'Perfil estructural 4x2', 'unidad', 113, 49000, 5537000);

INSERT OR IGNORE INTO invoices (
    id, invoice_number, invoice_date, status, notes, total_amount, billing_status, billing_payload
)
VALUES
    (1, 'FAC-000001', '2026-03-06', 'draft', 'Factura local en preparación.', 5850000, 'pending', NULL);

INSERT OR IGNORE INTO invoice_source_remissions (invoice_id, remission_id)
VALUES (1, 1);

INSERT OR IGNORE INTO invoice_items (id, invoice_id, remission_item_id, product_name, unit_measure, quantity, unit_price, total_item)
VALUES
    (1, 1, 1, 'Chapón galvanizado 2mm', 'm2', 90, 65000, 5850000);

INSERT OR IGNORE INTO audit_log (entity_type, entity_id, action, changes)
VALUES
    ('seed', 1, 'bootstrap', '{"message":"Seed demo inicial"}');
