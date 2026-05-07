# Claude Code — miyi-app

See **[AGENTS.md](./AGENTS.md)** for stack, constraints (PHP 7.4.7, Laravel 8), architecture, and commands.

Quick copy-paste:

```bash
docker compose -f docker-compose.local.yml up -d
docker compose -f docker-compose.local.yml exec app php -v   # expect 7.4.7
docker compose -f docker-compose.local.yml exec app php artisan --version
```
