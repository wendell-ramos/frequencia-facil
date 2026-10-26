// Tela de login — card #10. Contrato em docs/autenticacao.md.
// A senha só existe no campo do formulário e na requisição; nada vai para o localStorage.
import { auth } from './api.js';
import { mostrarMensagem, esconderMensagem, iniciarPagina } from './ui.js';

const formulario = document.getElementById('formulario-login');
const email = document.getElementById('email');
const senha = document.getElementById('senha');
const botaoEntrar = document.getElementById('botao-entrar');
const botaoMostrar = document.getElementById('mostrar-senha');
const mensagem = document.getElementById('mensagem');
const base = document.body.dataset.base || '';

/** Para onde ir depois de entrar: só caminhos internos (evita redirecionar para outro site). */
function destino() {
  const volta = new URLSearchParams(location.search).get('volta') || '/';
  const seguro = volta.startsWith('/') && !volta.startsWith('//') && !volta.startsWith('/\\') && !volta.startsWith('/login');
  return base + (seguro ? volta : '/');
}

// Já está logado? Vai direto para o sistema.
auth.sessao()
  .then((sessao) => { if (sessao.autenticado) location.replace(destino()); })
  .catch(() => mostrarMensagem(mensagem, 'Não foi possível conectar ao servidor. Verifique a conexão e tente de novo.', 'erro'));

botaoMostrar.addEventListener('click', () => {
  const mostrar = senha.type === 'password';
  senha.type = mostrar ? 'text' : 'password';
  botaoMostrar.textContent = mostrar ? 'Ocultar' : 'Mostrar';
  botaoMostrar.setAttribute('aria-pressed', String(mostrar));
});

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limparErros();

  // Validação rápida na tela; o servidor valida de novo.
  const erros = {};
  if (!email.value.trim()) erros.email = 'Informe o e-mail.';
  else if (!email.checkValidity()) erros.email = 'Informe um e-mail válido.';
  if (!senha.value) erros.senha = 'Informe a senha.';
  if (Object.keys(erros).length) return mostrarErros({ erros });

  botaoEntrar.disabled = true;
  botaoEntrar.textContent = 'Entrando…';
  try {
    await auth.entrar(email.value.trim(), senha.value);
    senha.value = '';
    location.replace(destino());
  } catch (e) {
    senha.value = '';
    // 401 traz sempre "E-mail ou senha inválidos." (não revela se a conta existe ou está bloqueada)
    mostrarErros(e);
    botaoEntrar.disabled = false;
    botaoEntrar.textContent = 'Entrar';
    (e.erros?.email ? email : senha).focus();
  }
});

function mostrarErros(e) {
  const campos = { email, senha };
  for (const [campo, texto] of Object.entries(e.erros || {})) {
    campos[campo]?.classList.add('entrada--invalida');
    campos[campo]?.setAttribute('aria-invalid', 'true');
    const alvo = formulario.querySelector(`[data-erro="${campo}"]`);
    if (alvo) alvo.textContent = texto;
  }
  if (e.message) mostrarMensagem(mensagem, e.message, 'erro');
}

function limparErros() {
  esconderMensagem(mensagem);
  formulario.querySelectorAll('.erro-campo').forEach((el) => { el.textContent = ''; });
  [email, senha].forEach((c) => { c.classList.remove('entrada--invalida'); c.removeAttribute('aria-invalid'); });
}

iniciarPagina();
email.focus();
