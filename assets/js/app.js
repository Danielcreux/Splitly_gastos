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

  function openGroupDetails(groupId, groupName) {
    const members = window.SplitlyData?.groupMembers?.[String(groupId)] || [];
    $('#expenseForm').classList.add('hidden');
    $('#groupForm').classList.add('hidden');
    $('#memberForm').classList.add('hidden');
    const panel = $('#groupDetailPanel');
    panel.classList.remove('hidden');
    $('#modalTitle').textContent = groupName;
    $('#modalSubtitle').textContent = `${members.length} ${members.length === 1 ? 'integrante activo' : 'integrantes activos'}`;
    const roles = { owner: 'Propietario', admin: 'Administrador', member: 'Integrante' };
    $('[data-group-member-list]', panel).replaceChildren(...members.map(member => {
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
    }));
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();
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

  function applyExpenseFilters(view) {
    const filters = {};
    $$('[data-expense-filter]', view).forEach(select => { filters[select.dataset.expenseFilter] = select.value; });
    $$('tr[data-id]', view).forEach(row => {
      const visible = Object.entries(filters).every(([key, value]) => !value || row.dataset[key] === value);
      row.classList.toggle('filter-hidden', !visible);
    });
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
      const groupName = event.target.closest('.group-detail').dataset.group.toLocaleLowerCase('es');
      navigate('expenses');
      const groupFilter = $('[data-expense-filter="group"]', $('#view-expenses'));
      if (groupFilter) { groupFilter.value = groupName; applyExpenseFilters($('#view-expenses')); }
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
  $('#globalSearch').addEventListener('input', event => filterVisibleView(event.target.value));
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
  $$('[data-expense-filter]').forEach(select => select.addEventListener('change', () => applyExpenseFilters(select.closest('.app-view'))));
  $('[data-activity-filter]')?.addEventListener('change', event => {
    const value = event.target.value;
    $$('.timeline-row', event.target.closest('.timeline')).forEach(row => {
      row.classList.toggle('filter-hidden', Boolean(value) && row.dataset.activityType !== value);
    });
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
})();
