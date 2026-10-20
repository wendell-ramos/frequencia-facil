// Funções de tela reaproveitadas por todas as páginas.
import { USAR_MOCK } from './api.js';

/** Mostra uma mensagem de sucesso ou erro numa <div class="msg">. */
export function mostrarMensagem(el, texto, tipo = 'ok') {
  el.textContent = texto;
  el.className = `msg ${tipo}`;
  el.hidden = false;
  if (tipo === 'ok') setTimeout(() => { el.hidden = true; }, 3000);
}

/** Data de hoje no formato AAAA-MM-DD (fuso local). */
export function hoje() {
  const d = new Date();
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

/** 2026-10-20 -> 20/10/2026 */
export function formatarData(iso) {
  if (!iso) return '—';
  const [a, m, d] = iso.slice(0, 10).split('-');
  return `${d}/${m}/${a}`;
}

/** Evita que um nome com <script> vire código na página. */
export function escapar(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

/** Destaca no menu a página atual e avisa quando está em modo mock. */
export function iniciarPagina() {
  const atual = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('nav a').forEach((a) => {
    if (a.getAttribute('href') === atual) a.classList.add('ativo');
  });
  if (USAR_MOCK) {
    const aviso = document.createElement('p');
    aviso.className = 'aviso-mock';
    aviso.textContent = 'Modo demonstração: dados salvos só neste navegador.';
    document.querySelector('main').append(aviso);
  }
}
