<section class="app-view" id="view-settings" data-title="Configuración" data-subtitle="Administra tu cuenta y tus preferencias">
  <div class="settings-layout">
    <nav class="settings-nav" aria-label="Apartados de configuración">
      <button type="button" class="active" data-settings-target="profileSettings">Perfil</button>
    </nav>
    <form class="panel settings-form" id="settingsForm">
      <div class="form-heading" id="profileSettings"><div><h2>Información personal</h2><p>Actualiza los datos visibles de tu cuenta.</p></div><div class="profile-photo"><?= e(initials($currentUser['first_name'])) ?></div></div>
      <div class="form-grid">
        <label><span>Nombre</span><input name="first_name" maxlength="80" required value="<?= e($currentUser['first_name']) ?>"></label>
        <label><span>Apellidos</span><input name="last_name" maxlength="120" value="<?= e($currentUser['last_name'] ?? '') ?>"></label>
        <label class="full-width"><span>Correo electrónico</span><input name="email" type="email" required value="<?= e($currentUser['email']) ?>"></label>
        <label id="preferenceSettings"><span>Moneda</span><select name="currency"><option value="EUR" <?= $currentUser['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option><option value="USD" <?= $currentUser['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option></select></label>
        <label><span>Idioma</span><select name="locale"><option value="es-ES" <?= $currentUser['locale'] === 'es-ES' ? 'selected' : '' ?>>Español</option><option value="en-US" <?= $currentUser['locale'] === 'en-US' ? 'selected' : '' ?>>English</option></select></label>
      </div>
      <div id="notificationSettings" class="form-section"><div><strong>Nuevos gastos</strong><p>Recibe avisos cuando se añadan gastos.</p></div><label class="switch"><input name="notify_new_expense" type="checkbox" <?= $currentUser['notify_new_expense'] ? 'checked' : '' ?>><span></span></label></div>
      <div class="form-section"><div><strong>Pagos recibidos</strong><p>Te avisaremos al registrar una liquidación.</p></div><label class="switch"><input name="notify_payment" type="checkbox" <?= $currentUser['notify_payment'] ? 'checked' : '' ?>><span></span></label></div>
      <div class="form-section"><div><strong>Recordatorios de pago</strong><p>Recibe recordatorios sobre saldos pendientes.</p></div><label class="switch"><input name="notify_payment_reminder" type="checkbox" <?= $currentUser['notify_payment_reminder'] ? 'checked' : '' ?>><span></span></label></div>
      <div class="form-section"><div><strong>Actualizaciones de grupos</strong><p>Recibe avisos sobre invitaciones aceptadas o rechazadas.</p></div><label class="switch"><input name="notify_group_updates" type="checkbox" <?= $currentUser['notify_group_updates'] ? 'checked' : '' ?>><span></span></label></div>
      <section class="shortcut-settings" aria-labelledby="shortcutSettingsTitle">
        <div class="shortcut-settings-copy"><span class="shortcut-badge">Atajos de iOS</span><h3 id="shortcutSettingsTitle">Añade gastos con Siri</h3><p>Crea un token privado para el Atajo. Al generar uno nuevo, el anterior deja de funcionar.</p></div>
        <div class="shortcut-token-actions">
          <button class="button button-outline" type="button" id="revokeShortcutToken">Revocar</button>
          <button class="button button-primary" type="button" id="generateShortcutToken">Generar token</button>
        </div>
        <div class="shortcut-token-result hidden" id="shortcutTokenResult" aria-live="polite">
          <label><span>Token (se muestra una sola vez)</span><input id="shortcutTokenValue" type="text" readonly></label>
          <button class="button button-outline" type="button" id="copyShortcutToken">Copiar</button>
          <small>Pégalo en el Atajo la primera vez. iOS lo conservará para las siguientes ejecuciones.</small>
        </div>
      </section>
      <div class="form-actions"><button type="reset" class="button button-outline" data-settings-reset>Cancelar</button><button class="button button-primary" type="submit">Guardar cambios</button></div>
    </form>
  </div>
</section>
