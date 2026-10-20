// Tela de Histórico — US03: presenças e faltas de um aluno, com filtro por período
// e percentual de frequência (item 9).
import { api } from './api.js';
import {
  mostrarMensagem, esconderMensagem, formatarData, formatarDataCurta,
  escapar, iniciarPagina,
} from './ui.js';

// Abaixo disto a frequência aparece em vermelho (75% é o mínimo usual de presença)
const FREQUENCIA_MINIMA = 75;

const seletorAluno = document.getElementById('aluno');
const inicio = document.getElementById('inicio');
const fim = document.getElementById('fim');
const resultado = document.getElementById('resultado');
const semAluno = document.getElementById('sem-aluno');
const caderneta = document.getElementById('caderneta');
const semRegistros = document.getElementById('sem-registros');
const mensagem = document.getElementById('mensagem');

/** Percentual de presença: 7 de 8 aulas -> 87.5 (null se não houve aula) */
export function calcularPercentual(presencas, total) {
  if (!total) return null;
  return Math.round((presencas / total) * 1000) / 10;
}

async function carregarAlunos() {
  try {
    const alunos = await api.listarAlunos();
    seletorAluno.innerHTML = '<option value="">Escolha um aluno</option>' +
      alunos.map((a) => `<option value="${a.id}">${escapar(a.nome)}</option>`).join('');

    // Veio da tela de Alunos com ?id=3? Já seleciona o aluno.
    const idNaUrl = new URLSearchParams(location.search).get('id');
    if (idNaUrl && alunos.some((a) => String(a.id) === idNaUrl)) {
      seletorAluno.value = idNaUrl;
      carregarHistorico();
    }
  } catch (e) {
    mostrarMensagem(mensagem, `Não foi possível carregar os alunos: ${e.message}`, 'erro');
  }
}

async function carregarHistorico() {
  esconderMensagem(mensagem);
  const temAluno = Boolean(seletorAluno.value);
  semAluno.hidden = temAluno;
  if (!temAluno) {
    resultado.hidden = true;
    return;
  }
  if (inicio.value && fim.value && inicio.value > fim.value) {
    mostrarMensagem(mensagem, 'A data "De" precisa ser igual ou anterior à data "Até".', 'erro');
    return;
  }

  try {
    const h = await api.historico(seletorAluno.value, inicio.value, fim.value);
    desenhar(h);
  } catch (e) {
    mostrarMensagem(mensagem, e.message, 'erro');
  }
}

function desenhar(h) {
  const { presencas, faltas, total } = h.totais;
  const pct = calcularPercentual(presencas, total);

  document.getElementById('nome-aluno').textContent = h.aluno.nome;
  document.getElementById('periodo-texto').textContent = textoPeriodo();
  document.getElementById('total-presencas').textContent = presencas;
  document.getElementById('total-faltas').textContent = faltas;
  document.getElementById('total-aulas').textContent = total;

  // Percentual e barra
  const elPct = document.getElementById('percentual');
  const legenda = document.getElementById('percentual-legenda');
  const barra = document.getElementById('barra');
  elPct.classList.remove('percentual--bom', 'percentual--baixo');
  document.getElementById('barra-minimo').style.left = `${FREQUENCIA_MINIMA}%`;
  document.getElementById('barra-minimo').firstElementChild.textContent = `mínimo ${FREQUENCIA_MINIMA}%`;

  if (pct === null) {
    elPct.textContent = '—';
    legenda.textContent = 'Sem aulas no período';
    barra.hidden = true;
  } else {
    const bom = pct >= FREQUENCIA_MINIMA;
    elPct.textContent = `${pct.toLocaleString('pt-BR')}%`;
    elPct.classList.add(bom ? 'percentual--bom' : 'percentual--baixo');
    legenda.textContent = bom ? 'de frequência, dentro do mínimo' : `de frequência, abaixo do mínimo de ${FREQUENCIA_MINIMA}%`;
    barra.hidden = false;
    barra.setAttribute('aria-label', `Frequência de ${pct}%. Mínimo exigido: ${FREQUENCIA_MINIMA}%.`);
    document.getElementById('barra-preenchimento').style.width = `${pct}%`;
  }

  // Caderneta: um quadradinho por aula, da mais antiga para a mais recente
  const aulas = [...h.registros].sort((a, b) => a.dataAula.localeCompare(b.dataAula));
  semRegistros.hidden = aulas.length > 0;
  caderneta.innerHTML = aulas.map((r) => `
    <li class="celula ${r.presente ? 'celula--presente' : 'celula--falta'}"
        title="${formatarData(r.dataAula)}: ${r.presente ? 'presente' : 'falta'}">
      <span class="celula__marca" aria-hidden="true">${r.presente ? 'P' : 'F'}</span>
      <span class="celula__data">${formatarDataCurta(r.dataAula)}</span>
      <span class="visualmente-oculto">${r.presente ? 'Presente' : 'Falta'} em ${formatarData(r.dataAula)}</span>
    </li>`).join('');

  resultado.hidden = false;
}

function textoPeriodo() {
  if (inicio.value && fim.value) return `De ${formatarData(inicio.value)} até ${formatarData(fim.value)}`;
  if (inicio.value) return `A partir de ${formatarData(inicio.value)}`;
  if (fim.value) return `Até ${formatarData(fim.value)}`;
  return 'Todo o período';
}

[seletorAluno, inicio, fim].forEach((el) => el.addEventListener('change', carregarHistorico));
document.getElementById('limpar-periodo').addEventListener('click', () => {
  inicio.value = '';
  fim.value = '';
  carregarHistorico();
});

iniciarPagina();
carregarAlunos();
