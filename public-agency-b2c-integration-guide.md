# Guide d'intégration — Boutique B2C agence (API publique)

| | |
|--|--|
| **Base URL prod** | `https://api.ont.digisafrica.tech` |
| **Préfixe** | `/api/public/agency/*` |
| **Auth** | Aucune (endpoints publics) |
| **Format** | JSON (`application/json`) |

---

## Flow voyageur

```
1. GET  /offers                    → recherche trajets
2. GET  /offers/{id}/seats         → plan de bus + sièges libres
3. GET  /offers/{id}/quote         → devis billet + Pass ONT
4. POST /bookings                  → hold siège (15 min par défaut)
5. POST /bookings/{token}/pay      → FlexPay Mobile Money ou Carte
6. POST /bookings/{token}/pay/check-status  → polling si webhook tardif
7. GET  /bookings/{token}/ticket     → billet VP- + QR
8. GET  /bookings/{token}/ticket/pdf → PDF téléchargeable
```

---

## Endpoints

### Catalogue

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/public/agency/health` | Healthcheck |
| GET | `/api/public/agency/offers` | Liste offres online (`?origin=&destination=&agencyId=`) |
| GET | `/api/public/agency/offers/{id}` | Détail offre |
| GET | `/api/public/agency/offers/{id}/quote?okapiPassRef=` | Devis |
| GET | `/api/public/agency/offers/{id}/seats?travelDate=YYYY-MM-DD` | Sièges disponibles |

### Réservation

**POST `/api/public/agency/bookings`** → 201

```json
{
  "offerId": "AO…",
  "travelDate": "2026-09-15",
  "seatNumber": "01A",
  "passengerName": "Jean Kabongo",
  "passengerId": "ID-12345",
  "passengerPhone": "+243812345678",
  "okapiPassRef": "OP-OPTIONAL"
}
```

Réponse : `publicToken`, `expiresAt`, `quote`, `status: PENDING`.

**GET `/api/public/agency/bookings/{publicToken}`** — statut réservation.

**POST `/api/public/agency/bookings/{publicToken}/cancel`** — annule si non payée.

### Paiement FlexPay

**POST `/api/public/agency/bookings/{publicToken}/pay`**

```json
{ "method": "MOBILE_MONEY" }
```

ou `{ "method": "CARD" }` → utiliser `cardFormUrl` retourné (redirect navigateur).

**POST `/api/public/agency/bookings/{publicToken}/pay/check-status`** — interroge FlexPay et finalise si payé.

Webhook unifié (backend) : `POST /api/payments/webhook/flexpay` (FlexPay → serveur, pas le front).

### Billet

**GET `/api/public/agency/bookings/{publicToken}/ticket`** — JSON (réf VP-, QR, `pdfUrl`).

**GET `/api/public/agency/bookings/{publicToken}/ticket/pdf`** — PDF binaire.

---

## Rate limiting

Par IP (fenêtre 60 s) :

| Endpoint | Limite |
|----------|--------|
| POST `/bookings` | 10 / min |
| POST `/bookings/{token}/pay` | 5 / min |

Réponse `429` avec header `Retry-After`.

---

## Paiement carte — redirects front

Configurer sur le VPS (`.env.vps`) :

```env
FLEXPAY_AGENCY_CARD_APPROVE_URL=https://votre-front/agency/booking/success?token={ref}
FLEXPAY_AGENCY_CARD_CANCEL_URL=https://votre-front/agency/booking/cancelled?token={ref}
FLEXPAY_AGENCY_CARD_DECLINE_URL=https://votre-front/agency/booking/failed?token={ref}&reason={reason}
```

`{ref}` = `publicToken` de la réservation.

---

## CORS

Autoriser le domaine front dans `CORS_ALLOW_ORIGIN` (`.env.vps`).

---

## Prérequis côté agence (portail partenaire)

Activer la vente online par offre :

```http
PATCH /api/agency/offers/{id}
Authorization: Bearer {jwt}
{ "onlineSales": true, "bookingHoldMinutes": 15 }
```

---

## Cron serveur

Libérer les holds expirés (toutes les minutes) :

```cron
* * * * * cd /var/www/okapipass && docker compose -f docker-compose.vps.yml --env-file .env.vps exec -T php php bin/console app:expire-public-agency-bookings >> /var/www/okapipass/var/log/expire-bookings.log 2>&1
```
