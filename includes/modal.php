<div class="modal-backdrop" id="modalBackdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-head"><div><h2 id="modalTitle">Añadir gasto</h2><p id="modalSubtitle">Registra un nuevo gasto compartido</p></div><button class="icon-button" data-close-modal><svg><use href="#i-x"/></svg></button></div>

    <form id="expenseForm" class="modal-form">
      <input type="hidden" name="expense_id" value="">
      <label class="full-width"><span>Descripción</span><input name="description" required maxlength="180" placeholder="Ej. Compra del supermercado"></label>
      <label><span>Importe</span><div class="input-suffix"><input name="amount" type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" required placeholder="0,00" title="Usa hasta dos decimales, con coma o punto"><b>€</b></div></label>
      <label><span>Fecha</span><input name="date" type="date" required value="<?= date('Y-m-d') ?>"></label>
      <label><span>Grupo</span><select name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int) $group['id'] ?>"><?= e($group['name']) ?></option><?php endforeach; ?></select></label>
      <label><span>Categoría</span><select name="category_id"><option value="1">Compras</option><option value="2">Alimentación</option><option value="3">Restaurante</option><option value="4">Transporte</option><option value="5">Servicios</option><option value="8">Otros</option></select></label>
      <label class="full-width"><span>Pagado por</span><select name="payer_id" required></select></label>
      <div class="split-preview full-width"><span data-split-label>Dividir a partes iguales</span><strong class="split-member-count">Participantes del grupo</strong><label class="switch"><input type="checkbox" name="split_expense" value="1" checked aria-label="Dividir el gasto entre los integrantes"><span></span></label></div>
      <div class="modal-actions full-width"><button type="button" class="button button-outline" data-close-modal>Cancelar</button><button class="button button-primary">Guardar gasto</button></div>
    </form>

    <form id="groupForm" class="modal-form hidden">
      <label class="full-width"><span>Nombre del grupo</span><input name="name" required maxlength="120" placeholder="Ej. Vacaciones de verano"></label>
      <label><span>Presupuesto</span><div class="input-suffix"><input name="budget" type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" required placeholder="0,00" title="Usa hasta dos decimales, con coma o punto"><b>€</b></div></label>
      <label><span>Descripción</span><input name="description" maxlength="500" placeholder="¿Para qué usaréis este grupo?"></label>
      <div class="participant-picker full-width" data-participant-picker>
        <label><span>Añadir participantes existentes</span><input type="search" name="participant_query" data-user-search autocomplete="off" placeholder="Correo o nombre completo exacto" aria-label="Buscar participante registrado"></label>
        <input type="hidden" name="participant_ids" data-participant-ids>
        <div class="user-search-results" data-user-results role="status" aria-live="polite"></div>
        <div class="selected-participants" data-selected-users aria-live="polite"></div>
        <small>Escribe el correo o el nombre completo. Solo se confirma una coincidencia exacta.</small>
      </div>
      <div class="modal-actions full-width"><button type="button" class="button button-outline" data-close-modal>Cancelar</button><button class="button button-primary">Crear grupo</button></div>
    </form>

    <form id="memberForm" class="modal-form hidden">
      <input type="hidden" name="group_id">
      <div class="participant-picker full-width" data-participant-picker>
        <label><span>Buscar usuario registrado</span><input type="search" name="participant_query" data-user-search autocomplete="off" placeholder="Correo o nombre completo exacto" aria-label="Buscar participante registrado"></label>
        <input type="hidden" name="participant_ids" data-participant-ids>
        <div class="user-search-results" data-user-results role="status" aria-live="polite"></div>
        <div class="selected-participants" data-selected-users aria-live="polite"></div>
        <small>Solo se confirma la persona exacta si existe y todavía no pertenece al grupo.</small>
      </div>
      <div class="modal-actions full-width"><button type="button" class="button button-outline" data-close-modal>Cancelar</button><button class="button button-primary">Añadir participantes</button></div>
    </form>

    <section id="groupDetailPanel" class="group-detail-panel hidden" aria-live="polite">
      <div class="group-member-list" data-group-member-list></div>
      <button type="button" class="button button-outline" data-group-members-more hidden>Cargar más</button>
      <div class="modal-actions"><button type="button" class="button button-outline" data-close-modal>Cerrar</button></div>
    </section>
  </div>
</div>

<div class="confirmation-backdrop" id="confirmationBackdrop" aria-hidden="true">
  <div class="confirmation-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirmationTitle" aria-describedby="confirmationMessage">
    <div class="confirmation-icon" aria-hidden="true"><svg><use href="#i-trash"/></svg></div>
    <h2 id="confirmationTitle">Eliminar grupo</h2>
    <p id="confirmationMessage"></p>
    <div class="confirmation-actions">
      <button type="button" class="button button-outline" data-confirm-cancel>Cancelar</button>
      <button type="button" class="button confirmation-danger" data-confirm-accept>Eliminar grupo</button>
    </div>
  </div>
</div>
