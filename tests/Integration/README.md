# Testes de integração

Testam a API conversando com um banco de verdade.

No CI, o workflow sobe um MySQL como *service* (ver `.github/workflows/ci.yml`) e
exporta `DB_HOST=127.0.0.1`. Os testes daqui podem usar `App\Core\Database::connection()`
normalmente.

Sugestão para a Sprint 2: `AlunoApiTest.php` cadastrando um aluno e conferindo que ele
aparece na listagem (critério de aceite da US01).
