# Déploiement VPS Docker — api.ont.digisafrica.tech

| | |
|--|--|
| **Host** | `digis@141.136.42.36` |
| **Domaine** | `https://api.ont.digisafrica.tech` |
| **Stack** | Docker (`php` + `nginx` + `mysql`) + Nginx hôte (TLS) |

---

## Architecture

```
Internet → Nginx hôte (:443 TLS)
              ↓ proxy_pass
         127.0.0.1:8080 → container nginx → php-fpm:9000
                              ↓
                           mysql:3306 (volume)
```

---

## 1. Une fois sur le VPS

```bash
ssh digis@141.136.42.36

# Dossier app
sudo mkdir -p /var/www/okapipass
sudo chown digis:digis /var/www/okapipass
cd /var/www/okapipass

git clone <URL_DU_REPO> .
# ou: git pull si déjà cloné

cp .env.vps.dist .env.vps
nano .env.vps   # secrets: APP_SECRET, MYSQL_*, FLEXPAY_*, etc.
```

### Nginx hôte (reverse proxy)

```bash
sudo cp docker/host-nginx/api.ont.digisafrica.tech.conf \
  /etc/nginx/sites-available/api.ont.digisafrica.tech.conf
sudo ln -sf /etc/nginx/sites-available/api.ont.digisafrica.tech.conf \
  /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# TLS (certbot déjà ou à installer)
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.ont.digisafrica.tech
```

### Lancer Docker

```bash
cd /var/www/okapipass
docker compose -f docker-compose.vps.yml --env-file .env.vps up -d --build

# Logs
docker compose -f docker-compose.vps.yml logs -f php
```

Vérifier :

```bash
curl -I http://127.0.0.1:8080/api/docs
curl -I https://api.ont.digisafrica.tech/api/docs
```

Seed démo agency (optionnel) :

```bash
docker compose -f docker-compose.vps.yml exec php \
  php bin/console app:seed-agency-portal
```

---

## 2. Mises à jour (git pull)

```bash
cd /var/www/okapipass
git pull
docker compose -f docker-compose.vps.yml --env-file .env.vps up -d --build
docker compose -f docker-compose.vps.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.vps.yml exec php php bin/console cache:clear
```

---

## 3. Secrets GitHub (workflow Deploy existant)

À adapter plus tard pour Docker :

| Secret | Exemple |
|--------|---------|
| `VPS_HOST` | `141.136.42.36` |
| `VPS_USER` | `digis` |
| `VPS_SSH_KEY` | clé privée |
| `VPS_PORT` | `22` |
| `VPS_APP_DIR` | `/var/www/okapipass` |

---

## 4. Ports

| Service | Bind | Usage |
|---------|------|--------|
| Host Nginx | 80 / 443 | Public TLS |
| Docker nginx | `127.0.0.1:8080` | App |
| MySQL | `127.0.0.1:3307` | Admin local only |
| Mailpit (opt.) | `127.0.0.1:8025` | `docker compose --profile mail …` |

---

## 5. Dépannage

```bash
docker compose -f docker-compose.vps.yml ps
docker compose -f docker-compose.vps.yml logs php --tail=100
docker compose -f docker-compose.vps.yml exec php php bin/console about
docker compose -f docker-compose.vps.yml exec php php bin/console doctrine:query:sql "SELECT 1"
```

Permissions :

```bash
docker compose -f docker-compose.vps.yml exec php chown -R www-data:www-data var public/media
```
