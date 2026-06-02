# Produccion Area Textiles

Documento de validacion tecnica para integrar el flujo de produccion textil en Industria Feris CRM.

## Estado de la decision

Este documento corresponde a la fase 1: validacion tecnica y modelo operativo. No implementa migraciones ni cambia el flujo comercial actual.

El siguiente paso de codigo debe empezar por:

1. clientes, dependencias y contactos de facturacion;
2. datos DNCP asociados a contratos;
3. especificaciones tecnicas por item de contrato.

La integracion SIFEN queda fuera de alcance para este modulo.

## Sistema actual relevante

El CRM ya opera el flujo comercial:

```text
Contrato -> Orden de compra cliente -> Nota interna -> Remision -> Factura
```

Tablas actuales relacionadas:

- `clients`
- `contracts`
- `contract_items`
- `purchase_orders`
- `purchase_order_items`
- `delivery_notes`
- `delivery_note_items`
- `remissions`
- `remission_items`
- `invoices`
- `invoice_items`
- `licitaciones`
- `audit_log`

Estados documentales actuales:

- `draft`
- `confirmed`
- `cancelled`
- `closed`

Acciones documentales actuales:

- `confirm`
- `cancel`
- `reopen`
- `close`
- `print`
- `export csv`

Regla base a conservar: solo documentos `confirmed` alimentan el siguiente paso del flujo.

## Modelo operativo propuesto

La produccion textil debe agregarse como una cadena trazable, no como pantallas aisladas:

```text
Contrato
-> Orden de compra del cliente
-> Orden de produccion
-> Verificacion de stock
-> Compras / presupuestos / proveedores
-> Ingreso de insumos
-> Orden de corte
-> Serigrafia / bordado externo
-> Confeccion
-> Control de calidad
-> Empaquetado
-> Inventario terminado
-> Remision
-> Factura
```

Separacion conceptual:

- Comercial: cliente, licitacion/DNCP, contrato, orden de compra del cliente.
- Produccion: orden de produccion, stock, corte, externo, confeccion, calidad, empaque.
- Compras: faltantes, pedidos de presupuesto, aprobacion, orden de compra proveedor, recepcion.
- Inventario: insumos, reservas, consumo, producto terminado, remision.

## Decisiones de integracion

### Licitaciones y contratos

Un ID de licitacion puede tener mas de un contrato. La relacion correcta es:

```text
licitaciones 1 -> N contratos
contratos 1 -> N ordenes de compra cliente
contratos 1 -> N items tecnicos
```

No se debe imponer una relacion 1 a 1 entre licitacion y contrato.

### Ordenes de compra del cliente

El sistema ya tiene `purchase_orders`. Para evitar duplicar conceptos, estas deben seguir representando la orden de compra manual del cliente.

La fase de produccion debe consumir solamente `purchase_orders.status = 'confirmed'`.

Campos futuros a evaluar sobre `purchase_orders`:

- dependencia destino;
- adjunto;
- saldos pendientes de produccion;
- snapshot tecnico del contrato al momento de confirmar.

### Items tecnicos de contrato

La tabla actual `contract_items` es comercial y monetaria. Para textiles se requiere una capa tecnica adicional.

Opcion recomendada para fase 3:

- mantener `contract_items` para cantidad, precio y saldo comercial;
- agregar `contract_item_specs` vinculada a `contract_items` o a `contracts`;
- si un item tecnico tambien requiere precio, conservar la referencia al item comercial.

Campos minimos textiles:

- codigo de item;
- tipo de producto;
- categoria `textil` o `consumo`;
- talle;
- tela;
- color;
- gramaje;
- medidas;
- terminacion;
- bordado si/no;
- serigrafia si/no;
- ubicacion de logo;
- etiqueta;
- dependencia destino;
- observaciones tecnicas;
- adjunto o referencia visual.

Campos minimos consumo:

