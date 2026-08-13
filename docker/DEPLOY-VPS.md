# Déploiement VPS Docker (MySQL) — api.ont.digisafrica.tech

| | |
|--|--|
| **SSH** | `digis@141.136.42.36` |
| **Domaine** | `https://api.ont.digisafrica.tech` |
| **DB** | **MySQL 8** (pas Postgres) |
| **Stack** | `php` + `nginx` + `mysql` (+ Nginx hôte TLS) |

---

## Architecture

```
Internet → Nginx hôte (:443)
              ↓
         127.0.0.1:8080 → nginx (Docker) → php-fpm
                              ↓
                         mysql:3306 (volume Docker)
```

---

## Contenu Docker

| Élément | Fichier / service |
|---------|-------------------|
| PHP 8.4-FPM + `pdo_mysql` | `docker/php/Dockerfile` |
| Entrypoint (wait MySQL, JWT, composer, migrations) | `docker/php/docker-entrypoint.sh` |
| Nginx app | `docker/nginx/default.conf` |
| MySQL 8 utf8mb4 | service `mysql` + `docker/mysql/conf.d/` |
| Env | `.env.vps` (depuis `.env.vps.dist`) |
| Reverse proxy hôte | `docker/host-nginx/api.ont.digisafrica.tech.conf` |

---

## 1. Clone + env

```bash
ssh digis@141.136.42.36
cd /var/www/okapipass   # déjà cloné

cp .env.vps.dist .env.vps
nano .env.vps
```

À renseigner obligatoirement :

- `APP_SECRET` → `openssl rand -hex 32`
- `MYSQL_ROOT_PASSWORD` / `MYSQL_PASSWORD` (identiques dans `DATABASE_URL`)
- `DATABASE_URL=mysql://okapi:MOTDEPASSE@mysql:3306/okapipass?serverVersion=8.0&charset=utf8mb4`
- `FLEXPAY_*`, `ULTRAMSG_*`, `MAILER_*` selon besoin
- `JWT_PASSPHRASE=` **vide** (clés générées sans passphrase)

> Host MySQL dans Docker = **`mysql`** (nom du service), jamais `127.0.0.1`.

---

## 2. Nginx hôte + TLS

```bash
sudo cp docker/host-nginx/api.ont.digisafrica.tech.conf \
  /etc/nginx/sites-available/api.ont.digisafrica.tech.conf
sudo ln -sf /etc/nginx/sites-available/api.ont.digisafrica.tech.conf \
  /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api.ont.digisafrica.tech
```

---

## 3. Lancer la stack

```bash
cd /var/www/okapipass
docker compose -f docker-compose.vps.yml --env-file .env.vps up -d --build
docker compose -f docker-compose.vps.yml logs -f php
```

Checks :

```bash
docker compose -f docker-compose.vps.yml exec php php bin/console doctrine:query:sql "SELECT 1"
curl -I http://127.0.0.1:8080/api/docs
curl -I https://api.ont.digisafrica.tech/api/docs
```

Seed agency (optionnel) :

```bash
docker compose -f docker-compose.vps.yml exec php php bin/console app:seed-agency-portal
```

---

## 4. Update

```bash
cd /var/www/okapipass
git pull
docker compose -f docker-compose.vps.yml --env-file .env.vps up -d --build
docker compose -f docker-compose.vps.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.vps.yml exec php php bin/console cache:clear
```

---

## 5. Ports

| Bind | Service |
|------|---------|
| 80/443 | Nginx hôte |
| `127.0.0.1:8080` | App Docker |
| `127.0.0.1:3307` | MySQL (admin) |

Connexion MySQL depuis le VPS :

```bash
mysql -h 127.0.0.1 -P 3307 -u okapi -p okapipass
```

---

## 6. Local (optionnel)

```bash
# compose.yaml = MySQL aussi
docker compose up -d database
# DATABASE_URL dans .env.local → 127.0.0.1:3306
```
