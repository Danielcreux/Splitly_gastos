/**
 * Controlador ligero de la interfaz.
 * Mantiene navegación, modales y filtros desacoplados del HTML generado por PHP.
 */
(() => {
  'use strict';

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const sidebar = $('#sidebar');
  const overlay = $('#sidebarOverlay');
  const modal = $('#modalBackdrop');
  const confirmation = $('#confirmationBackdrop');
  const toast = $('#toast');
  const participantPickers = new WeakMap();
  let resolveConfirmation = null;
  let confirmationTrigger = null;

  function syncBodyScrollLock() {
    const locked = sidebar?.classList.contains('open') || modal?.classList.contains('open') || confirmation?.classList.contains('open');
    document.body.style.overflow = locked ? 'hidden' : '';
  }

  function requestConfirmation({ title, message, confirmLabel = 'Confirmar' }, trigger = null) {
    if (!confirmation) return Promise.resolve(false);
    $('#confirmationTitle').textContent = title;
    $('#confirmationMessage').textContent = message;
    $('[data-confirm-accept]', confirmation).textContent = confirmLabel;
    confirmationTrigger = trigger;
    confirmation.classList.add('open');
    confirmation.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();
    setTimeout(() => $('[data-confirm-cancel]', confirmation)?.focus(), 80);
    return new Promise(resolve => { resolveConfirmation = resolve; });
  }

  function closeConfirmation(confirmed = false) {
    if (!confirmation?.classList.contains('open')) return;
    confirmation.classList.remove('open');
    confirmation.setAttribute('aria-hidden', 'true');
    syncBodyScrollLock();
    const resolve = resolveConfirmation;
    resolveConfirmation = null;
    resolve?.(confirmed);
    confirmationTrigger?.focus();
    confirmationTrigger = null;
  }

  function closeNotifications() {
    $('#notificationPanel')?.classList.remove('open');
    $('#notificationToggle')?.setAttribute('aria-expanded', 'false');
  }

  function updatePayerOptions(groupId, selectedId = null) {
    const select = $('#expenseForm [name="payer_id"]');
    if (!select) return;
    const members = window.SplitlyData?.groupMembers?.[String(groupId)] || [];
    select.replaceChildren(...members.map(member => {
      const option = document.createElement('option');
      option.value = member.id;
      option.textContent = `${member.name}${Number(member.id) === Number(window.SplitlyData.currentUserId) ? ' (tú)' : ''}`;
      return option;
    }));
    if (selectedId !== null) select.value = String(selectedId);
    updateSplitPreview();
  }

  function updateSplitPreview() {
    const form = $('#expenseForm');
    if (!form) return;
    const enabled = form.elements.split_expense.checked;
    const groupId = form.elements.group_id.value;
    const members = window.SplitlyData?.groupMembers?.[String(groupId)] || [];
    $('[data-split-label]', form).textContent = enabled ? 'Dividir a partes iguales' : 'No dividir este gasto';
    $('.split-member-count', form).textContent = enabled
      ? `${members.length} ${members.length === 1 ? 'participante' : 'participantes'}`
      : 'Solo se asignará al pagador';
  }

  function navigate(viewName, updateHash = true) {
    const view = $(`#view-${viewName}`);
    if (!view) return;

    $$('.app-view').forEach(item => item.classList.toggle('active', item === view));
    $$('.nav-item').forEach(item => item.classList.toggle('active', item.dataset.view === viewName));
    $$('.mobile-tab[data-view]').forEach(item => item.classList.toggle('active', item.dataset.view === viewName));
    $('#mobileMoreToggle')?.classList.toggle('active', ['activity', 'stats', 'settings'].includes(viewName));
    $('#pageTitle').textContent = view.dataset.title;
    $('#pageSubtitle').textContent = view.dataset.subtitle;
    document.title = `${view.dataset.title.replace(' 👋', '')} · Splitly`;
    if (updateHash) history.replaceState(null, '', `#${viewName}`);
    closeSidebar();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    filterVisibleView($('#globalSearch').value);
  }

  function openSidebar() {
    closeNotifications();
    sidebar.classList.add('open');
    overlay.classList.add('open');
    $('#menuToggle')?.setAttribute('aria-expanded', 'true');
    $('#mobileMoreToggle')?.setAttribute('aria-expanded', 'true');
    syncBodyScrollLock();
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    $('#menuToggle')?.setAttribute('aria-expanded', 'false');
    $('#mobileMoreToggle')?.setAttribute('aria-expanded', 'false');
    syncBodyScrollLock();
  }

  function openModal(type, record = null) {
    const isGroup = type === 'group';
    const isMembers = type === 'members';
    const activeForm = isGroup ? $('#groupForm') : isMembers ? $('#memberForm') : $('#expenseForm');
    activeForm.reset();
    resetParticipantPicker(activeForm);
    $('#expenseForm').classList.toggle('hidden', isGroup || isMembers);
    $('#groupForm').classList.toggle('hidden', !isGroup);
    $('#memberForm').classList.toggle('hidden', !isMembers);
    $('#groupDetailPanel').classList.add('hidden');
    $('#modalTitle').textContent = isGroup ? 'Crear nuevo grupo' : isMembers ? 'Añadir personas' : record ? 'Editar gasto' : 'Añadir gasto';
    $('#modalSubtitle').textContent = isGroup
      ? 'Organiza un nuevo plan con tus amigos'
      : isMembers
        ? `Busca personas para ${record?.groupName || 'el grupo'}`
        : record ? 'Actualiza los datos de este gasto' : 'Registra un nuevo gasto compartido';
    if (isMembers) {
      activeForm.elements.group_id.value = record?.groupId || '';
    } else if (!isGroup) {
      activeForm.dataset.endpoint = record ? 'api/expenses/update.php' : 'api/expenses/create.php';
      activeForm.elements.expense_id.value = record?.id || '';
      const groupId = record?.group_id || activeForm.elements.group_id.value;
      updatePayerOptions(groupId, record?.payer_id || window.SplitlyData.currentUserId);
      if (record) {
        ['description', 'amount', 'date', 'group_id', 'category_id'].forEach(name => {
          if (activeForm.elements[name]) activeForm.elements[name].value = record[name] ?? '';
        });
        activeForm.elements.split_expense.checked = record.split_expense !== false;
      }
      updateSplitPreview();
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();
    setTimeout(() => $('input:not([type="hidden"])', activeForm)?.focus(), 120);
  }

  async function loadGroupMembers(groupId, page, append = false) {
    const panel = $('#groupDetailPanel');
    const list = $('[data-group-member-list]', panel);
    const more = $('[data-group-members-more]', panel);
    more.disabled = true;
    try {
      const response = await fetch(`api/groups/members.php?group_id=${encodeURIComponent(groupId)}&page=${page}&per_page=20`);
      const result = await response.json();
      if (!response.ok || !Array.isArray(result.data)) throw new Error(result.message || 'No se pudieron cargar los integrantes.');
      const roles = { owner: 'Propietario', admin: 'Administrador', member: 'Integrante' };
      const rows = result.data.map(member => {
        const row = document.createElement('div');
        row.className = 'group-member-row';
        const avatar = document.createElement('i');
        avatar.textContent = member.name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase('es');
        const copy = document.createElement('span');
        const name = document.createElement('strong');
        const role = document.createElement('small');
        name.textContent = member.name;
        role.textContent = roles[member.role] || 'Integrante';
        copy.append(name, role);
        row.append(avatar, copy);
        return row;
      });
      if (append) list.append(...rows); else list.replaceChildren(...rows);
      $('#modalSubtitle').textContent = `${result.pagination.total} ${result.pagination.total === 1 ? 'integrante' : 'integrantes'}`;
      more.dataset.groupId = String(groupId);
      more.dataset.page = String(result.pagination.page);
      more.hidden = !result.pagination.has_next;
    } catch (error) {
      if (!append) list.replaceChildren(textElement('p', error.message || 'Error de red.'));
      notify(error.message || 'No se pudieron cargar los integrantes.');
    } finally { more.disabled = false; }
  }

  async function openGroupDetails(groupId, groupName) {
    $('#expenseForm').classList.add('hidden');
    $('#groupForm').classList.add('hidden');
    $('#memberForm').classList.add('hidden');
    const panel = $('#groupDetailPanel');
    panel.classList.remove('hidden');
    $('#modalTitle').textContent = groupName;
    $('#modalSubtitle').textContent = 'Cargando integrantes…';
    const list = $('[data-group-member-list]', panel);
    list.replaceChildren();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();
    await loadGroupMembers(groupId, 1);
  }

  function notificationElement(item) {
    const invitation = item.type === 'group_invitation' && /^#group-invitation:(\d+)$/.exec(item.actionUrl || '');
    const element = document.createElement(invitation ? 'div' : 'button');
    if (!invitation) element.type = 'button';
    element.className = `notification-item ${invitation ? 'group-invitation ' : ''}${item.isRead ? '' : 'unread'}`.trim();
    if (!invitation) element.dataset.view = (item.actionUrl || '').includes('balance') ? 'balances' : (item.actionUrl || '').includes('group') ? 'groups' : 'expenses';
    const dot = document.createElement('i');
    const copy = document.createElement('span'); copy.append(textElement('strong', item.title), textElement('small', item.message));
    element.append(dot, copy);
    if (invitation) {
      const actions = document.createElement('div'); actions.className = 'invitation-actions';
      for (const [action, label] of [['accept', 'Aceptar'], ['decline', 'Rechazar']]) {
        const button = document.createElement('button'); button.type = 'button'; button.dataset.invitationAction = action; button.dataset.groupId = invitation[1]; button.textContent = label; actions.append(button);
      }
      element.append(actions);
    }
    return element;
  }

  async function loadMoreNotifications(button) {
    if (button.disabled) return;
    button.disabled = true;
    try {
      const page = Number(button.dataset.page || 1) + 1;
      const response = await fetch(`api/notifications/index.php?page=${page}&per_page=8`);
      const result = await response.json();
      if (!response.ok || !Array.isArray(result.data)) throw new Error(result.message || 'No se pudieron cargar las notificaciones.');
      $('[data-notification-list]').append(...result.data.map(notificationElement));
      button.dataset.page = String(result.pagination.page);
      button.hidden = !result.pagination.has_next;
    } catch (error) { notify(error.message || 'No se pudieron cargar las notificaciones.'); }
    finally { button.disabled = false; }
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    syncBodyScrollLock();
  }

  function notify(message) {
    $('span', toast).textContent = message;
    toast.classList.add('show');
    clearTimeout(notify.timer);
    notify.timer = setTimeout(() => toast.classList.remove('show'), 2600);
  }

  function filterVisibleView(value) {
    const term = value.trim().toLocaleLowerCase('es');
    const activeView = $('.app-view.active');
    if (!activeView) return;
    $$('.searchable', activeView).forEach(item => {
      const haystack = (item.dataset.search || item.textContent).toLocaleLowerCase('es');
      item.classList.toggle('search-hidden', term && !haystack.includes(term));
    });
  }

  const paginationRequests = new WeakMap();
  const euro = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });

  function textElement(tag, text, className = '') {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text ?? '';
    return element;
  }

  function renderExpensePage(items) {
    const body = $('#view-expenses .expense-table tbody');
    if (!body) return;
    body.replaceChildren(...items.map(expense => {
      const row = document.createElement('tr');
      row.className = 'searchable';
      row.dataset.id = String(expense.id);
      row.dataset.expense = JSON.stringify({
        id: expense.id, description: expense.concept, amount: expense.amount,
        date: String(expense.date).slice(0, 10), group_id: expense.groupId,
        category_id: expense.categoryId, payer_id: expense.paidById,
        split_expense: expense.splitMethod === 'equal'
      });
      const category = document.createElement('td'); category.dataset.label = 'Categoría'; category.append(textElement('span', expense.categoryName));
      const description = document.createElement('td'); description.dataset.label = 'Descripción'; description.append(textElement('strong', expense.concept));
      const group = textElement('td', expense.groupName); group.dataset.label = 'Grupo';
      const payer = textElement('td', expense.paidByName); payer.dataset.label = 'Pagado por';
      const date = textElement('td', new Date(expense.date).toLocaleDateString('es-ES')); date.dataset.label = 'Fecha';
      const total = textElement('td', euro.format(Number(expense.amount))); total.dataset.label = 'Total';
      const share = textElement('td', euro.format(Number(expense.yourShare))); share.dataset.label = 'Tu parte';
      const status = document.createElement('td'); status.dataset.label = 'Estado'; status.append(textElement('span', expense.status || 'Pendiente', `status ${(expense.status || 'Pendiente').toLocaleLowerCase('es')}`));
      const actions = document.createElement('td'); actions.className = 'row-actions';
      const edit = document.createElement('button'); edit.type = 'button'; edit.title = 'Editar'; edit.className = 'edit-expense'; edit.innerHTML = '<svg><use href="#i-edit"/></svg>';
      const remove = document.createElement('button'); remove.type = 'button'; remove.title = 'Eliminar'; remove.className = 'delete-row'; remove.innerHTML = '<svg><use href="#i-trash"/></svg>';
      actions.append(edit, remove); row.append(category, description, group, payer, date, total, share, status, actions); return row;
    }));
  }

  function renderActivityPage(items) {
    const list = $('[data-activity-list]');
    if (!list) return;
    list.replaceChildren(...items.map(item => {
      const row = document.createElement('div'); row.className = 'timeline-row searchable'; row.dataset.activityType = item.type;
      const icon = document.createElement('span'); icon.className = 'timeline-icon'; icon.innerHTML = `<svg><use href="#i-${item.type === 'payment' ? 'check' : item.type === 'group' ? 'users' : 'receipt'}"/></svg>`;
      const copy = document.createElement('div'); copy.append(textElement('strong', item.title), textElement('p', item.meta));
      const amount = textElement('strong', item.amount == null ? '' : euro.format(Number(item.amount)), Number(item.amount) >= 0 ? 'success' : 'danger');
      const time = textElement('time', new Date(item.createdAt).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }));
      row.append(icon, copy, amount, time); return row;
    }));
  }

  function renderGroupPage(items) {
    const list = $('[data-group-list]');
    if (!list) return;
    list.replaceChildren(...items.map((group, index) => {
      const card = document.createElement('article'); card.className = 'group-card large searchable'; card.dataset.groupDetails = String(group.id); card.dataset.groupName = group.name; card.tabIndex = 0; card.setAttribute('role', 'button');
      const cover = document.createElement('div'); cover.className = `group-cover cover-${index % 4 + 1}`; cover.append(textElement('span', (group.name || 'G').slice(0, 1).toUpperCase(), 'category-icon mint'));
      const body = document.createElement('div'); body.className = 'group-body';
      const head = document.createElement('div'); head.className = 'group-card-head'; const heading = document.createElement('div'); heading.append(textElement('h3', group.name), textElement('p', `${group.memberCount} participantes`)); head.append(heading);
      const metrics = document.createElement('div'); metrics.className = 'group-metrics'; const spent = document.createElement('div'); spent.append(textElement('span', 'Gasto total'), textElement('strong', euro.format(Number(group.totalSpent)))); const budget = document.createElement('div'); budget.append(textElement('span', 'Presupuesto'), textElement('strong', euro.format(Number(group.budget || 0)))); metrics.append(spent, budget);
      const footer = document.createElement('div'); footer.className = 'group-footer-actions'; const detail = document.createElement('button'); detail.type = 'button'; detail.className = 'text-button group-detail'; detail.dataset.group = group.name; detail.textContent = 'Ver gastos →'; footer.append(detail);
      body.append(head, metrics, footer); card.append(cover, body); return card;
    }));
  }

  async function loadPagedList(kind, page) {
    const navigation = $(`[data-api-pagination="${kind}"]`);
    if (!navigation) return;
    paginationRequests.get(navigation)?.abort();
    const controller = new AbortController(); paginationRequests.set(navigation, controller);
    const params = new URLSearchParams({ page: String(Math.max(1, page)), per_page: '20' });
    const search = $('#globalSearch')?.value.trim(); if (search) params.set('search', search);
    let endpoint;
    if (kind === 'expenses') {
      $$('[data-expense-filter]', $('#view-expenses')).forEach(select => { if (select.value) params.set(select.dataset.expenseFilter, select.value); });
      endpoint = `api/expenses/index.php?${params}`;
    } else if (kind === 'activity') {
      const type = $('[data-activity-filter]')?.value; if (type) params.set('type', type);
      endpoint = `api/activity.php?${params}`;
    } else {
      endpoint = `api/groups/index.php?${params}`;
    }
    $$('button', navigation).forEach(button => { button.disabled = true; });
    try {
      const response = await fetch(endpoint, { signal: controller.signal });
      const result = await response.json();
      if (!response.ok || !Array.isArray(result.data)) throw new Error(result.message || 'No se pudo cargar la página.');
      if (kind === 'expenses') renderExpensePage(result.data);
      if (kind === 'activity') renderActivityPage(result.data);
      if (kind === 'groups') renderGroupPage(result.data);
      navigation.dataset.page = String(result.pagination.page);
      navigation.dataset.hasNext = result.pagination.has_next ? '1' : '0';
      $('[data-page-label]', navigation).textContent = `Página ${result.pagination.page} de ${Math.max(1, result.pagination.total_pages)}`;
      $('[data-page-previous]', navigation).disabled = !result.pagination.has_previous;
      $('[data-page-next]', navigation).disabled = !result.pagination.has_next;
    } catch (error) {
      if (error.name !== 'AbortError') notify(error.message || 'No se pudo cargar la página.');
    }
  }

  function initApiPagination() {
    $$('[data-api-pagination]').forEach(navigation => navigation.addEventListener('click', event => {
      const previous = event.target.closest('[data-page-previous]');
      const next = event.target.closest('[data-page-next]');
      const current = Number(navigation.dataset.page || 1);
      if (previous) loadPagedList(navigation.dataset.apiPagination, current - 1);
      if (next) loadPagedList(navigation.dataset.apiPagination, current + 1);
    }));
  }

  async function postApi(endpoint, body) {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').content },
      body
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo completar la operación.');
    return result;
  }

  function setPickerMessage(picker, message, tone = '') {
    const results = $('[data-user-results]', picker);
    results.replaceChildren();
    if (!message) return;
    const status = document.createElement('p');
    status.className = `user-search-status ${tone}`.trim();
    status.textContent = message;
    results.append(status);
  }

  function renderSelectedParticipants(picker) {
    const state = participantPickers.get(picker);
    const selected = $('[data-selected-users]', picker);
    const hidden = $('[data-participant-ids]', picker);
    selected.replaceChildren(...[...state.selected.values()].map(user => {
      const chip = document.createElement('span');
      chip.className = 'selected-user';
      chip.append(document.createTextNode(user.name));
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.dataset.removeUser = String(user.id);
      remove.setAttribute('aria-label', `Quitar a ${user.name}`);
      remove.textContent = '×';
      chip.append(remove);
      return chip;
    }));
    hidden.value = [...state.selected.keys()].join(',');
  }

  function resetParticipantPicker(form) {
    const picker = $('[data-participant-picker]', form);
    if (!picker) return;
    const state = participantPickers.get(picker);
    if (!state) return;
    clearTimeout(state.timer);
    state.controller?.abort();
    state.selected.clear();
    $('[data-user-search]', picker).value = '';
    setPickerMessage(picker, 'Escribe al menos 3 caracteres para comprobar si la persona existe.');
    renderSelectedParticipants(picker);
  }

  function initParticipantPickers() {
    $$('[data-participant-picker]').forEach(picker => {
      const input = $('[data-user-search]', picker);
      const state = { selected: new Map(), timer: null, controller: null };
      participantPickers.set(picker, state);
      setPickerMessage(picker, 'Escribe al menos 3 caracteres para comprobar si la persona existe.');

      input.addEventListener('input', () => {
        clearTimeout(state.timer);
        state.controller?.abort();
        const query = input.value.trim();
        if (query.length < 3) {
          setPickerMessage(picker, 'Escribe al menos 3 caracteres para comprobar si la persona existe.');
          return;
        }

        setPickerMessage(picker, 'Comprobando usuario…', 'loading');
        state.timer = setTimeout(async () => {
          state.controller = new AbortController();
          const form = picker.closest('form');
          const parameters = new URLSearchParams({ q: query });
          if (form?.id === 'memberForm' && form.elements.group_id.value) {
            parameters.set('group_id', form.elements.group_id.value);
          }

          try {
            const response = await fetch(`api/users/search.php?${parameters}`, { signal: state.controller.signal });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo comprobar el usuario.');
            if (result.already_member) {
              setPickerMessage(picker, result.message || 'Ya tienes a esta persona agregada al grupo.', 'found');
              return;
            }
            if (result.pending_invitation) {
              setPickerMessage(picker, result.message || 'Esta persona ya tiene una invitación pendiente.', 'found');
              return;
            }
            const users = result.users.filter(user => !state.selected.has(Number(user.id)));
            if (result.exists && users.length === 0) {
              setPickerMessage(picker, 'Ya tienes a esta persona seleccionada.', 'found');
              return;
            }
            if (!result.exists) {
              setPickerMessage(picker, 'No existe una persona disponible con esos datos.', 'not-found');
              return;
            }

            const results = $('[data-user-results]', picker);
            results.replaceChildren(...users.map(user => {
              const button = document.createElement('button');
              button.type = 'button';
              button.className = 'user-search-result';
              button.dataset.userId = String(user.id);
              button.dataset.userName = user.name;
              const copy = document.createElement('span');
              const name = document.createElement('strong');
              name.textContent = user.name;
              copy.append(name);
              const action = document.createElement('b');
              action.textContent = 'Añadir';
              button.append(copy, action);
              return button;
            }));
          } catch (error) {
            if (error.name !== 'AbortError') setPickerMessage(picker, error.message || 'No se pudo comprobar el usuario.', 'error');
          }
        }, 320);
      });

      picker.addEventListener('click', event => {
        const result = event.target.closest('[data-user-id]');
        const remove = event.target.closest('[data-remove-user]');
        if (result) {
          const id = Number(result.dataset.userId);
          state.selected.set(id, { id, name: result.dataset.userName });
          input.value = '';
          renderSelectedParticipants(picker);
          setPickerMessage(picker, 'Persona encontrada y seleccionada.', 'found');
          input.focus();
        }
        if (remove) {
          state.selected.delete(Number(remove.dataset.removeUser));
          renderSelectedParticipants(picker);
        }
      });
    });
  }

  /**
   * Gráfico de gastos con tres escalas temporales.
   * `expenseSeries` puede reemplazarse después por la respuesta JSON de un endpoint PHP.
   */
  function initExpenseChart() {
    const canvas = $('#expenseEvolutionChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const databaseMonths = window.SplitlyData?.monthlyEvolution || [];
    const monthFormatter = new Intl.DateTimeFormat('es-ES', { month: 'short' });
    const expenseSeries = {
      monthly: {
        labels: databaseMonths.length
          ? databaseMonths.map(item => monthFormatter.format(new Date(`${item.month_key}-02T00:00:00`)).replace('.', ''))
          : ['Sin datos'],
        values: databaseMonths.length
          ? databaseMonths.map(item => Number(item.total))
          : [0]
      }
    };

    const context = canvas.getContext('2d');
    const fill = context.createLinearGradient(0, 0, 0, 150);
    fill.addColorStop(0, 'rgba(18, 164, 119, .28)');
    fill.addColorStop(1, 'rgba(18, 164, 119, 0)');

    const chart = new Chart(context, {
      type: 'line',
      data: {
        labels: expenseSeries.monthly.labels,
        datasets: [{
          label: 'Gastos',
          data: expenseSeries.monthly.values,
          borderColor: '#129469',
          backgroundColor: fill,
          borderWidth: 2,
          fill: true,
          tension: .42,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: '#129469',
          pointHoverBorderColor: '#ffffff',
          pointHoverBorderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 550, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            displayColors: false,
            backgroundColor: '#075a45',
            titleColor: 'rgba(255,255,255,.72)',
            bodyColor: '#ffffff',
            padding: 10,
            cornerRadius: 7,
            callbacks: {
              label: context => `${new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2 }).format(context.parsed.y)} €`
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: '#85928d', font: { family: 'DM Sans', size: 9 } }
          },
          y: {
            beginAtZero: true,
            suggestedMax: 600,
            border: { display: false },
            grid: { color: 'rgba(115,128,123,.12)', drawTicks: false },
            ticks: {
              maxTicksLimit: 4,
              padding: 7,
              color: '#85928d',
              font: { family: 'DM Sans', size: 9 },
              callback: value => `${value} €`
            }
          }
        }
      }
    });

    $$('.chart-range button').forEach(button => button.addEventListener('click', () => {
      const series = expenseSeries[button.dataset.range];
      if (!series) return;
      $$('.chart-range button').forEach(item => item.classList.remove('active'));
      button.classList.add('active');
      chart.data.labels = series.labels;
      chart.data.datasets[0].data = series.values;
      chart.options.scales.y.suggestedMax = 600;
      chart.update();
    }));
  }

  async function submitToApi(form, endpoint) {
    const submitButton = $('button[type="submit"], button:not([type])', form);
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Guardando…';

    try {
      const result = await postApi(endpoint, new FormData(form));

      closeModal();
      notify(result.message);
      form.reset();
      setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      notify(error.message || 'Ha ocurrido un error inesperado.');
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
  }

  // Event delegation keeps new buttons working when data becomes dynamic later.
  document.addEventListener('click', event => {
    const viewButton = event.target.closest('[data-view]');
    const openButton = event.target.closest('[data-open-modal]');
    if (viewButton) { navigate(viewButton.dataset.view); closeNotifications(); }
    if (openButton) openModal(openButton.dataset.openModal);
    const addMembersButton = event.target.closest('.add-members');
    if (addMembersButton) openModal('members', {
      groupId: addMembersButton.dataset.groupId,
      groupName: addMembersButton.dataset.groupName
    });
    const showMembersButton = event.target.closest('.show-group-members');
    if (showMembersButton) openGroupDetails(showMembersButton.dataset.groupId, showMembersButton.dataset.groupName);
    const groupCard = event.target.closest('[data-group-details]');
    if (groupCard && !event.target.closest('button')) {
      openGroupDetails(groupCard.dataset.groupDetails, groupCard.dataset.groupName);
    }
    if (event.target.closest('[data-close-modal]') || event.target === modal) closeModal();

    const invitationButton = event.target.closest('[data-invitation-action]');
    if (invitationButton) {
      const data = new FormData();
      data.set('group_id', invitationButton.dataset.groupId);
      data.set('action', invitationButton.dataset.invitationAction);
      const invitation = invitationButton.closest('.group-invitation');
      $$('button', invitation).forEach(button => { button.disabled = true; });
      postApi('api/groups/respond-invitation.php', data).then(result => {
        notify(result.message);
        invitation.remove();
        setTimeout(() => window.location.reload(), 650);
      }).catch(error => {
        notify(error.message);
        $$('button', invitation).forEach(button => { button.disabled = false; });
      });
    }

    const notificationMore = event.target.closest('[data-notification-more]');
    if (notificationMore) loadMoreNotifications(notificationMore);
    const groupMembersMore = event.target.closest('[data-group-members-more]');
    if (groupMembersMore) loadGroupMembers(groupMembersMore.dataset.groupId, Number(groupMembersMore.dataset.page || 1) + 1, true);

    const deleteButton = event.target.closest('.delete-row');
    if (deleteButton) {
      const row = deleteButton.closest('tr');
      if (!window.confirm('¿Quieres eliminar este gasto? Esta acción quedará registrada.')) return;
      const data = new FormData();
      data.set('expense_id', row.dataset.id);
      postApi('api/expenses/delete.php', data).then(result => {
        row.remove();
        notify(result.message);
      }).catch(error => notify(error.message));
    }

    const deleteGroupButton = event.target.closest('.delete-group');
    if (deleteGroupButton) {
      const groupName = deleteGroupButton.dataset.groupName;
      requestConfirmation({
        title: 'Eliminar grupo',
        message: `¿Quieres eliminar "${groupName}"? También se eliminarán sus gastos, pagos e historial. Esta acción no se puede deshacer.`,
        confirmLabel: 'Eliminar grupo'
      }, deleteGroupButton).then(confirmed => {
        if (!confirmed) return;
        const data = new FormData();
        data.set('group_id', deleteGroupButton.dataset.groupId);
        deleteGroupButton.disabled = true;
        postApi('api/groups/delete.php', data).then(result => {
          deleteGroupButton.closest('.group-card')?.remove();
          notify(result.message);
          setTimeout(() => window.location.reload(), 650);
        }).catch(error => {
          notify(error.message);
          deleteGroupButton.disabled = false;
        });
      });
    }

    const editButton = event.target.closest('.edit-expense');
    if (editButton) {
      try { openModal('expense', JSON.parse(editButton.closest('tr').dataset.expense)); }
      catch { notify('No se pudieron cargar los datos del gasto.'); }
    }

    const settlementButton = event.target.closest('.record-settlement');
    if (settlementButton) {
      if (!window.confirm(`¿Confirmas la liquidación de ${Number(settlementButton.dataset.amount).toLocaleString('es-ES', { minimumFractionDigits: 2 })} €?`)) return;
      const data = new FormData();
      ['groupId', 'counterpartyId', 'amount', 'direction'].forEach(key => data.set(key.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`), settlementButton.dataset[key]));
      settlementButton.disabled = true;
      postApi('api/settlements/create.php', data).then(result => {
        notify(result.message);
        setTimeout(() => window.location.reload(), 600);
      }).catch(error => notify(error.message)).finally(() => { settlementButton.disabled = false; });
    }
    if (event.target.closest('.group-detail')) {
      const groupId = event.target.closest('.group-card')?.dataset.groupDetails;
      navigate('expenses');
      const groupFilter = $('[data-expense-filter="group_id"]', $('#view-expenses'));
      if (groupFilter && groupId) { groupFilter.value = groupId; loadPagedList('expenses', 1); }
    }

    const logoutButton = event.target.closest('.logout');
    if (logoutButton) {
      event.preventDefault();
      fetch('api/auth/logout.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').content }
      }).then(response => response.json()).then(result => {
        window.location.href = result.redirect || 'login.php';
      }).catch(() => notify('No se pudo cerrar la sesión.'));
    }
  });

  $('#menuToggle').addEventListener('click', openSidebar);
  $('#mobileMoreToggle')?.addEventListener('click', openSidebar);
  overlay.addEventListener('click', closeSidebar);
  $('#globalSearch').addEventListener('input', event => {
    clearTimeout(event.currentTarget.searchTimer);
    event.currentTarget.searchTimer = setTimeout(() => {
      const active = $('.app-view.active')?.id?.replace('view-', '');
      if (['expenses', 'activity', 'groups'].includes(active)) loadPagedList(active, 1);
      else filterVisibleView(event.currentTarget.value);
    }, 320);
  });
  $('#expenseForm [name="group_id"]')?.addEventListener('change', event => updatePayerOptions(event.target.value, window.SplitlyData.currentUserId));
  $('#expenseForm [name="split_expense"]')?.addEventListener('change', updateSplitPreview);
  $('#notificationToggle')?.addEventListener('click', event => {
    event.stopPropagation();
    const panel = $('#notificationPanel');
    const open = panel.classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
    if (open) {
      postApi('api/notifications/read.php', new FormData()).then(() => {
        $('.notification > span')?.remove();
        $$('.notification-item').forEach(item => item.classList.remove('unread'));
      }).catch(() => {});
    }
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('.notification-wrap')) closeNotifications();
  });
  confirmation?.addEventListener('click', event => {
    if (event.target === confirmation || event.target.closest('[data-confirm-cancel]')) closeConfirmation(false);
    if (event.target.closest('[data-confirm-accept]')) closeConfirmation(true);
  });
  $$('[data-expense-filter]').forEach(select => select.addEventListener('change', () => loadPagedList('expenses', 1)));
  $('[data-activity-filter]')?.addEventListener('change', event => {
    const value = event.target.value;
    loadPagedList('activity', 1);
  });

  $$('.chip').forEach(chip => chip.addEventListener('click', () => {
    $$('.chip').forEach(item => item.classList.remove('active'));
    chip.classList.add('active');
    $(`.chart-range [data-range="${chip.dataset.dashboardRange}"]`)?.click();
  }));

  $$('.segmented:not(.chart-range) button').forEach(button => button.addEventListener('click', () => {
    $$('.segmented button').forEach(item => item.classList.remove('active'));
    button.classList.add('active');
  }));

  $('#expenseForm')?.addEventListener('submit', event => {
    event.preventDefault();
    submitToApi(event.currentTarget, event.currentTarget.dataset.endpoint || 'api/expenses/create.php');
  });
  $('#groupForm')?.addEventListener('submit', event => {
    event.preventDefault();
    const picker = $('[data-participant-picker]', event.currentTarget);
    const typedParticipant = $('[data-user-search]', picker)?.value.trim();
    if (typedParticipant && !event.currentTarget.elements.participant_ids.value) {
      notify('La persona escrita no existe o no ha sido seleccionada.');
      return;
    }
    submitToApi(event.currentTarget, 'api/groups/create.php');
  });
  $('#memberForm')?.addEventListener('submit', event => {
    event.preventDefault();
    if (!event.currentTarget.elements.participant_ids.value) {
      notify('Busca y selecciona al menos una persona.');
      return;
    }
    submitToApi(event.currentTarget, 'api/groups/add-members.php');
  });
  $('#settingsForm')?.addEventListener('submit', event => {
    event.preventDefault();
    const button = $('button[type="submit"]', event.currentTarget);
    button.disabled = true;
    postApi('api/settings/update.php', new FormData(event.currentTarget)).then(result => {
      notify(result.message);
      setTimeout(() => window.location.reload(), 650);
    }).catch(error => notify(error.message)).finally(() => { button.disabled = false; });
  });
  $('#settingsForm')?.addEventListener('reset', () => {
    setTimeout(() => notify('Cambios descartados.'), 0);
  });
  $('#generateShortcutToken')?.addEventListener('click', event => {
    const button = event.currentTarget;
    const data = new FormData();
    data.set('action', 'generate');
    button.disabled = true;
    postApi('api/shortcuts/token.php', data).then(result => {
      $('#shortcutTokenValue').value = result.token;
      $('#shortcutTokenResult').classList.remove('hidden');
      notify('Token del Atajo creado. Guárdalo ahora.');
    }).catch(error => notify(error.message)).finally(() => { button.disabled = false; });
  });
  $('#revokeShortcutToken')?.addEventListener('click', event => {
    requestConfirmation({
      title: 'Revocar token del Atajo',
      message: 'El Atajo dejará de funcionar hasta que generes y guardes un token nuevo.',
      confirmLabel: 'Revocar token'
    }, event.currentTarget).then(confirmed => {
      if (!confirmed) return;
      const data = new FormData();
      data.set('action', 'revoke');
      postApi('api/shortcuts/token.php', data).then(result => {
        $('#shortcutTokenValue').value = '';
        $('#shortcutTokenResult').classList.add('hidden');
        notify(result.message);
      }).catch(error => notify(error.message));
    });
  });
  $('#copyShortcutToken')?.addEventListener('click', () => {
    const token = $('#shortcutTokenValue').value;
    if (!token) return;
    navigator.clipboard.writeText(token).then(() => notify('Token copiado.')).catch(() => {
      $('#shortcutTokenValue').select();
      notify('Selecciona y copia el token.');
    });
  });
  $$('[data-settings-target]').forEach(button => button.addEventListener('click', () => {
    $$('.settings-nav button').forEach(item => item.classList.toggle('active', item === button));
    $(`#${button.dataset.settingsTarget}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }));

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeConfirmation(false); closeModal(); closeSidebar(); closeNotifications(); }
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-group-details]')) {
      event.preventDefault();
      openGroupDetails(event.target.dataset.groupDetails, event.target.dataset.groupName);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 860) closeSidebar();
  }, { passive: true });

  const initialView = location.hash.slice(1);
  if (initialView) navigate(initialView, false);
  initParticipantPickers();
  initExpenseChart();
  initApiPagination();
})();
