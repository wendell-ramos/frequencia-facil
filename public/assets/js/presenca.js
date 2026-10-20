// Tela de Presença — US02: marcar presente/ausente em uma data.
import { api } from './api.js';
import { mostrarMensagem, hoje, escapar, iniciarPagina } from './ui.js';

const inputData = document.getElementById('data');
const lista = document.getElementById('lista');
const msg = document.getElementById('mensagem');
const btnSalvar = document.getElementById('btn-salvar');
const btnTodos = document.getElementById('todos-presentes');

let alunos = [];

async function carregar() {
  lista.innerHTML = `<tr><td colspan="2" class="vazio">Carregando...</td></tr>`;
  try {
    const resposta = await api.presencasDoDia(inputData.value);
    alunos = resposta.alunos;
    desenhar();
  } catch (e) {
    mostrarMensagem(msg, `Não foi possível carregar: ${e.message}`, 'erro');
  }
}

function desenhar() {
  const temAlunos = alunos.length > 0;
  btnSalvar.hidden = btnTodos.hidden = !temAlunos;

  if (!temAlunos) {
    lista.innerHTML = `<tr><td colspan="2" class="vazio">
      Nenhum aluno cadastrado. <a href="alunos.html">Cadastre alunos</a> primeiro.</td></tr>`;
    return;
  }

  // Cada aluno tem dois rádios: presente / ausente
  lista.innerHTML = alunos.map((a) => `
    <tr>
      <td>${escapar(a.nome)}</td>
      <td>
        <div class="opcoes-presenca">
          <label><input type="radio" name="p-${a.alunoId}" value="1" ${a.presente === true ? 'checked' : ''}> Presente</label>
          <label><input type="radio" name="p-${a.alunoId}" value="0" ${a.presente === false ? 'checked' : ''}> Ausente</label>
        </div>
      </td>
    </tr>`).join('');
}

btnTodos.addEventListener('click', () => {
  lista.querySelectorAll('input[value="1"]').forEach((r) => { r.checked = true; });
});

btnSalvar.addEventListener('click', async () => {
  const registros = [];
  const semMarcar = [];

  for (const a of alunos) {
    const marcado = lista.querySelector(`input[name="p-${a.alunoId}"]:checked`);
    if (marcado) registros.push({ alunoId: a.alunoId, presente: marcado.value === '1' });
    else semMarcar.push(a.nome);
  }

  if (semMarcar.length) {
    mostrarMensagem(msg, `Marque presente ou ausente para: ${semMarcar.join(', ')}`, 'erro');
    return;
  }

  btnSalvar.disabled = true;
  try {
    await api.registrarPresenca(inputData.value, registros);
    mostrarMensagem(msg, `Chamada de ${inputData.value.split('-').reverse().join('/')} salva (${registros.length} alunos).`);
  } catch (e) {
    mostrarMensagem(msg, e.message, 'erro');
  } finally {
    btnSalvar.disabled = false;
  }
});

inputData.addEventListener('change', carregar);

// Início: data de hoje como padrão
iniciarPagina();
inputData.value = hoje();
carregar();