- codigo de item;
- descripcion;
- cantidad;
- unidad;
- especificaciones;
- etiqueta;
- dependencia destino;
- observaciones.

### Estados de produccion

Conservar el estado principal y agregar etapa operativa:

```text
status = confirmed
production_stage = in_cutting
```

Etapas propuestas:

- `stock_check`
- `waiting_materials`
- `ready_for_cutting`
- `in_cutting`
- `waiting_external_work`
- `external_work_sent`
- `external_work_received`
- `in_sewing`
- `quality_control`
- `rework_required`
- `packaging`
- `completed`

### Auditoria

Cada transicion relevante debe usar `audit_log` y el servicio operativo existente.

Eventos minimos:

- crear orden de produccion;
- confirmar orden de produccion;
- verificar stock;
- generar pedido de presupuesto;
- registrar presupuesto;
- aprobar presupuesto;
- generar orden de compra proveedor;
- recibir insumos;
- codificar insumos;
- generar orden de corte;
- enviar a serigrafia/bordado;
- recibir de proveedor externo;
- asignar confeccion;
- aprobar/rechazar/reprocesar calidad;
- empaquetar;
- dar de alta inventario terminado;
- generar remision desde inventario terminado.

## Reglas operativas minimas

1. Solo contratos `confirmed` pueden alimentar ordenes de compra del cliente.
2. Solo ordenes de compra cliente `confirmed` pueden alimentar ordenes de produccion.
3. Solo ordenes de produccion `confirmed` pueden reservar o consumir stock.
4. Si falta stock, no se puede pasar a corte sin resolver faltantes.
5. Una excepcion administrativa futura debe quedar auditada.
6. Un pedido de presupuesto debe poder invitar al menos 3 proveedores.
7. Una compra a proveedor solo puede generarse desde presupuesto aprobado.
8. Los insumos recibidos deben ingresar al inventario antes de consumirse.
9. Corte debe reservar o descontar insumos.
10. Producto enviado a serigrafia/bordado no esta disponible internamente hasta su retorno.
11. Toda confeccion debe tener costurero asignado.
12. Solo calidad aprobada puede pasar a empaquetado.
13. Solo producto aprobado y empaquetado entra al inventario terminado.
14. Solo inventario terminado disponible puede generar remision.
15. Una remision consume inventario terminado.
16. No se debe facturar sin remision o inventario remitido, salvo excepcion futura auditada.

## Fases recomendadas

### Fase 2: clientes, dependencias, contactos y DNCP

Objetivo:

- agregar dependencias por cliente;
- agregar contactos de facturacion;
- asociar datos DNCP a contratos;
- permitir busqueda por cliente, RUC, dependencia e ID de licitacion.

Tablas candidatas:

- `client_dependencies`
- `client_billing_contacts`
- `contract_dncp_data`

Criterios go/no-go:

- migracion ejecuta en base nueva y existente;
- clientes existentes siguen abriendo;
- contratos existentes siguen abriendo;
- backup SQLite probado antes y despues;
- pruebas existentes pasan.

### Fase 3: especificaciones tecnicas por item

Objetivo:

- cargar items tecnicos textiles y de consumo;
- vincular codigo de item con contrato;
- preparar base para produccion.

Tabla candidata:

- `contract_item_specs`

Criterios go/no-go:

- contrato guarda items tecnicos;
- contrato confirmado conserva bloqueo de edicion destructiva;
- auditoria registra cambios relevantes;
- busqueda por codigo de item funciona.

### Fase 4: ordenes de produccion

Objetivo:

- generar ordenes de produccion desde ordenes de compra cliente confirmadas;
- controlar saldos de orden y contrato.

Tablas candidatas:

- `production_orders`
- `production_order_items`

Criterios go/no-go:

- no se puede producir sin orden confirmada;
- no se puede producir mas que el saldo pendiente;
- auditoria y timeline muestran la transicion.

