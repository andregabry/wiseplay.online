# Setup (Docker First)

## Requisitos
- Docker Desktop
- Docker Compose v2 (`docker compose`)

## 1) Variaveis
```bash
cp .env.example .env
```

Valores esperados para Docker no `.env`:
```dotenv
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wiseplay_php
DB_USERNAME=wiseplay_user
DB_PASSWORD=wiseplay_pass
```

## 2) Subir containers
```bash
docker compose up -d --build
```

## 3) Banco (bootstrap)
Criar banco (idempotente):
```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS wiseplay_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Importar schema (via container `app`, que tem os arquivos montados):
```bash
docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/schema.sql"
```

Importar seed:
```bash
docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/seed.sql"
```

## 4) Testes rapidos
Lint PHP dentro do container da app:
```bash
docker compose exec app sh -lc "find app public -name '*.php' -type f -print0 | xargs -0 -n1 php -l"
```

Smoke test (rotas principais):
```bash
curl -i http://localhost:8080/login
curl -i http://localhost:8080/admin/login
curl -i http://localhost:8080/admin
curl -i http://localhost:8080/admin/me
```

## 5) Acessos
- App: `http://localhost:8080/login`
- Admin: `http://localhost:8080/admin/login`
- phpMyAdmin: `http://localhost:8081`

Credenciais seed:
- `admin@wiseplay.local`
- `123456`

## 6) Logs e ciclo de vida
Subir:
```bash
docker compose up -d
```

Logs:
```bash
docker compose logs -f app
docker compose logs -f mysql
```

Parar:
```bash
docker compose stop
```

Derrubar stack:
```bash
docker compose down
```

Reset total (inclui banco):
```bash
docker compose down -v
```

## 7) Troubleshooting
- Porta `8080` ocupada: altere `app.ports` em `docker-compose.yml` (ex.: `8090:80`).
- Porta `3307` ocupada: altere `mysql.ports` (ex.: `3308:3306`) ou remova o publish se nao precisar acessar MySQL pelo host.
- Erro de conexao com DB na app: confirme `DB_HOST=mysql` e `DB_PORT=3306` no `.env`.
- Tela 404/Apache servindo pasta errada: confirme `DocumentRoot /var/www/html/public` em `docker/php/000-default.conf`.
- Falha de permissao em `storage/`: ajuste dono/permissao no host (`chmod -R u+rw storage`).
- Seed nao aplicou: rode novamente os imports via `app`:
  - `docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/schema.sql"`
  - `docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/seed.sql"`
- Mudou Dockerfile e nao refletiu: rebuild com `docker compose up -d --build app`.

Checklist funcional completo (admin + public): `docs/TEST_CHECKLIST.md`.
