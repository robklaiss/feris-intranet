<section class="auth-shell">
    <article class="auth-card">
        <p class="eyebrow">Acceso interno</p>
        <h1>Industria Feris CRM</h1>
        <p class="muted">Base operativa con control por roles y auditoría documental.</p>

        <form method="post" action="/login" class="form-stack auth-form">
            <?= csrf_field() ?>
            <label>
                <span>Usuario</span>
                <input type="text" name="username" autocomplete="username" required>
            </label>
            <label>
                <span>Contraseña</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="button">Ingresar</button>
        </form>

        <div class="auth-help">
            <strong>Usuarios demo</strong>
            <span><code>admin</code> / <code>admin123</code></span>
            <span><code>operador</code> / <code>operador123</code></span>
            <span><code>consulta</code> / <code>consulta123</code></span>
        </div>
    </article>
</section>
