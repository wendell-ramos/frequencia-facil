// ============================================================
// Comunicação com a API — todas as telas usam este arquivo.
// Contrato dos endpoints: docs/api.md
//
// A API fica em <APP_BASE_PATH>/api/. O PHP grava a base em <body data-base="...">,
// então funciona no Docker (base vazia) e no XAMPP (/frequencia-facil) sem configurar nada.
//
// USAR_MOCK = true  -> dados de mentira salvos no navegador (localStorage)
// USAR_MOCK = false -> chama a API PHP de verdade (<base>/api/...)
// Troque para false quando a API do Wendell estiver pronta.
// ============================================================
export const USAR_MOCK = true;

// ---------- API de verdade ----------
async function request(method, path, body) {
  const res = await fetch(`${document.body.dataset.base || ''}/api${path}`, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = res.status === 204 ? null : await res.json().catch(() => null);
  if (!res.ok) throw erroApi(data?.mensagem || `Erro ${res.status}`, res.status, data?.erros);
  return data;
}

// Erro da API no formato { "mensagem": "...", "erros": { "campo": "..." } }
function erroApi(mensagem, status = 400, erros = {}) {
  const err = new Error(mensagem);
  err.status = status;
  err.erros = erros || {};
  return err;
}

const apiReal = {
  listarAlunos:      (busca = '')   => request('GET', `/alunos${busca ? `?busca=${encodeURIComponent(busca)}` : ''}`),
  buscarAluno:       (id)           => request('GET', `/alunos/${id}`),
  criarAluno:        (dados)        => request('POST', '/alunos', dados),
  editarAluno:       (id, dados)    => request('PUT', `/alunos/${id}`, dados),
  excluirAluno:      (id)           => request('DELETE', `/alunos/${id}`),
  presencasDoDia:    (data)         => request('GET', `/presencas?data=${data}`),
  registrarPresenca: (data, registros) => request('POST', '/presencas', { data, registros }),
  historico: (id, inicio = '', fim = '') => request('GET', `/alunos/${id}/presencas?inicio=${inicio}&fim=${fim}`),
};

// ---------- Mock (imita a API seguindo docs/api.md) ----------
const ler = (chave, padrao) => {
  try { return JSON.parse(localStorage.getItem(chave)) ?? padrao; } catch { return padrao; }
};
const gravar = (chave, valor) => localStorage.setItem(chave, JSON.stringify(valor));
const esperar = () => new Promise((r) => setTimeout(r, 150)); // simula a rede

function alunosMock() {
  let alunos = ler('ff_alunos', null);
  if (!alunos) {
    alunos = [
      { id: 1, nome: 'Ana Souza',    telefone: '(18) 99711-0001', dataNascimento: '2004-03-12' },
      { id: 2, nome: 'Bruno Lima',   telefone: '(18) 99711-0002', dataNascimento: '2003-08-25' },
      { id: 3, nome: 'Carla Mendes', telefone: '(18) 99711-0003', dataNascimento: null },
    ];
    gravar('ff_alunos', alunos);
  }
  return alunos;
}

// Mesmas regras que o backend vai ter (US01)
function validarAluno(dados, alunos, idAtual = null) {
  const erros = {};
  const nome = (dados.nome || '').trim();
  const telefone = (dados.telefone || '').trim();

  if (!nome) erros.nome = 'Informe o nome';
  else if (nome.length < 3) erros.nome = 'O nome precisa ter pelo menos 3 letras';

  if (!telefone) erros.telefone = 'Informe o telefone';
  else if (telefone.replace(/\D/g, '').length < 10) erros.telefone = 'Telefone inválido (DDD + número)';

  if (dados.dataNascimento && dados.dataNascimento > new Date().toISOString().slice(0, 10)) {
    erros.dataNascimento = 'A data de nascimento não pode ser no futuro';
  }

  if (Object.keys(erros).length) throw erroApi('Dados inválidos', 422, erros);

  const duplicado = alunos.some((a) =>
    a.id !== idAtual &&
    a.nome.toLowerCase() === nome.toLowerCase() &&
    a.telefone.replace(/\D/g, '') === telefone.replace(/\D/g, ''));
  if (duplicado) throw erroApi('Já existe um aluno com este nome e telefone', 409);

  return { nome, telefone, dataNascimento: dados.dataNascimento || null };
}

const apiMock = {
  async listarAlunos(busca = '') {
    await esperar();
    const termo = busca.trim().toLowerCase();
    return alunosMock()
      .filter((a) => !termo || a.nome.toLowerCase().includes(termo))
      .sort((a, b) => a.nome.localeCompare(b.nome));
  },

  async buscarAluno(id) {
    await esperar();
    const aluno = alunosMock().find((a) => a.id === Number(id));
    if (!aluno) throw erroApi('Aluno não encontrado', 404);
    return aluno;
  },

  async criarAluno(dados) {
    await esperar();
    const alunos = alunosMock();
    const limpo = validarAluno(dados, alunos);
    const novo = { id: Math.max(0, ...alunos.map((a) => a.id)) + 1, ...limpo };
    gravar('ff_alunos', [...alunos, novo]);
    return novo;
  },

  async editarAluno(id, dados) {
    await esperar();
    const alunos = alunosMock();
    if (!alunos.some((a) => a.id === Number(id))) throw erroApi('Aluno não encontrado', 404);
    const limpo = validarAluno(dados, alunos, Number(id));
    const atualizado = { id: Number(id), ...limpo };
    gravar('ff_alunos', alunos.map((a) => (a.id === Number(id) ? atualizado : a)));
    return atualizado;
  },

  async excluirAluno(id) {
    await esperar();
    // O banco (FOREIGN KEY ... ON DELETE RESTRICT) não deixa excluir aluno com histórico.
    // Política ainda a confirmar com o PO — ver docs/api.md.
    if (ler('ff_presencas', []).some((p) => p.alunoId === Number(id))) {
      throw erroApi('Não é possível excluir: o aluno já tem presenças registradas.', 409);
    }
    gravar('ff_alunos', alunosMock().filter((a) => a.id !== Number(id)));
    return null;
  },

  async presencasDoDia(data) {
    await esperar();
    const presencas = ler('ff_presencas', []);
    const alunos = alunosMock().sort((a, b) => a.nome.localeCompare(b.nome));
    return {
      data,
      alunos: alunos.map((a) => {
        const p = presencas.find((x) => x.alunoId === a.id && x.dataAula === data);
        return { alunoId: a.id, nome: a.nome, presente: p ? p.presente : null };
      }),
    };
  },

  async registrarPresenca(data, registros) {
    await esperar();
    if (!data) throw erroApi('Informe a data da aula', 422);
    let presencas = ler('ff_presencas', []);
    for (const r of registros) {
      // Mesmo aluno + mesma data: substitui o registro anterior (US02)
      presencas = presencas.filter((p) => !(p.alunoId === r.alunoId && p.dataAula === data));
      presencas.push({ alunoId: r.alunoId, dataAula: data, presente: r.presente });
    }
    gravar('ff_presencas', presencas);
    return { mensagem: 'Presença salva', total: registros.length };
  },

  async historico(id, inicio = '', fim = '') {
    await esperar();
    const aluno = alunosMock().find((a) => a.id === Number(id));
    if (!aluno) throw erroApi('Aluno não encontrado', 404);
    const registros = ler('ff_presencas', [])
      .filter((p) => p.alunoId === aluno.id)
      .filter((p) => (!inicio || p.dataAula >= inicio) && (!fim || p.dataAula <= fim))
      .sort((a, b) => b.dataAula.localeCompare(a.dataAula))
      .map(({ dataAula, presente }) => ({ dataAula, presente }));
    const presencas = registros.filter((r) => r.presente).length;
    return {
      aluno: { id: aluno.id, nome: aluno.nome },
      totais: { presencas, faltas: registros.length - presencas, total: registros.length },
      registros,
    };
  },
};

export const api = USAR_MOCK ? apiMock : apiReal;
