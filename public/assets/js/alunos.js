// Tela de Alunos — US01: cadastrar, listar, editar e excluir.
import { api } from './api.js';
import { mostrarMensagem, formatarData, escapar, iniciarPagina } from './ui.js';

const form = document.getElementById('form-aluno');
const campos = {
  id: document.getElementById('id'),
  nome: document.getElementById('nome'),
  telefone: document.getElementById('telefone'),
  dataNascimento: document.getElementById('dataNascimento'),
};
const lista = document.getElementById('lista');
const busca = document.getElementById('busca');
const msg = document.getElementById('mensagem');
const btnSalvar = document.getElementById('btn-salvar');
const btnCancelar = document.getElementById('btn-cancelar');
const tituloForm = document.getElementById('titulo-form');

let alunos = [];

// ---------- Lista ----------
async function carregar() {
  try {
    alunos = await api.listarAlunos(busca.value);
    desenhar();
  } catch (e) {
    mostrarMensagem(msg, `Não foi possível carregar os alunos: ${e.message}`, 'erro');
  }
}

function desenhar() {
  if (!alunos.length) {
    lista.innerHTML = `<tr><td colspan="4" class="vazio">Nenhum aluno encontrado.</td></tr>`;
    return;
  }
  lista.innerHTML = alunos.map((a) => `
    <tr>
      <td>${escapar(a.nome)}</td>
      <td>${escapar(a.telefone)}</td>
      <td>${formatarData(a.dataNascimento)}</td>
      <td class="acoes-linha">
        <a class="botao pequeno secundario" href="historico.html?id=${a.id}">Histórico</a>
        <button class="secundario pequeno" data-editar="${a.id}">Editar</button>
        <button class="perigo pequeno" data-excluir="${a.id}">Excluir</button>
      </td>
    </tr>`).join('');
}

// Cliques nos botões da tabela
lista.addEventListener('click', async (ev) => {
  const idEditar = ev.target.dataset.editar;
  const idExcluir = ev.target.dataset.excluir;

  if (idEditar) {
    const aluno = alunos.find((a) => a.id === Number(idEditar));
    entrarModoEdicao(aluno);
  }

  if (idExcluir) {
    const aluno = alunos.find((a) => a.id === Number(idExcluir));
    if (!confirm(`Excluir ${aluno.nome}? As presenças dele também serão apagadas.`)) return;
    try {
      await api.excluirAluno(aluno.id);
      mostrarMensagem(msg, 'Aluno excluído.');
      if (campos.id.value === String(aluno.id)) sairModoEdicao();
      carregar();
    } catch (e) {
      mostrarMensagem(msg, e.message, 'erro');
    }
  }
});

// Busca enquanto digita (espera o usuário parar por 300ms)
let timerBusca;
busca.addEventListener('input', () => {
  clearTimeout(timerBusca);
  timerBusca = setTimeout(carregar, 300);
});

// ---------- Formulário ----------
form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  limparErros();

  const dados = {
    nome: campos.nome.value,
    telefone: campos.telefone.value,
    dataNascimento: campos.dataNascimento.value || null,
  };
  const editando = Boolean(campos.id.value);

  btnSalvar.disabled = true;
  try {
    if (editando) {
      await api.editarAluno(campos.id.value, dados);
      mostrarMensagem(msg, 'Aluno atualizado.');
    } else {
      await api.criarAluno(dados);
      mostrarMensagem(msg, 'Aluno cadastrado.');
    }
    sairModoEdicao();
    carregar();
  } catch (e) {
    mostrarErros(e);
  } finally {
    btnSalvar.disabled = false;
  }
});

btnCancelar.addEventListener('click', sairModoEdicao);

function entrarModoEdicao(aluno) {
  limparErros();
  campos.id.value = aluno.id;
  campos.nome.value = aluno.nome;
  campos.telefone.value = aluno.telefone;
  campos.dataNascimento.value = aluno.dataNascimento || '';
  tituloForm.textContent = `Editando: ${aluno.nome}`;
  btnSalvar.textContent = 'Salvar alterações';
  btnCancelar.hidden = false;
  campos.nome.focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function sairModoEdicao() {
  form.reset();
  campos.id.value = '';
  limparErros();
  tituloForm.textContent = 'Novo aluno';
  btnSalvar.textContent = 'Cadastrar';
  btnCancelar.hidden = true;
}

// Mostra o erro embaixo de cada campo (422) ou no topo (409, 500...)
function mostrarErros(e) {
  const detalhes = e.detalhes || {};
  for (const [campo, texto] of Object.entries(detalhes)) {
    campos[campo]?.classList.add('invalido');
    const el = form.querySelector(`[data-erro="${campo}"]`);
    if (el) el.textContent = texto;
  }
  mostrarMensagem(msg, e.message, 'erro');
}

function limparErros() {
  form.querySelectorAll('.erro-campo').forEach((el) => { el.textContent = ''; });
  Object.values(campos).forEach((c) => c.classList.remove('invalido'));
  msg.hidden = true;
}

// ---------- Início ----------
iniciarPagina();
carregar();
