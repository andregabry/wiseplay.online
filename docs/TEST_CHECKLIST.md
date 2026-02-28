# WisePlay Test Checklist (Docker-First)

## 1) Subir e resetar ambiente
```bash
docker compose up -d --build
docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/schema.sql"
docker compose exec -T app sh -lc "mysql --skip-ssl -h mysql -uwiseplay_user -pwiseplay_pass wiseplay_php < database/seed.sql"
```

## 2) Lint PHP no container
```bash
docker compose exec app sh -lc "find app public -name '*.php' -type f -print0 | xargs -0 -n1 php -l"
```

## 3) Smoke HTTP
```bash
curl -i http://localhost:8080/login
curl -i http://localhost:8080/admin/login
curl -i http://localhost:8080/browse
```

Esperado:
- `/login` e `/admin/login` com `200`.
- `/browse` sem sessao redireciona para `/login`.

## 4) Login e fluxo publico
1. Abrir `http://localhost:8080/login`.
2. Entrar com `admin@wiseplay.local / 123456`.
3. Selecionar perfil em `/profiles`.
4. Validar `/browse` com:
   - hero em destaque,
   - linha "Continue assistindo",
   - linhas por tipo/genero.
5. Abrir um titulo em `/title/{id}` e validar episodios/licoes.
6. Abrir `/watch/{media_id}` e testar:
   - progresso por slider/botoes,
   - sugestoes laterais.
7. Em `/profiles?manage=1`, validar:
   - criacao de perfil adulto/kids,
   - bloqueio amigavel quando atingir limite do plano (free=1, standard=3, premium=5).

## 5) Admin CRUD (cada modulo)
Entrar em `http://localhost:8080/admin` com usuario admin.

Para cada modulo (`users`, `profiles`, `titles`, `media`, `billing`, `settings`):
1. Filtrar/pesquisar na listagem.
2. Criar item via modal.
3. Editar item via menu de 3 pontos.
4. Excluir item via SweetAlert2.
5. Confirmar feedback de sucesso/erro via SweetAlert2.

Checks extras:
- `users`: nao permitir auto-delete/auto-disable.
- `users`: validar avatar da conta + plano + contador de perfis no card.
- `/admin/me`: validar edicao do proprio nome/email/senha/avatar.
- `media`: trocar tipo de titulo no modal e validar campos dependentes de temporada/episodio.
- `billing`: atribuir assinatura para usuario.
- `settings`: salvar OneDrive + testar botao "Test connection".

## 6) Auditoria
Após operacoes no admin, abrir dashboard e verificar "Auditoria recente".

## 7) Logs e parada
```bash
docker compose logs -f app
docker compose logs -f mysql
docker compose down
```

Reset total (inclui volume MySQL):
```bash
docker compose down -v
docker compose up -d --build
```
