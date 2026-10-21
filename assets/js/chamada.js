// Tela de Chamada — US02: marcar presente (P) ou falta (F) em uma data.
import { api } from './api.js';
import {
  mostrarMensagem, esconderMensagem, hoje, somarDias, formatarData,
  formatarDataExtenso, escapar, plural, iniciarPagina, url,
} from './ui.js';

const entradaData = document.getElementById('data');
const dataExtenso = document.getElementById('data-extenso');
const lista = document.getElementById('lista');
const contadores = document.getElementById('contadores');
const mensagem = document.getElementById('mensagem');
const botaoSalvar = document.getElementById('botao-salvar');
const botaoTodos = document.getElementById('todos-presentes');
const estadoSalvar = document.getElementById('estado-salvar');
const barraSalvar = document.getElementById('barra-salvar');
const botaoSeguinte = document.getElementById('dia-seguinte');

let alunos = [];              // [{ alunoId, nome, presente }]
let marcacoes = new Map();    // alunoId -> true (P) | false (F) | null
let salvas = new Map();       // como estava no último carregamento/salvamento
let pedidoAtual = 0;          // ignora respostas antigas se a data mudar rápido

// ---------- Carregar a chamada de uma data ----------
async function carregar() {
  esconderMensagem(mensagem);
  const data = entradaData.value;
  dataExtenso.textContent = formatarDataExtenso(data, true);
  botaoSeguinte.disabled = data >= hoje();
  lista.innerHTML = '<li class="vazio">Carregando a turma…</li>';
  const pedido = ++pedidoAtual;

  try {
    const resposta = await api.presencasDoDia(data);
    if (pedido !== pedidoAtual) return;
    alunos = resposta.alunos;
    marcacoes = new Map(alunos.map((a) => [a.alunoId, a.presente]));
    salvas = new Map(marcacoes);
    desenhar();
  } catch (e) {
    lista.innerHTML = '';
    mostrarMensagem(mensagem, `Não foi possível carregar a chamada: ${e.message}`, 'erro');
  }
}

function desenhar() {
  const temAlunos = alunos.length > 0;
  botaoTodos.hidden = !temAlunos;
  barraSalvar.hidden = !temAlunos;

  if (!temAlunos) {
    contadores.textContent = '';
    lista.innerHTML = `<li class="vazio">Nenhum aluno na turma ainda.
      <a href="${url('/alunos')}">Cadastre os alunos</a> para fazer a chamada.</li>`;
    return;
  }

  // Número da chamada = posição na lista em ordem alfabética
  lista.innerHTML = alunos.map((a, i) => {
    const valor = marcacoes.get(a.alunoId);
    const nome = escapar(a.nome);
    return `
      <li class="linha-chamada" data-aluno="${a.alunoId}">
        <span class="linha-chamada__numero" aria-hidden="true">${i + 1}</span>
        <span class="linha-chamada__nome" id="nome-${a.alunoId}">${nome}</span>
        <fieldset class="marcador" aria-labelledby="nome-${a.alunoId}">
          <label class="marcador__opcao marcador__opcao--presente" title="Presente">
            <input type="radio" name="aluno-${a.alunoId}" value="P" ${valor === true ? 'checked' : ''}>
            <span class="marcador__caixa" aria-hidden="true">P</span>
            <span class="visualmente-oculto">Presente</span>
          </label>
          <label class="marcador__opcao marcador__opcao--falta" title="Falta">
            <input type="radio" name="aluno-${a.alunoId}" value="F" ${valor === false ? 'checked' : ''}>
            <span class="marcador__caixa" aria-hidden="true">F</span>
            <span class="visualmente-oculto">Falta</span>
          </label>
        </fieldset>
      </li>`;
  }).join('');
  atualizarResumo();
}

// ---------- Marcar ----------
lista.addEventListener('change', (evento) => {
  const linha = evento.target.closest('.linha-chamada');
  if (!linha) return;
  marcacoes.set(Number(linha.dataset.aluno), evento.target.value === 'P');
  linha.classList.remove('linha-chamada--pendente');
  atualizarResumo();
});

