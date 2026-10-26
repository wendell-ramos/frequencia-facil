// Funções de tela usadas por todas as páginas.
import { EM_DEMONSTRACAO, auth, irParaLogin } from './api.js';

/** Mostra uma mensagem no topo da página. tipo: 'sucesso' | 'erro' */
export function mostrarMensagem(el, texto, tipo = 'sucesso') {
  el.textContent = texto;
  el.className = `mensagem mensagem--${tipo}`;
  el.hidden = false;
  clearTimeout(el.temporizador);
  if (tipo === 'sucesso') {
    el.temporizador = setTimeout(() => { el.hidden = true; }, 4000);
  }
}

export function esconderMensagem(el) {
  clearTimeout(el.temporizador);
  el.hidden = true;
}

/** Data de hoje no formato AAAA-MM-DD, no fuso do computador. */
export function hoje() {
  const d = new Date();
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

/** Soma (ou subtrai) dias de uma data AAAA-MM-DD. */
export function somarDias(iso, dias) {
  const [a, m, d] = iso.split('-').map(Number);
  const data = new Date(a, m - 1, d + dias);
  const mm = String(data.getMonth() + 1).padStart(2, '0');
  const dd = String(data.getDate()).padStart(2, '0');
  return `${data.getFullYear()}-${mm}-${dd}`;
}

/** 2026-10-20 -> 20/10/2026 */
export function formatarData(iso) {
  if (!iso) return '';
  const [a, m, d] = iso.slice(0, 10).split('-');
  return `${d}/${m}/${a}`;
}

/** 2026-10-20 -> 20/10 */
export function formatarDataCurta(iso) {
  const [, m, d] = iso.slice(0, 10).split('-');
  return `${d}/${m}`;
}

/** 2026-10-20 -> "Terça-feira, 20 de outubro" */
export function formatarDataExtenso(iso, comAno = false) {
  const [a, m, d] = iso.split('-').map(Number);
  const texto = new Date(a, m - 1, d).toLocaleDateString('pt-BR', {
    weekday: 'long', day: 'numeric', month: 'long', ...(comAno ? { year: 'numeric' } : {}),
  });
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** plural(1, 'falta', 'faltas') -> "1 falta"; plural(3, ...) -> "3 faltas" */
export function plural(n, singular, varios) {
  return `${n} ${n === 1 ? singular : varios}`;
}

/** Evita que um nome com <script> vire código na página. */
export function escapar(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

/** Formata o telefone enquanto a pessoa digita: (18) 99711-0001 */
export function aplicarMascaraTelefone(input) {
  input.addEventListener('input', () => {
    const d = input.value.replace(/\D/g, '').slice(0, 11);
    let valor = d;
    if (d.length > 2) valor = `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length > 6) valor = `(${d.slice(0, 2)}) ${d.slice(2, d.length - 4)}-${d.slice(-4)}`;
    input.value = valor;
  });
}

/**
 * Monta um endereço do sistema respeitando o APP_BASE_PATH.
 * url('/alunos') -> "/alunos" no Docker, "/frequencia-facil/alunos" no XAMPP.
 * O PHP grava a base em <body data-base="...">.
 */
export function url(caminho) {
  return (document.body.dataset.base || '') + caminho;
}

/**
 * Prepara a tela: aviso de demonstração no rodapé, nome do professor e botão Sair.
 * Também já busca o token CSRF, para as gravações não esperarem por ele.
 */
export function iniciarPagina() {
  const aviso = document.getElementById('aviso-modo');
  if (EM_DEMONSTRACAO && aviso) {
    aviso.textContent = 'Modo de demonstração: os dados ficam salvos só neste navegador.';
    aviso.hidden = false;
  }

  const botaoSair = document.getElementById('botao-sair');
  if (!botaoSair) return; // tela de login não tem sessão para mostrar

  auth.sessao().then((sessao) => {
    if (!sessao.autenticado) return irParaLogin();
    document.getElementById('nome-professor').textContent = sessao.professor.nome;
  }).catch(() => { /* sem conexão: a própria tela mostra o erro ao carregar os dados */ });

  botaoSair.addEventListener('click', async () => {
    botaoSair.disabled = true;
    try {
      await auth.sair();
    } catch {
      // mesmo se a sessão já tiver expirado, segue para o login
    }
    location.assign(`${document.body.dataset.base || ''}/login`);
  });
}
