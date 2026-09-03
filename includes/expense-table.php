<div class="section-title table-title"><h2><?= $tableTitle ?></h2><div class="filters">
  <select data-expense-filter="group"><option value="">Todos los grupos</option><?php foreach (array_unique(array_column($expenses, 'group')) as $option): ?><option value="<?= e(mb_strtolower($option)) ?>"><?= e($option) ?></option><?php endforeach; ?></select>
  <select data-expense-filter="category"><option value="">Todas las categorías</option><?php foreach (array_unique(array_column($expenses, 'category')) as $option): ?><option value="<?= e(mb_strtolower($option)) ?>"><?= e($option) ?></option><?php endforeach; ?></select>
  <select data-expense-filter="status"><option value="">Todos los estados</option><?php foreach (array_unique(array_column($expenses, 'status')) as $option): ?><option value="<?= e(mb_strtolower($option)) ?>"><?= e($option) ?></option><?php endforeach; ?></select>
</div></div>
<div class="table-panel">
  <table class="expense-table">
    <thead><tr><th>Categoría</th><th>Descripción</th><th>Grupo</th><th>Persona pagó</th><th>Fecha</th><th>Total</th><th>Te corresponde</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_slice($expenses, 0, $tableLimit ?? count($expenses)) as $expense): ?>
        <tr class="searchable" data-id="<?= (int) ($expense['id'] ?? 0) ?>" data-group="<?= e(mb_strtolower($expense['group'])) ?>" data-category="<?= e(mb_strtolower($expense['category'])) ?>" data-status="<?= e(mb_strtolower($expense['status'])) ?>" data-expense="<?= e(json_encode(['id' => $expense['id'] ?? 0, 'description' => $expense['description'], 'amount' => $expense['total'], 'date' => $expense['expense_date'] ?? '', 'group_id' => $expense['group_id'] ?? 0, 'category_id' => $expense['category_id'] ?? 0, 'payer_id' => $expense['paid_by'] ?? 0, 'split_expense' => ($expense['split_method'] ?? 'equal') === 'equal'], JSON_UNESCAPED_UNICODE)) ?>" data-search="<?= e(strtolower(implode(' ', $expense))) ?>">
          <td data-label="Categoría"><span class="category-icon small"><svg><use href="#i-<?= e($expense['icon']) ?>"/></svg></span><span class="mobile-only"><?= e($expense['category']) ?></span></td>
          <td data-label="Descripción"><strong><?= e($expense['description']) ?></strong></td><td data-label="Grupo"><?= e($expense['group']) ?></td>
          <td data-label="Pagado por"><span class="person"><i><?= e(initials($expense['person'])) ?></i><?= e($expense['person']) ?></span></td><td data-label="Fecha"><?= e($expense['date']) ?></td>
          <td data-label="Total"><?= money($expense['total']) ?></td><td data-label="Tu parte"><?= money($expense['share']) ?></td>
          <td data-label="Estado"><span class="status <?= e(strtolower($expense['status'])) ?>"><?= e($expense['status']) ?></span></td>
          <td class="row-actions"><button title="Editar" class="edit-expense"><svg><use href="#i-edit"/></svg></button><button title="Eliminar" class="delete-row"><svg><use href="#i-trash"/></svg></button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-search"><span>⌕</span><strong>Sin resultados</strong><p>Prueba con otra búsqueda.</p></div>
</div>