### Fase 5: inventario de insumos y verificacion de stock

Objetivo:

- registrar y codificar insumos internos;
- asociar insumos a codigos de item, tipos de producto o tipos de material;
- verificar stock desde ordenes de produccion confirmadas;
- detectar faltantes y marcar la orden como `stock_pending`;
- reservar stock suficiente sin descontar existencia fisica;
- dejar la orden en `ready_for_cutting` cuando todo queda reservado.

Tablas candidatas:

- `raw_material_inventory`
- `stock_checks`
- `stock_check_items`
- `raw_material_reservations`

Criterios go/no-go:

- no se puede verificar stock desde produccion `draft`, `cancelled` o `closed`;
- no se permiten cantidades negativas ni reservas superiores al stock libre;
- no se permite reservar una verificacion insuficiente ni reservar dos veces;
- anular una orden de produccion libera reservas activas;
- la fase no genera compras, presupuestos, corte ni consumos reales.

### Fase 6: compras por faltantes

Objetivo:

- generar pedidos de presupuesto desde faltantes de stock;
- invitar proveedores;
- registrar y aprobar presupuestos;
- emitir OC proveedor con evidencia ISO 9001.

Tablas candidatas:

- `suppliers`
- `purchase_requisitions`
- `supplier_quote_requests`
- `supplier_quotes`
- `supplier_purchase_orders`

### Fase 7: recepcion e ingreso de insumos

Objetivo:

- recibir insumos desde OC proveedor confirmada/enviada;
- registrar recepciones parciales o totales;
- codificar internamente insumos aceptados;
- ingresar aceptados a `raw_material_inventory`;
- actualizar estado de OC proveedor a `partially_received` o `received`.

Tablas candidatas:

- `goods_receipts`
- `goods_receipt_items`
- `raw_material_inventory.source_goods_receipt_item_id`

Criterio de inventario:

- cada item aceptado de una recepcion confirmada crea un nuevo registro de `raw_material_inventory` con su propio `internal_code` y lote opcional;
- no se acumula automaticamente con registros existentes, porque la trazabilidad por entrega/lote es prioritaria;
- `internal_code` se mantiene unico en inventario;
- cantidades rechazadas no ingresan a inventario;
- la recepcion no reserva stock automaticamente, solo deja cantidad disponible para futuras verificaciones.

### Fase 8 en adelante

Implementar corte, externo, confeccion, calidad, empaque, inventario terminado y remision desde inventario en pasos separados.

Cada fase debe incluir migracion, repositorio, servicio de reglas, vistas, pruebas y verificacion de backup/restore.

## Checklist tecnico para iniciar fase 2

Antes de tocar codigo:

- ejecutar backup SQLite con `bin/backup_sqlite.sh`;
- confirmar ruta real de `DB_DATABASE` en `.env`;
- ejecutar `php bin/test.php`;
- revisar que la migracion nueva sea idempotente para SQLite;
- mantener SIFEN y `LocalBillingAdapter` sin cambios.

Implementacion sugerida:

1. Crear migracion `007_add_textile_foundation.sql`.
2. Agregar `client_dependencies`.
3. Agregar `client_billing_contacts`.
4. Agregar `contract_dncp_data`.
5. Extender repositorios de cliente y contrato sin romper campos existentes.
6. Agregar formularios y vistas usando el estilo actual de badges, filtros y timeline.
7. Agregar pruebas de repositorio y reglas de relacion licitacion 1 -> N contratos.
8. Ejecutar migraciones y pruebas.

## Riesgos principales

- Duplicar ordenes de compra cliente creando un modulo paralelo a `purchase_orders`.
- Mezclar inventario terminado con remisiones actuales sin control de disponibilidad.
- Reemplazar `contract_items` y romper saldos comerciales existentes.
- Agregar migraciones grandes sin backup previo.
- Incluir facturacion electronica/SIFEN antes de cerrar trazabilidad productiva.

