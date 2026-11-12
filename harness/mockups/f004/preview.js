"use strict";
const views = [...document.querySelectorAll('.view')];
const navigation = [...document.querySelectorAll('#main-nav a')];
function showView() {
  const requested = location.hash.slice(1) || 'inicio';
  const role = document.querySelector('#preview-role').value;
  const target = requested === 'clientes' && role === 'member' ? 'inicio' : requested;
  const selected = views.find(view => view.id === target) || views[0];
  views.forEach(view => { view.hidden = view !== selected; });
  navigation.forEach(link => { if (link.hash === '#' + selected.id) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current'); });
  document.querySelector('#main-nav').classList.remove('open');
  document.querySelector('.menu-toggle').setAttribute('aria-expanded', 'false');
  document.querySelector('#contenido').focus({preventScroll:true});
}
window.addEventListener('hashchange', showView);
document.querySelector('.menu-toggle').addEventListener('click', event => {
  const open = event.currentTarget.getAttribute('aria-expanded') !== 'true';
  event.currentTarget.setAttribute('aria-expanded', String(open));
  document.querySelector('#main-nav').classList.toggle('open', open);
});
document.querySelector('#preview-state').addEventListener('change', event => {
  const state = event.target.value;
  document.querySelectorAll('[data-populated]').forEach(element => { element.hidden = state === 'empty'; });
  document.querySelectorAll('[data-empty]').forEach(element => { element.hidden = state !== 'empty'; });
  document.querySelectorAll('[data-metric]').forEach(element => { element.textContent = state === 'empty' ? (element.dataset.metric.includes('h') ? '0 h' : '0') : element.dataset.metric; });
  document.querySelector('#task-hint').textContent = state === 'empty' ? 'Sin tareas por vencer' : '2 próximas a vencer';
  document.querySelector('#error-state').hidden = state !== 'error';
  document.querySelector('#bag-status').textContent = state === 'expired' ? 'Vigencia vencida' : 'Vigente';
  document.querySelector('#bag-status').className = 'badge ' + (state === 'expired' ? 'amber' : 'green');
  document.querySelector('#expiry-note').hidden = state !== 'expired';
  if (state === 'expired') location.hash = 'clientes';
});
document.querySelector('#retry').addEventListener('click', () => {
  const state = document.querySelector('#preview-state'); state.value = 'normal'; state.dispatchEvent(new Event('change'));
});
document.querySelector('#preview-role').addEventListener('change', event => {
  const role = event.target.value;
  document.querySelectorAll('[data-team]').forEach(element => { element.hidden = role === 'member'; });
  document.querySelectorAll('[data-admin]').forEach(element => { element.hidden = role !== 'admin'; });
  document.querySelector('#role-label').textContent = event.target.selectedOptions[0].textContent;
  showView();
});
document.querySelectorAll('[data-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.open).showModal()));
document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
function message(text) { const toast = document.querySelector('#toast'); toast.textContent = text; toast.hidden = false; }
document.querySelector('#task-form').addEventListener('submit', () => message('Tarea de ejemplo creada.'));
document.querySelector('#time-form').addEventListener('submit', event => { event.preventDefault(); document.querySelector('#time-result').hidden = false; });
document.querySelector('#apply-filters').addEventListener('click', () => { document.querySelector('#filter-result').hidden = false; });
document.querySelector('#export-sample').addEventListener('click', () => message('Vista previa: exportación de horas de ejemplo.'));
showView();
