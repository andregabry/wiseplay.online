SET NAMES utf8mb4;

-- Password for all seed users: 123456
-- Hash: $2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.

INSERT INTO users (id, name, email, password_hash, account_avatar, role, status, email_verified_at, password_set_at, onboarding_completed_at, preferences_completed_at, created_at, updated_at, last_login_at) VALUES
(1, 'Andre WisePlay', 'admin@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-01.svg', 'SUPER_ADMIN', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 95 DAY), DATE_SUB(NOW(), INTERVAL 95 DAY), DATE_SUB(NOW(), INTERVAL 120 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'Luciana Operacoes', 'luciana@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-02.svg', 'ADMIN', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 100 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'Bruno Conteudo', 'bruno@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-03.svg', 'EDITOR', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 95 DAY), DATE_SUB(NOW(), INTERVAL 95 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 95 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(4, 'Camila Suporte', 'camila@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-04.svg', 'SUPPORT', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 88 DAY), DATE_SUB(NOW(), INTERVAL 88 DAY), DATE_SUB(NOW(), INTERVAL 74 DAY), DATE_SUB(NOW(), INTERVAL 74 DAY), DATE_SUB(NOW(), INTERVAL 88 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'Rafael Billing', 'rafael@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-05.svg', 'BILLING_MANAGER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 'Marina Teste', 'marina@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-06.svg', 'CUSTOMER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 52 DAY), DATE_SUB(NOW(), INTERVAL 52 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 'Thiago Viewer', 'thiago@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-07.svg', 'CUSTOMER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(8, 'Beatriz Kids', 'beatriz@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-08.svg', 'CUSTOMER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(9, 'Danilo Sandbox', 'danilo@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-09.svg', 'CUSTOMER', 'DISABLED', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW(), NULL),
(10, 'Paula Creator', 'paula@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-10.svg', 'PRODUCER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 75 DAY), DATE_SUB(NOW(), INTERVAL 75 DAY), DATE_SUB(NOW(), INTERVAL 65 DAY), DATE_SUB(NOW(), INTERVAL 65 DAY), DATE_SUB(NOW(), INTERVAL 75 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(11, 'Eduardo Studio', 'eduardo@wiseplay.local', '$2y$10$CfRt8b1jEpbbJJO5h6.g5uMcHCWbefMXsWYmR4gf5AV0rmy/cc98.', '/assets/img/avatar-library/avatar-11.svg', 'PRODUCER', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 9 HOUR));

INSERT INTO producers (id, owner_user_id, brand_name, slug, status, created_at, updated_at) VALUES
(1, 10, 'CodeForge Academy', 'codeforge-academy', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 70 DAY), NOW()),
(2, 11, 'Cinema Atlas Studio', 'cinema-atlas-studio', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 68 DAY), NOW());

INSERT INTO producer_members (id, producer_id, user_id, member_role, status, created_at, updated_at) VALUES
(1, 1, 3, 'EDITOR', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 60 DAY), NOW()),
(2, 2, 4, 'ANALYST', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 58 DAY), NOW());

INSERT INTO billing_plans (id, plan_name, slug, price, currency, billing_cycle, profile_limit, max_streams, quality_level, active, is_selectable, requires_payment_method, sort_order, created_at, updated_at) VALUES
(1, 'Free', 'free', 0.00, 'BRL', 'MONTHLY', 3, 1, '720p', 1, 1, 0, 10, DATE_SUB(NOW(), INTERVAL 200 DAY), NOW()),
(2, 'Standard', 'standard', 29.90, 'BRL', 'MONTHLY', 3, 2, '1080p', 1, 1, 1, 20, DATE_SUB(NOW(), INTERVAL 200 DAY), NOW()),
(3, 'Premium', 'premium', 49.90, 'BRL', 'MONTHLY', 5, 4, '4K', 1, 1, 1, 30, DATE_SUB(NOW(), INTERVAL 200 DAY), NOW()),
(4, 'Pagamento unico', 'pagamento-unico', 129.90, 'BRL', 'ONE_TIME', 1, 1, '1080p', 1, 1, 1, 40, DATE_SUB(NOW(), INTERVAL 120 DAY), NOW());

