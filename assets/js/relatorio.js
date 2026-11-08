// Tela de Relatório — card #9: presenças, faltas e percentual de todos os alunos
// em um período. O percentual e a marcação de baixa frequência vêm prontos da API.
import { api } from './api.js';
import {
  mostrarMensagem, esconderMensagem, formatarData, escapar, iniciarPagina,
} from './ui.js';

const formulario = document.getElementById('filtros');
const inicio = document.getElementById('inicio');
const fim = document.getElementById('fim');
const botaoConsultar = document.getElementById('consultar');
const mensagem = document.getElementById('mensagem');
const erroAcao = document.getElementById('erro-acao');
const carregando = document.getElementById('carregando');
const resultado = document.getElementById('resultado');
const tabela = document.getElementById('tabela');
const semAlunos = document.getElementById('sem-alunos');

// Só a consulta mais recente pode desenhar na tela (evita resposta antiga sobrescrever a nova)
let consultaAtual = 0;

async function consultar() {
  const numero = ++consultaAtual;
  limparErros();
  resultado.hidden = true; // nunca deixa o período anterior à vista durante ou depois de uma falha
  carregando.hidden = false;
  botaoConsultar.disabled = true;

  try {
    const relatorio = await api.relatorioFrequencia(inicio.value, fim.value);
    if (numero !== consultaAtual) return;
    desenhar(relatorio);
  } catch (e) {
    if (numero !== consultaAtual) return;
    tratarErro(e);
  } finally {
    if (numero === consultaAtual) {
      carregando.hidden = true;
      botaoConsultar.disabled = false;
    }
  }
}

function tratarErro(e) {
  if (e.status === 401) return; // api.js já está levando para o login

  if (e.status === 422) {
    const erros = e.erros || {};
    let achouCampo = false;
    for (const campo of ['inicio', 'fim']) {
      if (!erros[campo]) continue;
      achouCampo = true;
      document.getElementById(`erro-${campo}`).textContent = erros[campo];
      document.getElementById(campo).classList.add('entrada--invalida');
    }
    mostrarMensagem(mensagem, achouCampo ? 'Corrija o período e consulte de novo.' : e.message, 'erro');
    return; // dado digitado errado: tentar de novo sem corrigir não adianta
  }

  mostrarMensagem(mensagem, e.message, 'erro');
  erroAcao.hidden = false;
}

function limparErros() {
  esconderMensagem(mensagem);
  erroAcao.hidden = true;
  [inicio, fim].forEach((campo) => {
    campo.classList.remove('entrada--invalida');
    document.getElementById(`erro-${campo.id}`).textContent = '';
  });
}

/** 66.67 -> "66,67%"; 100 -> "100%" */
const formatarPercentual = (p) => `${p.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

function desenhar({ periodo, frequenciaMinima, alunos }) {
  document.getElementById('periodo-texto').textContent = textoPeriodo(periodo);
  document.getElementById('minimo-texto').textContent =
    `Baixa frequência: abaixo de ${frequenciaMinima}% de presença.`;

  semAlunos.hidden = alunos.length > 0;
  tabela.hidden = alunos.length === 0;

  document.getElementById('linhas').innerHTML = alunos.map((a) => {
    const semMarcacao = a.percentual === null;
    const frequencia = semMarcacao
      ? '<span class="relatorio__sem">Sem registros</span>'
      : `<strong>${formatarPercentual(a.percentual)}</strong>`;
    const alerta = a.baixaFrequencia ? ' <span class="selo-baixa">Baixa frequência</span>' : '';
    return `
      <tr${a.baixaFrequencia ? ' class="relatorio__baixa"' : ''}>
        <th scope="row" class="relatorio__nome">${escapar(a.nome)}</th>
        <td class="relatorio__num" data-rotulo="Presenças">${a.presencas}</td>
        <td class="relatorio__num" data-rotulo="Faltas">${a.faltas}</td>
        <td class="relatorio__num" data-rotulo="Total de aulas">${a.total}</td>
        <td class="relatorio__num" data-rotulo="Frequência">${frequencia}${alerta}</td>
      </tr>`;
  }).join('');

  resultado.hidden = false;
}

function textoPeriodo({ inicio: de, fim: ate }) {
  if (de && ate) return `De ${formatarData(de)} até ${formatarData(ate)}`;
  if (de) return `A partir de ${formatarData(de)}`;
  if (ate) return `Até ${formatarData(ate)}`;
  return 'Todos os registros';
}

formulario.addEventListener('submit', (evento) => {
  evento.preventDefault();
  consultar();
});
document.getElementById('limpar-periodo').addEventListener('click', () => {
  inicio.value = '';
  fim.value = '';
  consultar();
});
document.getElementById('tentar-novamente').addEventListener('click', consultar);

iniciarPagina();
consultar();
