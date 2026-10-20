// Tela de Histórico — US03: presenças e faltas de um aluno, com filtro por período.
import { api } from './api.js';
import { mostrarMensagem, formatarData, escapar, iniciarPagina } from './ui.js';

const selAluno = document.getElementById('aluno');
const inicio = document.getElementById('inicio');
const fim = document.getElementById('fim');
const resultado = document.getElementById('resultado');
const lista = document.getElementById('lista');
const msg = document.getElementById('mensagem');

// Abaixo disto a frequência aparece em vermelho (75% é o mínimo usual de presença)
const FREQUENCIA_MINIMA = 75;

/** Percentual de presença: 7 de 8 aulas -> 87.5 (null se não houve aula) */
export function calcularPercentual(presencas, total) {
  if (!total) return null;
  return Math.round((presencas / total) * 1000) / 10;
}

async function carregarAlunos() {
  try {
    const alunos = await api.listarAlunos();
    selAluno.innerHTML = '<option value="">Selecione...</option>' +
      alunos.map((a) => `<option value="${a.id}">${escapar(a.nome)}</option>`).join('');

    // Veio da tela de alunos com ?id=3? Já seleciona.
    const idUrl = new URLSearchParams(location.search).get('id');
    if (idUrl) {
      selAluno.value = idUrl;
      carregarHistorico();
    }
  } catch (e) {
    mostrarMensagem(msg, `Não foi possível carregar os alunos: ${e.message}`, 'erro');
  }
}

async function carregarHistorico() {
  msg.hidden = true;
  if (!selAluno.value) {
    resultado.hidden = true;
    return;
  }
  if (inicio.value && fim.value && inicio.value > fim.value) {
    mostrarMensagem(msg, 'A data "De" precisa ser anterior à data "Até".', 'erro');
    return;
  }

  try {
    const h = await api.historico(selAluno.value, inicio.value, fim.value);
    document.getElementById('nome-aluno').textContent = h.aluno.nome;
    document.getElementById('t-presencas').textContent = h.totais.presencas;
    document.getElementById('t-faltas').textContent = h.totais.faltas;
    document.getElementById('t-total').textContent = h.totais.total;
    mostrarPercentual(calcularPercentual(h.totais.presencas, h.totais.total));

    lista.innerHTML = h.registros.length
      ? h.registros.map((r) => `
          <tr>
            <td>${formatarData(r.dataAula)}</td>
            <td><span class="tag ${r.presente ? 'presente' : 'ausente'}">${r.presente ? 'Presente' : 'Ausente'}</span></td>
          </tr>`).join('')
      : `<tr><td colspan="2" class="vazio">Nenhum registro de presença no período.</td></tr>`;

    resultado.hidden = false;
  } catch (e) {
    mostrarMensagem(msg, e.message, 'erro');
  }
}

function mostrarPercentual(pct) {
  const caixa = document.getElementById('caixa-percentual');
  caixa.classList.remove('boa', 'baixa');
  if (pct === null) {
    document.getElementById('t-percentual').textContent = '—';
    caixa.title = 'Nenhuma aula no período';
    return;
  }
  document.getElementById('t-percentual').textContent = `${pct.toLocaleString('pt-BR')}%`;
  caixa.classList.add(pct >= FREQUENCIA_MINIMA ? 'boa' : 'baixa');
  caixa.title = pct >= FREQUENCIA_MINIMA
    ? 'Frequência dentro do mínimo'
    : `Abaixo do mínimo de ${FREQUENCIA_MINIMA}%`;
}

[selAluno, inicio, fim].forEach((el) => el.addEventListener('change', carregarHistorico));

iniciarPagina();
carregarAlunos();
