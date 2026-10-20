// Tela de Alunos — US01 (cadastrar) e item 3 (listar, editar, excluir).
import { api } from './api.js';
import {
  mostrarMensagem, esconderMensagem, formatarData, escapar, plural,
  aplicarMascaraTelefone, iniciarPagina, url,
} from './ui.js';

const formulario = document.getElementById('formulario');
const blocoFormulario = document.getElementById('bloco-formulario');
const tituloFormulario = document.getElementById('titulo-formulario');
const botaoSalvar = document.getElementById('botao-salvar');
const botaoCancelar = document.getElementById('botao-cancelar');
const campos = {
  id: document.getElementById('id'),
  nome: document.getElementById('nome'),
  telefone: document.getElementById('telefone'),
  dataNascimento: document.getElementById('dataNascimento'),
};
const lista = document.getElementById('lista');
const busca = document.getElementById('busca');
const contagem = document.getElementById('contagem');
const mensagem = document.getElementById('mensagem');
const dialogo = document.getElementById('dialogo-exclusao');

let alunos = [];

// ---------- Lista ----------
async function carregar() {
  try {
    alunos = await api.listarAlunos(busca.value);
    desenhar();
  } catch (e) {
    mostrarMensagem(mensagem, `Não foi possível carregar os alunos: ${e.message}`, 'erro');
  }
}

function desenhar() {
  const termo = busca.value.trim();
  contagem.textContent = termo
    ? `${plural(alunos.length, 'resultado', 'resultados')} para "${termo}"`
    : `${plural(alunos.length, 'aluno', 'alunos')} na turma`;

  if (!alunos.length) {
    lista.innerHTML = `<li class="vazio">${termo
      ? 'Nenhum aluno com esse nome.'
      : 'Nenhum aluno cadastrado. Use o formulário para cadastrar o primeiro.'}</li>`;
    return;
  }

  const idEditando = campos.id.value;
  lista.innerHTML = alunos.map((a) => `
    <li class="aluno ${String(a.id) === idEditando ? 'aluno--editando' : ''}">
      <div>
        <span class="aluno__nome">${escapar(a.nome)}</span>
        <span class="aluno__detalhes">
          <span>${escapar(a.telefone)}</span>
          ${a.dataNascimento ? `<span>Nascimento ${formatarData(a.dataNascimento)}</span>` : ''}
        </span>
      </div>
      <div class="aluno__acoes">
        <a class="botao botao--secundario botao--pequeno" href="${url(`/historico?id=${a.id}`)}">Histórico</a>
        <button class="botao botao--secundario botao--pequeno" type="button" data-editar="${a.id}">Editar</button>
        <button class="botao botao--perigo botao--pequeno" type="button" data-excluir="${a.id}">Excluir</button>
      </div>
    </li>`).join('');
}

lista.addEventListener('click', (evento) => {
  const botao = evento.target.closest('button');
  if (!botao) return;
  const aluno = alunos.find((a) => a.id === Number(botao.dataset.editar || botao.dataset.excluir));
  if (!aluno) return;
  if (botao.dataset.editar) entrarModoEdicao(aluno);
  if (botao.dataset.excluir) confirmarExclusao(aluno);
});

// Busca enquanto digita (espera 300 ms depois da última tecla)
let temporizadorBusca;
busca.addEventListener('input', () => {
  clearTimeout(temporizadorBusca);
  temporizadorBusca = setTimeout(carregar, 300);
});

// ---------- Exclusão (com diálogo de confirmação) ----------
function confirmarExclusao(aluno) {
  document.getElementById('dialogo-texto').textContent =
    `${aluno.nome} será removido da turma. Essa ação não pode ser desfeita.`;
  dialogo.returnValue = '';
  dialogo.showModal();
  dialogo.addEventListener('close', async () => {
    if (dialogo.returnValue !== 'confirmar') return;
    try {
      await api.excluirAluno(aluno.id);
      if (campos.id.value === String(aluno.id)) sairModoEdicao();
      mostrarMensagem(mensagem, `${aluno.nome} foi excluído.`);
      carregar();
    } catch (e) {
      mostrarMensagem(mensagem, e.message, 'erro');
    }
  }, { once: true });
}

// ---------- Formulário: cadastrar e editar ----------
aplicarMascaraTelefone(campos.telefone);

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limparErros();

  const dados = {
    nome: campos.nome.value,
    telefone: campos.telefone.value,
    dataNascimento: campos.dataNascimento.value || null,
  };
  const editando = Boolean(campos.id.value);

  botaoSalvar.disabled = true;
  try {
    const aluno = editando
      ? await api.editarAluno(campos.id.value, dados)
      : await api.criarAluno(dados);

    // Limpa o formulário ANTES de mostrar a confirmação,
    // porque sairModoEdicao() também esconde a mensagem.
    sairModoEdicao();
    mostrarMensagem(mensagem, editando ? `Alterações de ${aluno.nome} salvas.` : `${aluno.nome} foi cadastrado.`);
    await carregar();
    campos.nome.focus();
  } catch (e) {
    mostrarErros(e);
  } finally {
    botaoSalvar.disabled = false;
  }
});

botaoCancelar.addEventListener('click', () => {
  sairModoEdicao();
  desenhar();
});

function entrarModoEdicao(aluno) {
  limparErros();
  campos.id.value = aluno.id;
  campos.nome.value = aluno.nome;
  campos.telefone.value = aluno.telefone;
  campos.dataNascimento.value = aluno.dataNascimento || '';
  tituloFormulario.textContent = `Editar ${aluno.nome}`;
  botaoSalvar.textContent = 'Salvar alterações';
  botaoCancelar.hidden = false;
  blocoFormulario.classList.add('formulario--editando');
  desenhar();
  blocoFormulario.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  campos.nome.focus({ preventScroll: true });
}

function sairModoEdicao() {
  formulario.reset();
  campos.id.value = '';
  limparErros();
  tituloFormulario.textContent = 'Novo aluno';
  botaoSalvar.textContent = 'Cadastrar aluno';
  botaoCancelar.hidden = true;
  blocoFormulario.classList.remove('formulario--editando');
}

// Erros por campo (422) aparecem embaixo de cada campo; os demais, no topo.
function mostrarErros(e) {
  const erros = e.erros || {};
  let primeiro = null;
  for (const [campo, texto] of Object.entries(erros)) {
    const entrada = campos[campo];
    if (entrada) {
      entrada.classList.add('entrada--invalida');
      entrada.setAttribute('aria-invalid', 'true');
      primeiro ??= entrada;
    }
    const alvo = formulario.querySelector(`[data-erro="${campo}"]`);
    if (alvo) alvo.textContent = texto;
  }
  if (primeiro) primeiro.focus();
  else mostrarMensagem(mensagem, e.message, 'erro');
}

function limparErros() {
  formulario.querySelectorAll('.erro-campo').forEach((el) => { el.textContent = ''; });
  Object.values(campos).forEach((c) => {
    c.classList.remove('entrada--invalida');
    c.removeAttribute('aria-invalid');
  });
  esconderMensagem(mensagem);
}

// ---------- Início ----------
iniciarPagina();
carregar();
