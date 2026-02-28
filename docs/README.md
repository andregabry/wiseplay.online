# WisePlay PHP (Docker-First)

Projeto novo em PHP puro para substituir a versao Next.js/Node, mantendo o comportamento de produto.

## Estado atual
- Runtime Docker com `php-apache` + `mysql` + `phpmyadmin`.
- Admin completo com tema Materialize (`/admin`) e CRUD real:
  - users, profiles, titles, media, billing/subscriptions, settings/OneDrive scaffold.
- Painel admin refinado com:
  - modais semanticos com icones,
  - efeito LED animado nos cards,
  - preview de imagens em formularios,
  - pagina de auto-perfil em `/admin/me`.
- Public app estilo streaming (login, perfis, browse, detalhes e watch) lendo dados reais do MySQL.
- Seguranca base: sessao, CSRF, prepared statements PDO, validacao server-side e auditoria.

## URLs locais (quando rodando)
- Public app: `http://localhost:8080`
- Public login: `http://localhost:8080/login`
- Admin login: `http://localhost:8080/admin/login`
- Admin dashboard: `http://localhost:8080/admin`
- Admin self-profile: `http://localhost:8080/admin/me`
- phpMyAdmin: `http://localhost:8081`

Setup recomendado: Docker Compose (`docs/SETUP.md`).
