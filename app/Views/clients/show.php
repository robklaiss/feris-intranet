<section class="page-head">
    <div>
        <p class="eyebrow">Cliente</p>
        <h1><?= e($client['name']) ?></h1>
        <p class="muted">RUC: <?= e($client['tax_id'] ?: 'Sin definir') ?></p>
    </div>
    <?php if (can('clients.manage')): ?>
        <div class="actions-row">
            <a href="/clients/<?= e((string) $client['id']) ?>/edit" class="button">Editar</a>
            <form method="post" action="/clients/<?= e((string) $client['id']) ?>/delete" data-confirm="Eliminar cliente">
                <?= csrf_field() ?>
                <button type="submit" class="button button--danger">Eliminar</button>
            </form>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel__header">
        <h2>Dependencias</h2>
        <span class="muted">Sedes, áreas o unidades operativas del cliente</span>
    </div>
    <div class="table-wrap">
        <table class="table table--form">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Ciudad</th>
                <th>Contacto operativo</th>
                <th>Recepción</th>
                <th>Facturación</th>
                <th>Notas</th>
                <?php if (can('clients.manage')): ?><th></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($dependencies as $dependency): ?>
                <tr>
                    <?php if (can('clients.manage')): ?>
                        <form method="post" action="/clients/<?= e((string) $client['id']) ?>/dependencies/<?= e((string) $dependency['id']) ?>/update">
                            <?= csrf_field() ?>
                            <td>
                                <input type="text" name="name" value="<?= e($dependency['name']) ?>" required>
                                <input type="text" name="address" value="<?= e($dependency['address'] ?? '') ?>" placeholder="Dirección">
                                <input type="text" name="phone" value="<?= e($dependency['phone'] ?? '') ?>" placeholder="Teléfono">
                                <input type="email" name="email" value="<?= e($dependency['email'] ?? '') ?>" placeholder="Email">
                            </td>
                            <td><input type="text" name="city" value="<?= e($dependency['city'] ?? '') ?>"></td>
                            <td>
                                <input type="text" name="operational_contact_name" value="<?= e($dependency['operational_contact_name'] ?? '') ?>" placeholder="Nombre">
                                <input type="text" name="operational_contact_phone" value="<?= e($dependency['operational_contact_phone'] ?? '') ?>" placeholder="Teléfono">
                            </td>
                            <td>
                                <input type="text" name="reception_contact_name" value="<?= e($dependency['reception_contact_name'] ?? '') ?>" placeholder="Nombre">
                                <input type="text" name="reception_contact_phone" value="<?= e($dependency['reception_contact_phone'] ?? '') ?>" placeholder="Teléfono">
                            </td>
                            <td>
                                <select name="billing_contact_id">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($billingContacts as $contact): ?>
                                        <option value="<?= e((string) $contact['id']) ?>" <?= (string) ($dependency['billing_contact_id'] ?? '') === (string) $contact['id'] ? 'selected' : '' ?>>
                                            <?= e($contact['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><textarea name="notes" rows="3"><?= e($dependency['notes'] ?? '') ?></textarea></td>
                            <td><button type="submit" class="button button--secondary">Guardar</button></td>
                        </form>
                    <?php else: ?>
                        <td><strong><?= e($dependency['name']) ?></strong><br><span class="muted"><?= e($dependency['address'] ?? '') ?></span></td>
                        <td><?= e($dependency['city'] ?? '') ?></td>
                        <td><?= e($dependency['operational_contact_name'] ?? '') ?><br><span class="muted"><?= e($dependency['operational_contact_phone'] ?? '') ?></span></td>
                        <td><?= e($dependency['reception_contact_name'] ?? '') ?><br><span class="muted"><?= e($dependency['reception_contact_phone'] ?? '') ?></span></td>
                        <td><?= e($dependency['billing_contact_name'] ?? '') ?></td>
                        <td><?= nl2br(e($dependency['notes'] ?? '')) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($dependencies === []): ?>
                <tr><td colspan="<?= can('clients.manage') ? '7' : '6' ?>" class="empty">Sin dependencias cargadas.</td></tr>
            <?php endif; ?>
            <?php if (can('clients.manage')): ?>
                <tr>
                    <form method="post" action="/clients/<?= e((string) $client['id']) ?>/dependencies">
                        <?= csrf_field() ?>
                        <td>
                            <input type="text" name="name" placeholder="Nueva dependencia" required>
                            <input type="text" name="address" placeholder="Dirección">
                            <input type="text" name="phone" placeholder="Teléfono">
                            <input type="email" name="email" placeholder="Email">
                        </td>
                        <td><input type="text" name="city" placeholder="Ciudad"></td>
                        <td>
                            <input type="text" name="operational_contact_name" placeholder="Nombre">
                            <input type="text" name="operational_contact_phone" placeholder="Teléfono">
                        </td>
                        <td>
                            <input type="text" name="reception_contact_name" placeholder="Nombre">
                            <input type="text" name="reception_contact_phone" placeholder="Teléfono">
                        </td>
                        <td>
                            <select name="billing_contact_id">
                                <option value="">Sin asignar</option>
                                <?php foreach ($billingContacts as $contact): ?>
                                    <option value="<?= e((string) $contact['id']) ?>"><?= e($contact['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><textarea name="notes" rows="3" placeholder="Notas"></textarea></td>
                        <td><button type="submit" class="button">Crear</button></td>
                    </form>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <h2>Contactos de facturación</h2>
        <span class="muted">Datos fiscales por cliente o dependencia</span>
    </div>
    <div class="table-wrap">
        <table class="table table--form">
            <thead>
            <tr>
                <th>Contacto</th>
                <th>Dependencia</th>
                <th>Datos fiscales</th>
                <th>Dirección</th>
                <th>Default</th>
                <th>Notas</th>
                <?php if (can('clients.manage')): ?><th></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($billingContacts as $contact): ?>
                <tr>
                    <?php if (can('clients.manage')): ?>
                        <form method="post" action="/clients/<?= e((string) $client['id']) ?>/billing-contacts/<?= e((string) $contact['id']) ?>/update">
                            <?= csrf_field() ?>
                            <td>
                                <input type="text" name="name" value="<?= e($contact['name']) ?>" required>
                                <input type="text" name="role" value="<?= e($contact['role'] ?? '') ?>" placeholder="Rol">
                                <input type="text" name="phone" value="<?= e($contact['phone'] ?? '') ?>" placeholder="Teléfono">
                                <input type="email" name="email" value="<?= e($contact['email'] ?? '') ?>" placeholder="Email">
                            </td>
                            <td>
                                <select name="dependency_id">
                                    <option value="">Cliente general</option>
                                    <?php foreach ($dependencies as $dependency): ?>
                                        <option value="<?= e((string) $dependency['id']) ?>" <?= (string) ($contact['dependency_id'] ?? '') === (string) $dependency['id'] ? 'selected' : '' ?>>
                                            <?= e($dependency['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="business_name" value="<?= e($contact['business_name'] ?? '') ?>" placeholder="Razón social">
                                <input type="text" name="ruc" value="<?= e($contact['ruc'] ?? '') ?>" placeholder="RUC">
                            </td>
                            <td><textarea name="address" rows="3"><?= e($contact['address'] ?? '') ?></textarea></td>
                            <td><label class="checkbox"><input type="checkbox" name="is_default" value="1" <?= !empty($contact['is_default']) ? 'checked' : '' ?>><span>Default</span></label></td>
                            <td><textarea name="notes" rows="3"><?= e($contact['notes'] ?? '') ?></textarea></td>
                            <td><button type="submit" class="button button--secondary">Guardar</button></td>
                        </form>
                    <?php else: ?>
                        <td><strong><?= e($contact['name']) ?></strong><br><span class="muted"><?= e($contact['email'] ?? '') ?></span></td>
                        <td><?= e($contact['dependency_name'] ?? 'Cliente general') ?></td>
                        <td><?= e($contact['business_name'] ?? '') ?><br><span class="muted"><?= e($contact['ruc'] ?? '') ?></span></td>
                        <td><?= nl2br(e($contact['address'] ?? '')) ?></td>
                        <td><?= !empty($contact['is_default']) ? 'Sí' : 'No' ?></td>
                        <td><?= nl2br(e($contact['notes'] ?? '')) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($billingContacts === []): ?>
                <tr><td colspan="<?= can('clients.manage') ? '7' : '6' ?>" class="empty">Sin contactos de facturación cargados.</td></tr>
            <?php endif; ?>
            <?php if (can('clients.manage')): ?>
                <tr>
                    <form method="post" action="/clients/<?= e((string) $client['id']) ?>/billing-contacts">
                        <?= csrf_field() ?>
                        <td>
                            <input type="text" name="name" placeholder="Nuevo contacto" required>
                            <input type="text" name="role" placeholder="Rol">
                            <input type="text" name="phone" placeholder="Teléfono">
                            <input type="email" name="email" placeholder="Email">
                        </td>
                        <td>
                            <select name="dependency_id">
                                <option value="">Cliente general</option>
                                <?php foreach ($dependencies as $dependency): ?>
                                    <option value="<?= e((string) $dependency['id']) ?>"><?= e($dependency['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="business_name" placeholder="Razón social">
                            <input type="text" name="ruc" placeholder="RUC">
                        </td>
                        <td><textarea name="address" rows="3" placeholder="Dirección fiscal"></textarea></td>
                        <td><label class="checkbox"><input type="checkbox" name="is_default" value="1"><span>Default</span></label></td>
                        <td><textarea name="notes" rows="3" placeholder="Notas"></textarea></td>
                        <td><button type="submit" class="button">Crear</button></td>
                    </form>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Estado</dt><dd><?= e($client['status']) ?></dd></div>
            <div><dt>Direcciones</dt><dd><?= nl2br(e($client['addresses'])) ?></dd></div>
            <div><dt>Contactos</dt><dd><?= nl2br(e($client['contacts'])) ?></dd></div>
        </dl>
    </article>

    <article class="panel">
        <div class="panel__header">
            <h2>Contratos asociados</h2>
            <?php if (can('documents.create')): ?>
                <a href="/contracts/create" class="link-arrow">Nuevo contrato</a>
            <?php endif; ?>
        </div>
        <div class="list-stack">
            <?php foreach ($contracts as $contract): ?>
                <a class="list-item" href="/contracts/<?= e((string) $contract['id']) ?>">
                    <strong><?= e($contract['contract_number']) ?></strong>
                    <span><?= e($contract['date']) ?> · <?= e($contract['status']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($contracts === []): ?>
                <p class="empty">Sin contratos asociados.</p>
            <?php endif; ?>
        </div>
    </article>
</section>
