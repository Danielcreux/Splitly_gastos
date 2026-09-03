(() => {
  'use strict';
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const form = $('[data-auth-form]');
  const alert = $('#authAlert');

  function showMessage(message, success = false) {
    if (!alert) return;
    alert.textContent = message;
    alert.classList.toggle('success', success);
    alert.classList.add('show');
  }

  $$('[data-toggle-password]').forEach(button => button.addEventListener('click', () => {
    const input = $('input', button.parentElement);
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    button.textContent = showing ? '◉' : '⊘';
    button.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
  }));

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    alert?.classList.remove('show');
    const button = $('button[type="submit"]', form);
    const original = button.innerHTML;
    button.disabled = true;
    button.textContent = 'Procesando…';

    try {
      const response = await fetch(form.dataset.endpoint, {
        method: 'POST',
        headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').content },
        body: new FormData(form)
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo completar la operación.');
      showMessage(result.message, true);

      if (result.reset_url) {
        const box = $('#resetLinkBox');
        box.textContent = 'Entorno local: ';
        const link = document.createElement('a');
        link.href = result.reset_url;
        link.textContent = 'abrir enlace de recuperación';
        box.appendChild(link);
        box.classList.add('show');
      }
      if (result.redirect) setTimeout(() => { window.location.href = result.redirect; }, 550);
    } catch (error) {
      showMessage(error.message || 'Ha ocurrido un error inesperado.');
    } finally {
      button.disabled = false;
      button.innerHTML = original;
    }
  });
})();