## Fuera de alcance por ahora

- SIFEN.
- `LocalBillingAdapter`.
- `sifen-minisender-3`.
- Facturacion electronica.
- Cambios directos en servidor.
- Busqueda full-text.
- Excepciones administrativas no auditadas.

## Estado implementado por fases

Fases 2 a 14 implementan el flujo operativo textil sobre las tablas existentes y migraciones `007` a `018`: clientes/dependencias/DNCP, items tecnicos, OC cliente, produccion, stock checks, reservas, compras por faltantes, recepciones, corte, trabajos externos, confeccion, calidad, reproceso, empaquetado, inventario terminado y remisiones desde inventario terminado.

La fase 14 agrega hardening operativo: reportes textiles integrados en `/reports`, filtros globales ampliados, dashboard textil con contadores, prueba E2E del flujo suficiente/insuficiente y documentacion de reglas criticas.

## Como recorrer el flujo completo

Flujo con stock suficiente:

```text
Contrato confirmed
-> item tecnico confirmed
-> OC cliente confirmed
-> orden de produccion confirmed
-> stock check sufficient
-> reserva de stock
-> corte confirmado y completado
-> confeccion completada
-> calidad approved
-> empaquetado packed
-> inventario terminado available
-> remision confirmed desde inventario terminado
```

Flujo con faltantes:

```text
Stock check insufficient
-> pedido de presupuesto
-> 3 proveedores solicitados
-> presupuesto aprobado
-> OC proveedor confirmed
-> recepcion confirmed
-> alta de inventario de insumos
-> nueva verificacion de stock sufficient
-> reserva y corte
```

## Reglas criticas de inventario

- Solo producciones `confirmed` pueden verificar, reservar o consumir stock.
- Un stock check `insufficient` no puede reservar stock.
- Una produccion no puede crear corte sin reservas activas y etapa `ready_for_cutting`.
- El corte consume inventario de insumos y marca reservas como `consumed`.
- Las recepciones de proveedor solo crean inventario por cantidades aceptadas.
- El empaquetado solo toma cantidades aprobadas por calidad.
- El inventario terminado solo puede remitirse cuando tiene disponibilidad real.
- La remision confirmada descuenta `quantity_available` y aumenta `quantity_remitted`.

## Acciones que consumen stock

- Confirmar corte consume reservas de `raw_material_inventory`.
- Confirmar remision desde inventario terminado consume `finished_goods_inventory`.

## Acciones que solo reservan stock

- Reservar una verificacion de stock aumenta `raw_material_inventory.quantity_reserved`.
- Crear empaquetado en borrador reserva saldo aprobado de calidad para evitar doble empaquetado.

## Acciones que bloquean cancelacion o reapertura

- No se cancela produccion con consumo irreversible salvo reglas explicitas de reversa.
- No se cancela confeccion con avances registrados.
- No se cancela empaquetado que ya genero inventario terminado.
- No se cancela remision confirmada que ya consumio inventario terminado.
- Documentos comerciales siguen bloqueando anulacion/reapertura cuando alimentaron documentos posteriores.

## Backup y restore

Los scripts SQLite existentes respaldan y restauran el archivo completo de base de datos, por lo que incluyen naturalmente las tablas textiles:

- inventario de insumos;
- reservas;
- compras;
- recepciones;
- corte;
- trabajos externos;
- confeccion;
- calidad y reproceso;
- empaquetado;
- inventario terminado;
- remisiones vinculadas a inventario terminado.

No se requieren cambios en `bin/backup_sqlite.sh` ni `bin/restore_sqlite.sh` mientras el sistema use una unica base SQLite configurada en `DB_DATABASE`.

## Pendientes fuera de alcance

- SIFEN.
- Facturacion electronica real.
- Reversas avanzadas de inventario.
- Emails reales a proveedores.
- Adjuntos reales si no estan completos.
- Automatizaciones futuras.
