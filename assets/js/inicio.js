// Tela inicial: data de hoje e situação da chamada do dia.
import { api } from './api.js';
import { hoje, formatarDataExtenso, plural, iniciarPagina, url } from './ui.js';

iniciarPagina();

const data = hoje();
const situacao = document.getElementById('situacao');
const botao = document.getElementById('botao-principal');

document.getElementById('data-hoje').textContent = formatarDataExtenso(data);

try {
  const [alunos, chamada] = await Promise.all([api.listarAlunos(), api.presencasDoDia(data)]);
  const marcados = chamada.alunos.filter((a) => a.presente !== null);
  const presentes = marcados.filter((a) => a.presente).length;

  if (!alunos.length) {
    situacao.textContent = 'Nenhum aluno cadastrado ainda. Comece cadastrando a turma.';
    botao.textContent = 'Cadastrar alunos';
    botao.href = url('/alunos');
  } else if (!marcados.length) {
    situacao.textContent = `A chamada de hoje ainda não foi feita. A turma tem ${plural(alunos.length, 'aluno', 'alunos')}.`;
  } else {
    situacao.textContent = `Chamada de hoje registrada: ${plural(presentes, 'presente', 'presentes')} e ${plural(marcados.length - presentes, 'falta', 'faltas')}.`;
    botao.textContent = 'Revisar a chamada de hoje';
  }
} catch {
  situacao.textContent = 'Não foi possível carregar a turma. Verifique se o servidor está no ar e recarregue a página.';
}