INSERT INTO subscriptions (id, user_id, plan_id, status, start_at, end_at, auto_renew, payment_required, payment_status, payment_provider, payment_reference, created_at, updated_at) VALUES
(1, 1, 3, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 110 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), 1, 1, 'PAID', 'mock', 'seed-premium-1', DATE_SUB(NOW(), INTERVAL 110 DAY), NOW()),
(2, 2, 3, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY), 1, 1, 'PAID', 'mock', 'seed-premium-2', DATE_SUB(NOW(), INTERVAL 100 DAY), NOW()),
(3, 3, 2, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, 1, 'PAID', 'mock', 'seed-standard-3', DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(4, 4, 2, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 88 DAY), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 1, 'PAID', 'mock', 'seed-standard-4', DATE_SUB(NOW(), INTERVAL 88 DAY), NOW()),
(5, 5, 3, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 80 DAY), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, 1, 'PAID', 'mock', 'seed-premium-5', DATE_SUB(NOW(), INTERVAL 80 DAY), NOW()),
(6, 6, 1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, 0, 'NOT_REQUIRED', NULL, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY), NOW()),
(7, 7, 2, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 1, 'PAID', 'mock', 'seed-standard-7', DATE_SUB(NOW(), INTERVAL 55 DAY), NOW()),
(8, 8, 1, 'TRIALING', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY), 1, 0, 'NOT_REQUIRED', NULL, NULL, DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
(9, 9, 1, 'PAUSED', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 0, 0, 'NOT_REQUIRED', NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW());

INSERT INTO payment_methods (id, user_id, provider, token_ref, brand, last4, exp_month, exp_year, is_default, created_at, updated_at) VALUES
(1, 1, 'CARD', 'pm_seed_admin_visa', 'VISA', '7580', 3, 2028, 1, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(2, 1, 'CARD', 'pm_seed_admin_mc', 'MASTERCARD', '1024', 9, 2029, 0, DATE_SUB(NOW(), INTERVAL 70 DAY), NOW()),
(3, 2, 'CARD', 'pm_seed_luciana_visa', 'VISA', '8801', 12, 2027, 1, DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(4, 3, 'CARD', 'pm_seed_bruno_amex', 'AMEX', '4477', 6, 2028, 1, DATE_SUB(NOW(), INTERVAL 60 DAY), NOW()),
(5, 7, 'CARD', 'pm_seed_thiago_mc', 'MASTERCARD', '3159', 11, 2030, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), NOW());

INSERT INTO profiles (id, user_id, profile_name, avatar_path, is_kids, maturity_rating, language, autoplay, created_at, updated_at) VALUES
(1, 1, 'Andre', '/assets/img/avatar-library/avatar-01.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 100 DAY), NOW()),
(2, 1, 'Kids', '/assets/img/avatar-library/avatar-10.svg', 1, '12', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(3, 1, 'Viagens', '/assets/img/avatar-library/avatar-11.svg', 0, '16', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 94 DAY), NOW()),
(4, 2, 'Luciana', '/assets/img/avatar-library/avatar-02.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 96 DAY), NOW()),
(5, 2, 'Casa', '/assets/img/avatar-library/avatar-12.svg', 1, '10', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 92 DAY), NOW()),
(6, 3, 'Bruno', '/assets/img/avatar-library/avatar-03.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 85 DAY), NOW()),
(7, 3, 'Study', '/assets/img/avatar-library/avatar-04.svg', 0, '16', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 82 DAY), NOW()),
(8, 4, 'Camila', '/assets/img/avatar-library/avatar-05.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 82 DAY), NOW()),
(9, 5, 'Rafael', '/assets/img/avatar-library/avatar-06.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 75 DAY), NOW()),
(10, 5, 'Filhos', '/assets/img/avatar-library/avatar-07.svg', 1, '12', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 74 DAY), NOW()),
(11, 5, 'Cursos', '/assets/img/avatar-library/avatar-08.svg', 0, '14', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 73 DAY), NOW()),
(12, 6, 'Marina', '/assets/img/avatar-library/avatar-09.svg', 0, '16', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 54 DAY), NOW()),
(13, 7, 'Thiago', '/assets/img/avatar-library/avatar-10.svg', 0, '18', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 52 DAY), NOW()),
(14, 7, 'Casa', '/assets/img/avatar-library/avatar-11.svg', 1, '12', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 51 DAY), NOW()),
(15, 7, 'Animes', '/assets/img/avatar-library/avatar-12.svg', 0, '14', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 51 DAY), NOW()),
(16, 8, 'Beatriz', '/assets/img/avatar-library/avatar-02.svg', 0, '16', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW()),
(17, 9, 'Danilo', '/assets/img/avatar-library/avatar-03.svg', 0, '16', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 19 DAY), NOW());

