<?php $showTableControls = $showTableControls ?? true; ?>
<div class="section-title table-title"><h2><?= $tableTitle ?></h2><?php if ($showTableControls): ?><div class="filters">
  <select data-expense-filter="group_id"><option value="">Todos los grupos</option><?php foreach ($groups as $option): ?><option value="<?= (int) $option['id'] ?>"><?= e($option['name']) ?></option><?php endforeach; ?></select>
  <?php $expenseCategories = []; foreach ($expenses as $option) $expenseCategories[(int) $option['category_id']] = $option['category']; ?>
  <select data-expense-filter="category_id"><option value="">Todas las categorías</option><?php foreach ($expenseCategories as $id => $name): ?><option value="<?= $id ?>"><?= e($name) ?></option><?php endforeach; ?></select>
  <select data-expense-filter="status"><option value="">Todos los estados</option><option value="settled">Liquidado</option><option value="paid">Pagado</option><option value="pending">Pendiente</option></select>
</div><?php endif; ?></div>
<div class="table-panel">
  <table class="expense-table">
    <thead><tr><th>Categoría</th><th>Descripción</th><th>Grupo</th><th>Persona pagó</th><th>Fecha</th><th>Total</th><th>Te corresponde</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_slice($expenses, 0, $tableLimit ?? count($expenses)) as $expense): ?>
        <tr class="searchable" data-id="<?= (int) ($expense['id'] ?? 0) ?>" data-group="<?= e(mb_strtolower($expense['group'])) ?>" data-category="<?= e(mb_strtolower($expense['category'])) ?>" data-status="<?= e(mb_strtolower($expense['status'])) ?>" data-expense="<?= e(json_encode(['id' => $expense['id'] ?? 0, 'description' => $expense['description'], 'amount' => $expense['total'], 'date' => $expense['expense_date'] ?? '', 'group_id' => $expense['group_id'] ?? 0, 'category_id' => $expense['category_id'] ?? 0, 'payer_id' => $expense['paid_by'] ?? 0, 'split_expense' => ($expense['split_method'] ?? 'equal') === 'equal'], JSON_UNESCAPED_UNICODE)) ?>" data-search="<?= e(strtolower(implode(' ', $expense))) ?>">
          <td data-label="Categoría"><span class="category-icon small"><svg><use href="#i-<?= e($expense['icon']) ?>"/></svg></span><span class="mobile-only"><?= e($expense['category']) ?></span></td>
          <td data-label="Descripción"><strong><?= e($expense['description']) ?></strong></td><td data-label="Grupo"><?= e($expense['group']) ?></td>
          <td data-label="Pagado por"><span class="person"><i><?= e(initials($expense['person'])) ?></i><?= e($expense['person']) ?></span></td><td data-label="Fecha"><?= e($expense['date']) ?></td>
          <td data-label="Total"><?= money($expense['total']) ?></td><td data-label="Tu parte"><span class="expense-share"><strong><?= money($expense['share']) ?></strong><small><?= (int) ($expense['split_count'] ?? 0) > 1 ? 'Dividido entre ' . (int) $expense['split_count'] . ' participantes' : 'Sin dividir' ?></small></span></td>
          <td data-label="Estado"><span class="status <?= e(strtolower($expense['status'])) ?>"><?= e($expense['status']) ?></span></td>
          <td class="row-actions"><button title="Editar" class="edit-expense"><svg><use href="#i-edit"/></svg></button><button title="Eliminar" class="delete-row"><svg><use href="#i-trash"/></svg></button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-search"><span>⌕</span><strong>Sin resultados</strong><p>Prueba con otra búsqueda.</p></div>
  <?php if ($showTableControls): ?>
    <?php $expenseTotalPages = max(1, (int) ceil($summary['expense_count'] / 20)); ?>
    <nav class="api-pagination" data-api-pagination="expenses" data-page="1" data-total-pages="<?= $expenseTotalPages ?>" data-has-previous="0" data-has-next="<?= $expenseTotalPages > 1 ? '1' : '0' ?>" aria-label="Paginación de gastos">
      <button type="button" class="button pagination-button" data-page-previous disabled aria-label="Ir a la página anterior"><span aria-hidden="true">&larr;</span><span>Anterior</span></button>
      <span class="pagination-status" data-page-label aria-live="polite">Página 1 de <?= $expenseTotalPages ?></span>
      <button type="button" class="button pagination-button" data-page-next <?= $expenseTotalPages > 1 ? '' : 'disabled' ?> aria-label="Ir a la página siguiente"><span>Siguiente</span><span aria-hidden="true">&rarr;</span></button>
    </nav>
  <?php endif; ?>
</div>