botaoTodos.addEventListener('click', () => {
  for (const a of alunos) {
    if (marcacoes.get(a.alunoId) === null) marcacoes.set(a.alunoId, true);
  }
  lista.querySelectorAll('input[value="P"]').forEach((r) => {
    const id = Number(r.closest('.linha-chamada').dataset.aluno);
    if (marcacoes.get(id) === true) r.checked = true;
  });
  lista.querySelectorAll('.linha-chamada--pendente').forEach((l) => l.classList.remove('linha-chamada--pendente'));
  atualizarResumo();
});

function temAlteracoes() {
  return alunos.some((a) => marcacoes.get(a.alunoId) !== salvas.get(a.alunoId));
}

function atualizarResumo() {
  const valores = [...marcacoes.values()];
  const presentes = valores.filter((v) => v === true).length;
  const faltas = valores.filter((v) => v === false).length;
  const pendentes = valores.filter((v) => v === null).length;

  contadores.innerHTML = `
    <span class="contador contador--presente"><strong>${presentes}</strong> ${presentes === 1 ? 'presente' : 'presentes'}</span>
    <span class="contador contador--falta"><strong>${faltas}</strong> ${faltas === 1 ? 'falta' : 'faltas'}</span>
    <span class="contador"><strong>${pendentes}</strong> sem marcação</span>`;

  botaoTodos.disabled = pendentes === 0;

  const alterado = temAlteracoes();
  estadoSalvar.textContent = alterado
    ? 'Alterações não salvas'
    : (salvas.size && [...salvas.values()].every((v) => v !== null) ? 'Chamada salva' : 'Chamada ainda não registrada');
  estadoSalvar.classList.toggle('barra-salvar__estado--pendente', alterado);
}

// ---------- Salvar ----------
botaoSalvar.addEventListener('click', async () => {
  const pendentes = alunos.filter((a) => marcacoes.get(a.alunoId) === null);
  if (pendentes.length) {
    pendentes.forEach((a) => lista.querySelector(`[data-aluno="${a.alunoId}"]`)?.classList.add('linha-chamada--pendente'));
    mostrarMensagem(
      mensagem,
      `Falta marcar ${plural(pendentes.length, 'aluno', 'alunos')}: ${pendentes.map((a) => a.nome).join(', ')}.`,
      'erro',
    );
    lista.querySelector('.linha-chamada--pendente input')?.focus();
    return;
  }

  const registros = alunos.map((a) => ({ alunoId: a.alunoId, presente: marcacoes.get(a.alunoId) }));
  botaoSalvar.disabled = true;
  try {
    await api.registrarPresenca(entradaData.value, registros);
    salvas = new Map(marcacoes);
    atualizarResumo();
    mostrarMensagem(mensagem, `Chamada de ${formatarData(entradaData.value)} salva.`);
  } catch (e) {
    mostrarMensagem(mensagem, e.message, 'erro');
  } finally {
    botaoSalvar.disabled = false;
  }
});

// ---------- Trocar de data ----------
function irPara(data) {
  if (data > hoje()) return;
  if (temAlteracoes() && !confirm('Há alterações não salvas nesta chamada. Trocar de data mesmo assim?')) {
    entradaData.value = entradaData.dataset.atual;
    return;
  }
  entradaData.value = data;
  entradaData.dataset.atual = data;
  carregar();
}

entradaData.addEventListener('change', () => { if (entradaData.value) irPara(entradaData.value); });
document.getElementById('dia-anterior').addEventListener('click', () => irPara(somarDias(entradaData.value, -1)));
botaoSeguinte.addEventListener('click', () => irPara(somarDias(entradaData.value, 1)));
document.getElementById('ir-hoje').addEventListener('click', () => irPara(hoje()));

window.addEventListener('beforeunload', (evento) => {
  if (temAlteracoes()) evento.preventDefault();
});

// ---------- Início: data de hoje ----------
iniciarPagina();
entradaData.max = hoje();
entradaData.value = hoje();
entradaData.dataset.atual = entradaData.value;
carregar();