INSERT INTO profile_preferences (id, profile_id, preference_type, preference_key, weight, created_at, updated_at) VALUES
(1, 1, 'GENRE', 'Sci-Fi', 120, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(2, 1, 'GENRE', 'Tecnologia', 110, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(3, 1, 'TYPE', 'SERIES', 110, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(4, 1, 'TOPIC', 'Programming', 100, DATE_SUB(NOW(), INTERVAL 95 DAY), NOW()),
(5, 2, 'GENRE', 'Documentario', 100, DATE_SUB(NOW(), INTERVAL 93 DAY), NOW()),
(6, 2, 'TYPE', 'MOVIE', 100, DATE_SUB(NOW(), INTERVAL 93 DAY), NOW()),
(7, 3, 'GENRE', 'Crime', 100, DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(8, 3, 'TYPE', 'SERIES', 100, DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(9, 6, 'TYPE', 'COURSE', 120, DATE_SUB(NOW(), INTERVAL 70 DAY), NOW()),
(10, 6, 'TOPIC', 'Programming', 120, DATE_SUB(NOW(), INTERVAL 70 DAY), NOW()),
(11, 7, 'TOPIC', 'Programming', 120, DATE_SUB(NOW(), INTERVAL 50 DAY), NOW()),
(12, 7, 'TYPE', 'COURSE', 110, DATE_SUB(NOW(), INTERVAL 50 DAY), NOW()),
(13, 12, 'TOPIC', 'Programming', 120, DATE_SUB(NOW(), INTERVAL 52 DAY), NOW()),
(14, 13, 'GENRE', 'Crime', 100, DATE_SUB(NOW(), INTERVAL 39 DAY), NOW()),
(15, 13, 'TYPE', 'SERIES', 100, DATE_SUB(NOW(), INTERVAL 39 DAY), NOW()),
(16, 14, 'GENRE', 'Anime', 100, DATE_SUB(NOW(), INTERVAL 40 DAY), NOW()),
(17, 14, 'TYPE', 'SERIES', 100, DATE_SUB(NOW(), INTERVAL 40 DAY), NOW()),
(18, 16, 'GENRE', 'Documentario', 100, DATE_SUB(NOW(), INTERVAL 8 DAY), NOW()),
(19, 16, 'TYPE', 'MOVIE', 100, DATE_SUB(NOW(), INTERVAL 8 DAY), NOW());

INSERT INTO titles (id, producer_id, billing_plan_id, title_name, slug, type, description, genre, year, maturity_rating, featured, status, cover_image, backdrop_image, created_by_user_id, created_at, updated_at) VALUES
(1, 2, 1, 'Projeto Horizonte', 'projeto-horizonte', 'MOVIE', 'Filme sci-fi sobre exploracao orbital e conspiracoes.', 'Sci-Fi', 2024, '14', 1, 'PUBLISHED', '/assets/img/avatar-library/avatar-04.svg', '/assets/img/avatar-library/avatar-05.svg', 11, DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(2, 2, 1, 'Noite no Centro', 'noite-no-centro', 'MOVIE', 'Thriller policial ambientado em Sao Paulo.', 'Thriller', 2023, '16', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-06.svg', '/assets/img/avatar-library/avatar-07.svg', 11, DATE_SUB(NOW(), INTERVAL 80 DAY), NOW()),
(3, 2, 1, 'Planeta Azul', 'planeta-azul', 'MOVIE', 'Documentario sobre oceanos e preservacao.', 'Documentario', 2022, '10', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-08.svg', '/assets/img/avatar-library/avatar-09.svg', 11, DATE_SUB(NOW(), INTERVAL 70 DAY), NOW()),
(4, 2, 2, 'Cidade Codificada', 'cidade-codificada', 'SERIES', 'Serie de investigacao digital em 2 temporadas.', 'Crime', 2024, '16', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-10.svg', '/assets/img/avatar-library/avatar-11.svg', 11, DATE_SUB(NOW(), INTERVAL 65 DAY), NOW()),
(5, 2, 1, 'Hospital 24h', 'hospital-24h', 'SERIES', 'Drama medico com casos semanais.', 'Drama', 2023, '14', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-12.svg', '/assets/img/avatar-library/avatar-01.svg', 11, DATE_SUB(NOW(), INTERVAL 60 DAY), NOW()),
(6, 2, 1, 'Rota 404', 'rota-404', 'SERIES', 'Time de ciberseguranca enfrenta ataques em tempo real.', 'Tecnologia', 2025, '14', 1, 'PUBLISHED', '/assets/img/avatar-library/avatar-02.svg', '/assets/img/avatar-library/avatar-03.svg', 11, DATE_SUB(NOW(), INTERVAL 55 DAY), NOW()),
(7, 1, 4, 'PHP do Zero ao Deploy', 'php-do-zero-ao-deploy', 'COURSE', 'Curso completo de backend com foco em deploy real.', 'Educacao', 2025, 'Livre', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-04.svg', '/assets/img/avatar-library/avatar-05.svg', 10, DATE_SUB(NOW(), INTERVAL 50 DAY), NOW()),
(8, 1, 4, 'JavaScript Profissional', 'javascript-profissional', 'COURSE', 'Fundamentos e arquitetura para frontend moderno.', 'Educacao', 2024, 'Livre', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-06.svg', '/assets/img/avatar-library/avatar-07.svg', 10, DATE_SUB(NOW(), INTERVAL 45 DAY), NOW()),
(9, 1, 1, 'UX para Streaming', 'ux-para-streaming', 'COURSE', 'Pesquisa, UI e experimentacao para apps de video.', 'Design', 2024, 'Livre', 0, 'PUBLISHED', '/assets/img/avatar-library/avatar-08.svg', '/assets/img/avatar-library/avatar-09.svg', 10, DATE_SUB(NOW(), INTERVAL 40 DAY), NOW());

INSERT INTO media_items (id, producer_id, title_id, season_number, episode_number, item_label, duration_minutes, source_type, source_path, external_id, subtitles, audio_lang, is_published, created_at, updated_at) VALUES
(1, 2, 1, NULL, NULL, 'Projeto Horizonte - Filme', 128, 'URL', 'https://example.com/watch/projeto-horizonte', NULL, 'pt-BR,en', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 89 DAY), NOW()),
(2, 2, 2, NULL, NULL, 'Noite no Centro - Filme', 112, 'URL', 'https://example.com/watch/noite-no-centro', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 79 DAY), NOW()),
(3, 2, 3, NULL, NULL, 'Planeta Azul - Filme', 96, 'URL', 'https://example.com/watch/planeta-azul', NULL, 'pt-BR,en,es', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 69 DAY), NOW()),
(4, 2, 4, 1, 1, 'S1E1 - Vazamento', 48, 'URL', 'https://example.com/watch/cidade-codificada-s1e1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 64 DAY), NOW()),
(5, 2, 4, 1, 2, 'S1E2 - Espelho', 46, 'URL', 'https://example.com/watch/cidade-codificada-s1e2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 64 DAY), NOW()),
(6, 2, 4, 2, 1, 'S2E1 - Reconstrucao', 50, 'URL', 'https://example.com/watch/cidade-codificada-s2e1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 61 DAY), NOW()),
(7, 2, 4, 2, 2, 'S2E2 - Chave Mestra', 52, 'URL', 'https://example.com/watch/cidade-codificada-s2e2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 61 DAY), NOW()),
(8, 2, 5, 1, 1, 'S1E1 - Plantao', 43, 'URL', 'https://example.com/watch/hospital-24h-s1e1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 59 DAY), NOW()),
(9, 2, 5, 1, 2, 'S1E2 - Trauma', 44, 'URL', 'https://example.com/watch/hospital-24h-s1e2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 59 DAY), NOW()),
(10, 2, 6, 1, 1, 'S1E1 - Zero Day', 42, 'URL', 'https://example.com/watch/rota-404-s1e1', NULL, 'pt-BR,en', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 54 DAY), NOW()),
(11, 2, 6, 1, 2, 'S1E2 - Backdoor', 45, 'URL', 'https://example.com/watch/rota-404-s1e2', NULL, 'pt-BR,en', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 54 DAY), NOW()),
(12, 1, 7, 1, 1, 'Modulo 1 / Licao 1 - Ambiente', 25, 'URL', 'https://example.com/watch/php-zero-mod1-lic1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
(13, 1, 7, 1, 2, 'Modulo 1 / Licao 2 - Rotas', 30, 'URL', 'https://example.com/watch/php-zero-mod1-lic2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
(14, 1, 7, 2, 1, 'Modulo 2 / Licao 1 - PDO', 35, 'URL', 'https://example.com/watch/php-zero-mod2-lic1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 48 DAY), NOW()),
(15, 1, 8, 1, 1, 'Modulo 1 / Licao 1 - Sintaxe Moderna', 22, 'URL', 'https://example.com/watch/js-prof-mod1-lic1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 44 DAY), NOW()),
(16, 1, 8, 1, 2, 'Modulo 1 / Licao 2 - Assincrono', 26, 'URL', 'https://example.com/watch/js-prof-mod1-lic2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 44 DAY), NOW()),
(17, 1, 9, 1, 1, 'Modulo 1 / Licao 1 - Pesquisa', 18, 'URL', 'https://example.com/watch/ux-stream-mod1-lic1', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 39 DAY), NOW()),
(18, 1, 9, 1, 2, 'Modulo 1 / Licao 2 - Prototipos', 20, 'URL', 'https://example.com/watch/ux-stream-mod1-lic2', NULL, 'pt-BR', 'pt-BR', 1, DATE_SUB(NOW(), INTERVAL 39 DAY), NOW());

INSERT INTO media_materials (id, producer_id, media_item_id, title, file_name, source_type, one_drive_item_id, one_drive_path, download_url, mime_type, file_size_bytes, sort_order, is_downloadable, created_at, updated_at) VALUES
(1, 1, 12, 'Slides - Ambiente', 'slides-ambiente.pdf', 'URL', NULL, NULL, 'https://example.com/materials/php-zero-mod1-lic1/slides.pdf', 'application/pdf', 1843200, 10, 1, DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
(2, 1, 12, 'Arquivos iniciais', 'starter-files.zip', 'URL', NULL, NULL, 'https://example.com/materials/php-zero-mod1-lic1/starter-files.zip', 'application/zip', 7340032, 20, 1, DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
(3, 1, 13, 'Checklist da aula', 'checklist-rotas.pdf', 'URL', NULL, NULL, 'https://example.com/materials/php-zero-mod1-lic2/checklist.pdf', 'application/pdf', 842752, 10, 1, DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
(4, 1, 14, 'Exercicios PDO', 'pdo-exercicios.zip', 'URL', NULL, NULL, 'https://example.com/materials/php-zero-mod2-lic1/exercicios.zip', 'application/zip', 9437184, 10, 1, DATE_SUB(NOW(), INTERVAL 48 DAY), NOW()),
(5, 1, 15, 'Guia assíncrono', 'guia-assincrono.pdf', 'URL', NULL, NULL, 'https://example.com/materials/js-prof-mod1-lic1/guia.pdf', 'application/pdf', 1201152, 10, 1, DATE_SUB(NOW(), INTERVAL 44 DAY), NOW()),
(6, 1, 17, 'Template de pesquisa', 'template-pesquisa.docx', 'URL', NULL, NULL, 'https://example.com/materials/ux-stream-mod1-lic1/template.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 610304, 10, 1, DATE_SUB(NOW(), INTERVAL 39 DAY), NOW());

INSERT INTO offers (id, producer_id, title_id, offer_type, billing_interval, currency, price, status, is_selectable, created_at, updated_at) VALUES
(1, 1, 7, 'ONE_TIME', NULL, 'BRL', 149.90, 'ACTIVE', 1, DATE_SUB(NOW(), INTERVAL 21 DAY), NOW()),
(2, 1, 7, 'SUBSCRIPTION', 'MONTH', 'BRL', 39.90, 'ACTIVE', 0, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW()),
(3, 1, 7, 'SUBSCRIPTION', 'YEAR', 'BRL', 349.90, 'ACTIVE', 0, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW()),
(4, 1, 8, 'ONE_TIME', NULL, 'BRL', 129.90, 'ACTIVE', 1, DATE_SUB(NOW(), INTERVAL 18 DAY), NOW()),
(5, 2, 4, 'SUBSCRIPTION', 'MONTH', 'BRL', 24.90, 'ACTIVE', 1, DATE_SUB(NOW(), INTERVAL 16 DAY), NOW()),
(6, 2, 4, 'SUBSCRIPTION', 'YEAR', 'BRL', 249.90, 'ACTIVE', 0, DATE_SUB(NOW(), INTERVAL 16 DAY), NOW());

INSERT INTO purchases (id, user_id, producer_id, title_id, offer_id, status, provider, provider_reference, created_at, paid_at) VALUES
(1, 6, 1, 7, 1, 'PAID', 'infinitepay', 'seed-course-7-marina', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 7, 2, 4, 5, 'PAID', 'infinitepay', 'seed-series-4-thiago', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO entitlements (id, user_id, producer_id, title_id, source, start_at, end_at, status, created_at) VALUES
(1, 6, 1, 7, 'ONE_TIME', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 7, 2, 4, 'SUBSCRIPTION', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_ADD(NOW(), INTERVAL 21 DAY), 'ACTIVE', DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO settings (id, category, key_name, value_text, is_secret, created_at, updated_at) VALUES
(1, 'general', 'app.name', 'WisePlay', 0, NOW(), NOW()),
(2, 'general', 'app.home_tagline', 'Seu streaming inteligente para filmes, series e cursos.', 0, NOW(), NOW()),
(3, 'general', 'app.kids_default_maturity', '12', 0, NOW(), NOW()),
(4, 'onedrive', 'onedrive.client_id', '', 0, NOW(), NOW()),
(5, 'onedrive', 'onedrive.client_secret', '', 1, NOW(), NOW()),
(6, 'onedrive', 'onedrive.redirect_uri', 'http://localhost:8080/admin/settings/onedrive/callback', 0, NOW(), NOW()),
(7, 'onedrive', 'onedrive.authority', 'consumers', 0, NOW(), NOW()),
(8, 'onedrive', 'onedrive.scopes', 'openid offline_access profile User.Read Files.ReadWrite', 0, NOW(), NOW()),
(9, 'onedrive', 'onedrive.last_connection_status', 'Not connected', 0, NOW(), NOW()),
(10, 'onedrive', 'onedrive.last_connected_at', '', 0, NOW(), NOW()),
(11, 'onedrive', 'onedrive.root_folder', 'WisePlay', 0, NOW(), NOW()),
(12, 'onedrive', 'onedrive.last_upload_at', '', 0, NOW(), NOW()),
(13, 'onedrive', 'onedrive.access_token_expires_at', '', 0, NOW(), NOW()),
(14, 'onedrive', 'onedrive.quota_cache_json', '', 0, NOW(), NOW()),
(15, 'onedrive', 'onedrive.quota_cache_at', '', 0, NOW(), NOW()),
(16, 'onedrive', 'onedrive.upload_debug', '0', 0, NOW(), NOW()),
(17, 'onedrive', 'onedrive.upload_chunk_mb', '10', 0, NOW(), NOW()),
(18, 'onedrive', 'onedrive.upload_max_retries', '3', 0, NOW(), NOW()),
(19, 'onedrive', 'onedrive.upload_retry_backoff_ms', '500', 0, NOW(), NOW()),
(41, 'onedrive', 'onedrive.upload_max_video_gb', '10', 0, NOW(), NOW()),
(20, 'auth', 'auth.verification_code_ttl_minutes', '10', 0, NOW(), NOW()),
(21, 'auth', 'auth.verification_code_max_attempts', '5', 0, NOW(), NOW()),
(22, 'auth', 'auth.verification_resend_cooldown_seconds', '60', 0, NOW(), NOW()),
(23, 'auth', 'auth.password_min_length', '8', 0, NOW(), NOW()),
(24, 'auth', 'auth.verification_session_ttl_minutes', '30', 0, NOW(), NOW()),
(25, 'smtp', 'smtp.enabled', '1', 0, NOW(), NOW()),
(26, 'smtp', 'smtp.host', 'smtp.hostinger.com', 0, NOW(), NOW()),
(27, 'smtp', 'smtp.port', '465', 0, NOW(), NOW()),
(28, 'smtp', 'smtp.username', 'noreply@wiseplay.online', 0, NOW(), NOW()),
(29, 'smtp', 'smtp.password', '', 1, NOW(), NOW()),
(30, 'smtp', 'smtp.encryption', 'ssl', 0, NOW(), NOW()),
(31, 'smtp', 'smtp.from_email', 'noreply@wiseplay.online', 0, NOW(), NOW()),
(32, 'smtp', 'smtp.from_name', 'WisePlay', 0, NOW(), NOW()),
(33, 'email', 'email.verification_subject', 'WisePlay - Codigo de verificacao', 0, NOW(), NOW()),
(34, 'email', 'email.verification_template', 'Ola {{name}},\n\nSeu codigo de verificacao WisePlay: {{code}}\nEste codigo expira em {{expires_minutes}} minutos.\n\nSe voce nao solicitou este cadastro, ignore este email.', 0, NOW(), NOW()),
(35, 'email', 'email.dev_log_delivery', '0', 0, NOW(), NOW()),
(36, 'payment', 'payment.gateway_enabled', '0', 0, NOW(), NOW()),
(37, 'payment', 'payment.provider', 'mock', 0, NOW(), NOW()),
(38, 'payment', 'payment.mode', 'sandbox', 0, NOW(), NOW()),
(39, 'payment', 'payment.mock_auto_approve', '1', 0, NOW(), NOW()),
(40, 'payment', 'payment.public_paid_visible', '1', 0, NOW(), NOW()),
(42, 'onboarding', 'onboarding.preferences_allow_skip', '1', 0, NOW(), NOW()),
(43, 'playback', 'playback.autosave_interval_seconds', '15', 0, NOW(), NOW()),
(44, 'playback', 'playback.completion_threshold_percent', '92', 0, NOW(), NOW()),
(45, 'playback', 'playback.show_next_countdown_seconds', '8', 0, NOW(), NOW()),
(46, 'smtp', 'smtp.incoming_imap_host', 'imap.hostinger.com', 0, NOW(), NOW()),
(47, 'smtp', 'smtp.incoming_imap_port', '993', 0, NOW(), NOW()),
(48, 'smtp', 'smtp.incoming_imap_encryption', 'ssl', 0, NOW(), NOW()),
(49, 'smtp', 'smtp.incoming_pop_host', 'pop.hostinger.com', 0, NOW(), NOW()),
(50, 'smtp', 'smtp.incoming_pop_port', '995', 0, NOW(), NOW()),
(51, 'smtp', 'smtp.incoming_pop_encryption', 'ssl', 0, NOW(), NOW()),
(52, 'smtp', 'smtp.outgoing_host', 'smtp.hostinger.com', 0, NOW(), NOW()),
(53, 'smtp', 'smtp.outgoing_port', '465', 0, NOW(), NOW()),
(54, 'smtp', 'smtp.outgoing_encryption', 'ssl', 0, NOW(), NOW());

INSERT INTO setting_profiles (id, category, config_name, description, payload_json, is_default, is_active, created_at, updated_at) VALUES
(1, 'general', 'Production', 'Configuracao geral padrao da aplicacao', JSON_OBJECT('app_name','WisePlay','app_url','https://wiseplay.online','default_language','pt-PT','timezone','Europe/Lisbon','maintenance_mode','0'), 1, 1, NOW(), NOW()),
(2, 'email', 'Hostinger SMTP', 'Canal principal de envio de emails transacionais', JSON_OBJECT('smtp_host','smtp.hostinger.com','smtp_port','465','smtp_encryption','ssl','smtp_username','noreply@wiseplay.online','smtp_password','','sender_email','noreply@wiseplay.online','sender_name','WisePlay'), 1, 1, NOW(), NOW()),
(3, 'payment', 'Stripe Sandbox', 'Gateway de pagamento para ambiente de testes', JSON_OBJECT('provider','stripe','mode','test','currency','BRL','public_key','','secret_key','','webhook_url',''), 1, 1, NOW(), NOW());

INSERT INTO watch_history (id, profile_id, title_id, media_item_id, progress_percent, duration_seconds, last_position_seconds, completed, completed_at, last_watched_at, created_at, updated_at) VALUES
(1, 13, 4, 5, 72, 2760, 1980, 0, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()),
(2, 13, 6, 10, 21, 2520, 540, 0, NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR), NOW()),
(3, 12, 7, 13, 89, 1800, 1520, 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(4, 16, 3, 3, 45, 5760, 1180, 0, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), NOW());

INSERT INTO public_notifications (id, entity_type, entity_id, title_text, message_text, link_url, created_at) VALUES
(1, 'TITLE', 6, 'Novo titulo no catalogo', 'Rota 404', '/browse#row-series', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 'COURSE', 7, 'Novo curso no catalogo', 'PHP do Zero ao Deploy', '/browse#row-course', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(3, 'SEASON', 4, 'Nova temporada liberada', 'Cidade Codificada - Temporada 2', '/browse#row-series', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'EPISODE', 11, 'Novo episodio disponivel', 'Rota 404 - Temporada 1, episodio 2', '/watch/11', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'EPISODE', 14, 'Nova licao disponivel', 'PHP do Zero ao Deploy - Modulo 2, licao 1', '/watch/14', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO public_notification_reads (notification_id, user_id, read_at) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 2, DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, diff_summary, ip, user_agent, created_at)
VALUES (1, 'seed.bootstrap', 'system', NULL, '{"ok":true}', '127.0.0.1', 'seed.sql', NOW());
