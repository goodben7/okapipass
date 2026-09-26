# Vague 1 — Intégration front (E1 / E3 / E2)

| Champ | Valeur |
|-------|--------|
| **Scope** | OTP voyageur, POS + cash collect, promos |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926120000` |
| **SMS** | Dream Digital (`DREAM_DIGITAL_SMS_ENABLED=1`) ou stub log. En `dev`/`test`, OTP renvoie `debugCode`. |

### Dream Digital (SMS)

```env
DREAM_DIGITAL_SMS_ENABLED=1
DREAM_DIGITAL_BASE_URL=https://YOUR_API_URL   # sans slash final
DREAM_DIGITAL_API_ID=API…
DREAM_DIGITAL_API_PASSWORD=…
DREAM_DIGITAL_SENDER_ID=VotreSenderID
DREAM_DIGITAL_SMS_TYPE=T   # Transactional
DREAM_DIGITAL_ENCODING=T   # Text
```

Envoi : `POST {BASE_URL}/api/SendSMS` (JSON). Téléphone normalisé **sans `+`** (ex. `243810012345`).

**Vérifier que ça marche :**

```bash
# 1) Unit (mock HTTP — toujours OK en CI)
php bin/phpunit tests/Unit/Service/Agency/DreamDigitalSmsSenderTest.php

# 2) Envoi réel (recommandé)
php bin/console okapi:test-sms +243823783066

# 3) Live PHPUnit (optionnel)
DREAM_DIGITAL_LIVE_TEST=1 DREAM_DIGITAL_LIVE_PHONE=+243823783066 \
  php bin/phpunit tests/Functional/Sms/DreamDigitalLiveSmsTest.php
```

---

## E1 — Voyageur (OTP + billets)

```http
POST /api/public/auth/otp/request
{ "phone": "+243810012345" }

POST /api/public/auth/otp/verify
{ "phone": "+243810012345", "code": "123456" }
→ { "token", "userId", "phone" }

GET  /api/traveler/me
PATCH /api/traveler/me   { "displayName", "email" }
GET  /api/traveler/tickets?scope=upcoming|past|cancelled
GET  /api/traveler/tickets/{id}
POST /api/traveler/tickets/{id}/share   { "toPhone": "+243…" }
```

Auth : `Authorization: Bearer {token}` + rôle `ROLE_TRAVELER`.  
Historique = billets dont `passengerPhone` matche le téléphone du compte.  
PDF existant : `/api/public/agency/tickets/{id}/pdf`.

---

## E3 — POS + cash collect

Nouveaux rôles staff : `SELLER_BUS`, `SELLER_CASH`, `SUPERVISOR` (+ permissions `pos:write`, `cash:collect`).

```http
POST /api/agency/pos/sessions/open     { "pointOfSale": "Guichet-1" }
POST /api/agency/pos/sales
{
  "session": "PS…",
  "offer": "/api/agency/offers/AO…",
  "passengerName": "…",
  "passengerId": "…",
  "passengerPhone": "+243…",
  "seatNumber": "01A",
  "travelDate": "2026-10-01",
  "method": "CASH",
  "promoCode": "MBIYO10"
}
POST /api/agency/pos/sessions/{id}/close
POST /api/agency/pos/cash-handovers     { "session": "PS…", "declaredAmount": 15000 }
POST /api/agency/pos/cash-handovers/{id}/confirm
POST /api/agency/pos/cash-handovers/{id}/reject   { "reason": "…" }
```

Vente = booking + issue ticket + payment (canal `POS`).

---

## E2 — Promos / fidélité (simple)

```http
GET/POST  /api/agency/promotions
PATCH     /api/agency/promotions/{id}
POST      /api/agency/promotions/validate
{ "code": "MBIYO10", "ticketPrice": 10000 }
→ { "discountAmount": 1000, "finalTicketPrice": 9000, … }

GET/POST  /api/agency/loyalty/rules
PATCH     /api/agency/loyalty/rules/{id}
```

Quote public (offre online) : `GET /api/public/agency/offers/{id}/quote?promoCode=MBIYO10`  
→ champs `discountAmount`, `promoCode`, `finalTicketPrice`.

Billet : `discountAmount`, `promoCode`, `loyaltyRule`.

---

## Hors scope Vague 1

Wallet, multi-jours, précommandes, offline POS sync, surprises aléatoires, ledger compta.
