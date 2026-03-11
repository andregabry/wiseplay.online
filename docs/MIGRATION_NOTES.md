# Migration Notes

## Referencias funcionais mapeadas da base anterior
- Rotas publicas: `/login`, `/browse`, `/title/:id`, `/manage`
- Rotas admin: `/admin`, `/admin/users`, `/admin/profiles`, `/admin/titles`, `/admin/media`, `/admin/billing`, `/admin/settings`
- Entidades de dados reconstruidas em SQL manual: users, profiles, titles, seasons, episodes, media_assets, plans, subscriptions, settings, audit_logs
- Fluxo OneDrive Personal: authority `consumers`, OAuth v2.0

## Rebuild strategy
1. Criar base limpa em PHP (feito nesta etapa)
2. Migrar auth e protecao admin
3. Migrar modulos admin com modal CRUD + SweetAlert2
4. Migrar frontend publico Netflix-like
5. Finalizar OneDrive settings + OAuth scaffold + testes

## Pattern reutilizavel (implementado em `/admin/users`)
- Controller com:
  - `index` (filtros + paginacao server-side)
  - `create/update/toggle/delete` (JSON para AJAX + validacao server-side)
- Model com:
  - `paginate`, `findById`, `emailExists`, `create`, `update`, `setStatus`, `delete`
- View com:
  - toolbar + filtros
  - cards por entidade (nao tabela)
  - menu de acoes (3 pontos) por card
  - modais create/edit/detail
- JS (jQuery) com:
  - event delegation
  - submit AJAX
  - confirmacoes e feedback via SweetAlert2
  - render de erros de validacao no modal
- Auditoria:
  - log por acao mutavel (`create/update/toggle/delete`) em `audit_logs`

Esse mesmo padrao sera replicado para `profiles`, `titles`, `media`, `billing` e `settings`.

## Diferencas esperadas
- Sessao PHP no lugar de autenticacao acoplada a framework
- Controllers e rotas POST no lugar de actions proprietarias
- PDO + SQL manual no lugar de ORM gerado
