# Guide d'intégration front — Boutique B2C agence (API publique)

| Champ | Valeur |
|-------|--------|
| **Audience** | Dev front (Next.js, Vite, etc.) — parcours **voyageur** sans compte |
| **Backend** | API Platform / Symfony — préfixe `/api/public/agency/*` |
| **Base URL prod** | `https://api.ont.digisafrica.tech` |
| **Auth** | Aucune — endpoints `PUBLIC_ACCESS` |
| **Format** | JSON (`application/json`) |
| **Statut** | Backend prêt MVP — 46 tests PHPUnit |
| **Date** | 2026-08-30 |
| **Tests backend** | `tests/Functional/PublicAgency/` |

---

## 1. Objectif

Brancher le **front voyageur B2C** (recherche trajet → siège → réservation → paiement → billet/PDF) sur l'API publique agence.

Ce document est le **contrat pratique** : routes, payloads, statuts, erreurs, patterns UX paiement.

**Ne pas confondre :**

| Préfixe | Usage | Auth |
|---------|-------|------|
| `/api/public/agency/*` | **Voyageur B2C** (ce guide) | Aucune |
| `/api/agency/*` | Portail **partenaire** agence | JWT Bearer |
| `/api/passes/*`, GoPass grand public | Pass ONT / ancien flux GoPass | Variable |

Guide portail partenaire : `agency-frontend-integration-guide.md`.

---

## 2. Headers & conventions

### 2.1 Headers obligatoires

| Méthode | Headers |
|---------|---------|
| GET | `Accept: application/json` |
| POST | `Content-Type: application/json`, `Accept: application/json` |

Pas de `Authorization`. Pas de `merge-patch+json` (aucun PATCH public).

### 2.2 Collections Hydra

Les listes renvoient souvent :

```json
{ "member": [ … ] }
```

ou legacy :

```json
{ "hydra:member": [ … ] }
```

Parser les deux : `data.member ?? data["hydra:member"] ?? []`.

Les ressources publiques utilisent des **IDs plats** (`offerId`, `publicToken`) — pas d'IRI `/api/...` dans les bodies B2C.

### 2.3 Identifiants clés

| Champ | Rôle | Format |
|-------|------|--------|
| `publicToken` | Clé voyageur pour toute la session (booking, pay, ticket) | 64 car. hex (ex. `a3f2…`) |
| `offerId` | Offre catalogue | Préfixe entité agence (ex. `AO…`) |
| `reference` billet | Référence imprimée | `VP-YYYY-#####` |
| `okapiPassRef` | Pass ONT déjà détenu (optionnel) | `OP-…` |

Conserver `publicToken` en **localStorage / URL query** dès la création de réservation jusqu'à l'écran billet.

---

## 3. Flow voyageur (vue d'ensemble)

```
┌─────────────┐    ┌──────────────┐    ┌─────────────┐    ┌──────────────┐
│  Recherche  │───▶│ Plan sièges  │───▶│  Devis      │───▶│  Réservation │
│ GET /offers │    │ GET …/seats  │    │ GET …/quote │    │ POST /bookings│
└─────────────┘    └──────────────┘    └─────────────┘    └──────┬───────┘
                                                                  │
                    ┌─────────────────────────────────────────────┘
                    ▼
         ┌──────────────────┐    ┌─────────────────────┐    ┌─────────────┐
         │ Paiement         │───▶│ Confirmation        │───▶│ Billet      │
         │ POST …/pay       │    │ POST …/check-status │    │ GET …/ticket│
         │ (MM ou Carte)    │    │ (polling fallback)  │    │ GET …/pdf   │
         └──────────────────┘    └─────────────────────┘    └─────────────┘
```

Webhook FlexPay → backend (`POST /api/payments/webhook/flexpay`) — **pas appelé par le front**.

---

## 4. Catalogue des routes

### 4.1 Healthcheck

```http
GET /api/public/agency/health
```

Réponse `200` :

```json
{
  "status": "ok",
  "service": "public-agency"
}
```

### 4.2 Liste des offres online

```http
GET /api/public/agency/offers?origin=Kinshasa&destination=Matadi&agencyId=AG…
```

Tous les query params sont **optionnels**. Seules les offres avec `onlineSales: true` côté portail partenaire apparaissent.

Réponse (extrait `member[0]`) — valeurs issues des tests :

```json
{
  "id": "AO…",
  "label": "PublicBooking Express",
  "origin": "Kinshasa",
  "destination": "Matadi",
  "ticketPrice": 85000,
  "currency": "CDF",
  "departureTime": "06:00",
  "durationMinutes": 180,
  "agencyId": "AG…",
  "agencyName": "PublicBooking abc12345",
  "transportKind": "BUS",
  "transportCapacity": 8,
  "bookingHoldMinutes": 15
}
```

| Erreur | Code | Message typique |
|--------|------|-----------------|
| Offre hors ligne | 404 | `Offer "AO…" not found or not available online.` |

### 4.3 Détail offre

```http
GET /api/public/agency/offers/{id}
```

Même shape qu'un item de liste. **404** si `onlineSales` désactivé.

### 4.4 Devis (billet + Pass ONT)

```http
GET /api/public/agency/offers/{id}/quote
GET /api/public/agency/offers/{id}/quote?okapiPassRef=OP-PUBLIC-QUOTE
```

**Sans Pass** (test `testQuoteWithoutPass`) :

```json
{
  "offerId": "AO…",
  "ticketPrice": 85000,
  "passPrice": 3000,
  "total": 88000,
  "currency": "CDF",
  "hasExistingPass": false,
  "okapiPassRef": null
}
```

> `passPrice` = tarif Pass ROUTIER actif (souvent 3000 CDF). Vérifier via devis, ne pas hardcoder en prod.

**Avec Pass valide** (test `testQuoteWithValidPass`) :

```json
{
  "offerId": "AO…",
  "ticketPrice": 85000,
  "passPrice": 0,
  "total": 85000,
  "currency": "CDF",
  "hasExistingPass": true,
  "okapiPassRef": "OP-PUBLIC-QUOTE"
}
```

Afficher dans l'UI : `total = ticketPrice + passPrice`. Si `hasExistingPass`, indiquer « Pass ONT déjà détenu ».

### 4.5 Disponibilité sièges

```http
GET /api/public/agency/offers/{id}/seats?travelDate=2026-09-15
```

`travelDate` **obligatoire** (`YYYY-MM-DD`, aujourd'hui ou futur). Sans param → **422**.

Réponse (test `testSeatsReturnsLayout`, bus 8 places) :

```json
{
  "offerId": "AO…",
  "travelDate": "2026-09-15",
  "capacity": 8,
  "availableCount": 8,
  "isFull": false,
  "layout": {
    "kind": "BUS",
    "rows": 2,
    "columns": ["A", "B", "C", "D"],
    "aisleAfter": 1,
    "seatIds": ["01A", "01B", "01C", "01D", "02A", "02B", "02C", "02D"]
  },
  "occupiedSeats": []
}
```

| Erreur | Code | Message |
|--------|------|---------|
| `travelDate` manquant | 422 | `Query travelDate (YYYY-MM-DD) is required.` |
| Date invalide | 422 | `Invalid travelDate.` |
| Date passée | 422 | `travelDate must be today or in the future.` |

#### Règles UI plan de bus

- Afficher **uniquement** `layout.seatIds`.
- Désactiver les sièges listés dans `occupiedSeats`.
- Format siège : **`01A`, `02D`** — 2 chiffres + lettre (uppercase).
- Si `isFull === true` → bloquer la réservation, proposer autre date/offre.
- Ne **pas** réutiliser un layout mock statique : il est **calculé** serveur depuis `transportKind` + `capacity`.

---

## 5. Réservation (hold)

### 5.1 Créer une réservation

```http
POST /api/public/agency/bookings
Content-Type: application/json

{
  "offerId": "AO…",
  "travelDate": "2026-09-15",
  "seatNumber": "01A",
  "passengerName": "Jean Kabongo",
  "passengerId": "ID-100",
  "passengerPhone": "+243812345678",
  "okapiPassRef": "OP-OPTIONAL"
}
```

| Champ | Requis | Contraintes |
|-------|--------|-------------|
| `offerId` | oui | Offre online |
| `travelDate` | oui | `YYYY-MM-DD` |
| `seatNumber` | oui | max 10 car., doit exister dans `layout.seatIds` |
| `passengerName` | oui | max 120 — **voyageur** (nom sur le billet) |
| `passengerId` | oui | max 60 |
| `passengerPhone` | oui | max 20, format E.164 — **voyageur** (reçoit SMS + WhatsApp + PDF) |
| `okapiPassRef` | non | max 40 |

> **Achat pour un tiers :** renseignez ici les coordonnées du **passager** (celui qui voyage). Le numéro Mobile Money du payeur se transmet à l'étape paiement via `payerPhone` (§ 6.1).

Réponse **201** (test `testCreateOnlineBookingReturnsTokenAndQuote`) :

```json
{
  "publicToken": "64_hex_chars…",
  "bookingId": "AB…",
  "status": "PENDING",
  "paymentStatus": "UNPAID",
  "expiresAt": "2026-08-30T16:01:00+01:00",
  "isExpired": false,
  "passengerName": "Jean Kabongo",
  "passengerId": "ID-100",
  "passengerPhone": "+243812345678",
  "seatNumber": "01A",
  "travelDate": "2026-09-15",
  "okapiPassRef": null,
  "quote": {
    "ticketPrice": 85000,
    "passPrice": 3000,
    "total": 88000,
    "currency": "CDF",
    "hasExistingPass": false
  },
  "offer": {
    "id": "AO…",
    "label": "PublicBooking Express",
    "origin": "Kinshasa",
    "destination": "Matadi",
    "departureTime": "06:00"
  },
  "ticketReference": null
}
```

**UX hold :** afficher un compte à rebours basé sur `expiresAt`. Durée = `bookingHoldMinutes` de l'offre (défaut 15 min, configurable par l'agence).

| Erreur | Code | Message |
|--------|------|---------|
| Siège déjà pris | 409 | `Le siège 02A est déjà réservé.` |
| Bus complet | 409 | `Bus complet — aucune place disponible.` |
| Siège invalide | 422 | `Siège XX invalid pour ce véhicule.` |
| Offre hors ligne | 404 | `Offer "…" not found or not available online.` |
| Trop de requêtes | 429 | `Too many requests. Please retry later.` + header `Retry-After` |

### 5.2 Lire une réservation

```http
GET /api/public/agency/bookings/{publicToken}
```

Même shape que la création. Si le hold est expiré, le serveur peut auto-annuler : `status: CANCELLED`, `isExpired: true`.

| Erreur | Code | Message |
|--------|------|---------|
| Token inconnu | 404 | `Booking not found.` |

### 5.3 Annuler (non payée)

```http
POST /api/public/agency/bookings/{publicToken}/cancel
```

Body vide. Réponse **200** :

```json
{
  "status": "CANCELLED",
  "paymentStatus": "FAILED",
  …
}
```

| Erreur | Code | Message |
|--------|------|---------|
| Déjà payée / paiement en cours | 409 | `Cannot cancel a booking while payment is in progress or completed.` |
| Pas annulable | 422 | `Only pending online bookings can be cancelled.` |

---

## 6. Paiement FlexPay

### 6.1 Initier le paiement

```http
POST /api/public/agency/bookings/{publicToken}/pay
Content-Type: application/json

{ "method": "MOBILE_MONEY" }
```

Achat pour quelqu'un d'autre (push MM sur le téléphone du payeur, billet envoyé au passager) :

```json
{
  "method": "MOBILE_MONEY",
  "payerPhone": "+243811111111"
}
```

ou :

```json
{ "method": "CARD" }
```

| Champ | Requis | Description |
|-------|--------|-------------|
| `method` | oui | `MOBILE_MONEY` ou `CARD` |
| `payerPhone` | non | Numéro Mobile Money du **payeur**. Si omis, le push MM part sur `passengerPhone` (achat pour soi-même). |

Valeurs `method` : `MOBILE_MONEY` | `CARD` (exactement, sensibles à la casse).

#### Réponse Mobile Money (test `testInitiateMobileMoneyPayment`)

```json
{
  "publicToken": "…",
  "bookingId": "AB…",
  "paymentId": "AP…",
  "paymentStatus": "PENDING",
  "paymentMethod": "MOBILE_MONEY",
  "amount": 88000,
  "currency": "CDF",
  "providerTransactionId": "FP-ORDER-…",
  "cardFormUrl": null,
  "ticketReference": null,
  "bookingStatus": "PENDING",
  "bookingPaymentStatus": "PENDING"
}
```

**UX MM :** afficher « Validez le push USSD / Mobile Money sur votre téléphone ». Le push part sur `payerPhone` si fourni, sinon sur `passengerPhone`. Le billet (SMS + WhatsApp) est toujours envoyé au `passengerPhone` de la réservation.

Le backend planifie automatiquement un **poll async** (~20 s après initiation) via Symfony Messenger. Le front doit quand même poller `check-status` (voir § 6.3).

#### Réponse Carte (test `testInitiateCardPaymentReturnsFormUrl`)

```json
{
  "paymentMethod": "CARD",
  "paymentStatus": "PENDING",
  "cardFormUrl": "/api/public/agency/payments/AP…/card/form",
  …
}
```

**UX Carte :** rediriger le navigateur vers `{API_BASE}{cardFormUrl}` (URL relative → préfixer avec la base API).

Exemple : `window.location.href = 'https://api.ont.digisafrica.tech' + cardFormUrl`

Cette page HTML auto-poste un formulaire vers FlexPay (redirect externe). Après paiement, FlexPay redirige vers les URLs configurées côté serveur (§ 8).

| Erreur | Code | Message |
|--------|------|---------|
| Hold expiré | 422 | `Booking hold has expired.` |
| Déjà payée | 409 | `Booking is already paid.` |
| Paiement déjà en cours (autre méthode) | 409 | `A payment is already in progress for this booking.` |
| Trop de requêtes pay | 429 | `Too many requests…` + `Retry-After` |

**Idempotence partielle :** si un paiement `PENDING` existe **avec la même méthode**, un second `POST …/pay` renvoie la ressource existante (pas d'erreur).

### 6.2 Finalisation (webhook — backend only)

FlexPay notifie le serveur :

```http
POST /api/payments/webhook/flexpay
Content-Type: application/json

{
  "orderNumber": "<providerTransactionId>",
  "status": "SUCCESS"
}
```

Le front **n'appelle jamais** cette route. À la réception, le backend émet le billet `VP-…`, envoie SMS + WhatsApp.

### 6.3 Polling — check-status (fallback front)

```http
POST /api/public/agency/bookings/{publicToken}/pay/check-status
```

Body vide. Interroge FlexPay et finalise si payé (test `testCheckPaymentStatusEndpoint`).

Réponse quand payé :

```json
{
  "paymentStatus": "PAID",
  "ticketReference": "VP-2026-00042",
  "bookingStatus": "CONFIRMED",
  "bookingPaymentStatus": "PAID",
  …
}
```

Réponse tant que en attente :

```json
{
  "paymentStatus": "PENDING",
  "ticketReference": null,
  …
}
```

#### Pattern UX recommandé

```
Après POST …/pay (MM) :
  1. Afficher écran « Paiement en cours… »
  2. Boucle :
       POST …/check-status
       si paymentStatus === 'PAID' → redirect billet
       si paymentStatus === 'FAILED' → écran erreur + retry pay
       sinon sleep 3–5 s, max ~2 min (24–40 tentatives)
  3. Si timeout → « Paiement non confirmé » + support + bouton retry

Après redirect carte (page success) :
  1. Lire token depuis query ?token={publicToken}
  2. Même boucle check-status (webhook peut arriver avant le redirect)
  3. Puis GET …/ticket
```

| Erreur | Code | Message |
|--------|------|---------|
| Aucun paiement | 404 | `No payment found for this booking.` |

---

## 7. Billet & PDF

### 7.1 Billet JSON (QR)

```http
GET /api/public/agency/bookings/{publicToken}/ticket
```

Disponible **uniquement après paiement réussi**. Réponse **200** (test `testGetTicketAfterPayment`) :

```json
{
  "publicToken": "…",
  "ticketId": "AT…",
  "reference": "VP-2026-00042",
  "status": "ISSUED",
  "passengerName": "Jean Kabongo",
  "passengerPhone": "+243812345678",
  "seatNumber": "02B",
  "travelDate": "2026-09-07",
  "ticketPrice": 85000,
  "passPrice": 3000,
  "currency": "CDF",
  "hasExistingPass": false,
  "qrPayload": "VP-2026-00042|…",
  "offer": {
    "id": "AO…",
    "label": "PublicBooking Express",
    "origin": "Kinshasa",
    "destination": "Matadi",
    "departureTime": "06:00"
  },
  "pdfUrl": "/api/public/agency/bookings/{publicToken}/ticket/pdf"
}
```

Afficher le QR à partir de `qrPayload`. Bouton téléchargement → `{API_BASE}{pdfUrl}`.

| Erreur | Code | Message |
|--------|------|---------|
| Pas encore payé | 404 | `Ticket not available yet.` |

### 7.2 PDF binaire

```http
GET /api/public/agency/bookings/{publicToken}/ticket/pdf
```

Réponse **200** :

- `Content-Type: application/pdf`
- Body commence par `%PDF`

Ouvrir dans un nouvel onglet ou déclencher un download. Pas de JSON.

---

## 8. Paiement carte — pages redirect front

Configurer sur le VPS (`.env.vps`) — **obligatoire en prod** :

```env
FLEXPAY_AGENCY_CARD_APPROVE_URL=https://votre-front.com/agency/booking/success?token={ref}
FLEXPAY_AGENCY_CARD_CANCEL_URL=https://votre-front.com/agency/booking/cancelled?token={ref}
FLEXPAY_AGENCY_CARD_DECLINE_URL=https://votre-front.com/agency/booking/failed?token={ref}&reason={reason}
```

| Placeholder | Valeur |
|-------------|--------|
| `{ref}` | `publicToken` de la réservation |
| `{reason}` | Motif FlexPay (decline) |

### Pages front à implémenter

| Route front | Rôle |
|-------------|------|
| `/agency/booking/success?token=…` | Lancer polling `check-status`, puis afficher billet |
| `/agency/booking/cancelled?token=…` | Message annulation, proposer nouvelle réservation |
| `/agency/booking/failed?token=…&reason=…` | Erreur paiement, bouton retry `POST …/pay` |

Sans config VPS, fallback dev : `okapi-pass-v2.vercel.app/agency/booking/…` (GoPass — à remplacer).

---

## 9. Rate limiting

Par IP client, fenêtre glissante **60 secondes** :

| Endpoint | Limite |
|----------|--------|
| `POST /api/public/agency/bookings` | 10 / min |
| `POST /api/public/agency/bookings/{token}/pay` | 5 / min |

Réponse **429** :

```json
{
  "title": "Too Many Requests",
  "detail": "Too many requests. Please retry later.",
  "status": 429
}
```

Header `Retry-After: <secondes>` — respecter avant retry.

**UX :** backoff exponentiel côté front ; ne pas spammer `check-status` (non limité actuellement, mais rester raisonnable : 3–5 s d'intervalle).

---

## 10. CORS

Autoriser le domaine front dans `.env.vps` :

```env
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|.*\.digisafrica\.tech|.*\.vercel\.app|votre-domaine\.com)(:[0-9]+)?$'
```

En dev local : `http://localhost:3000` couvert par le pattern `localhost`.

---

## 11. Règles métier UI

```
totalVoyageur = ticketPrice + passPrice
passPrice     = 0 si Pass valide (okapiPassRef), sinon tarif ROUTIER
```

| Statut booking | Signification |
|----------------|---------------|
| `PENDING` | Hold actif, en attente paiement |
| `CONFIRMED` | Payé, billet émis |
| `CANCELLED` | Annulé ou hold expiré |

| Statut payment (booking) | Signification |
|--------------------------|---------------|
| `UNPAID` | Réservation créée |
| `PENDING` | Paiement initié |
| `PAID` | Succès |
| `FAILED` | Échec ou annulation |

| Produit | Format |
|---------|--------|
| Billet agence | `VP-YYYY-#####` |
| Pass OkapiPass | `OP-…` |

Notifications post-paiement (backend, automatique) : **SMS + WhatsApp** au `passengerPhone` avec réf billet et lien PDF relatif.

---

## 12. Prérequis côté agence (hors front B2C)

L'agence active la vente online via le **portail partenaire** :

```http
PATCH /api/agency/offers/{id}
Authorization: Bearer {jwt}
Content-Type: application/merge-patch+json

{ "onlineSales": true, "bookingHoldMinutes": 15 }
```

Sans `onlineSales: true`, l'offre est invisible dans `GET /api/public/agency/offers`.

---

## 13. Codes HTTP — récap

| Code | Cas B2C |
|------|---------|
| 200 | GET, actions POST (pay, cancel, check-status) |
| 201 | Création réservation |
| 400 | Body invalide / validation Symfony |
| 404 | Offre, booking, ticket ou paiement introuvable |
| 409 | Conflit siège, déjà payé, paiement en cours |
| 422 | Hold expiré, date invalide, règle métier |
| 429 | Rate limit booking / pay |
| 500 | Bug serveur |

Erreurs API Platform : format **RFC 7807** (`title`, `detail`, `status`).

---

## 14. Ordre d'intégration recommandé (écrans)

1. **Recherche** — `GET /offers` + filtres origin/destination
2. **Détail trajet** — `GET /offers/{id}` + `GET …/quote`
3. **Seat picker** — `GET …/seats?travelDate=` + rendu `layout.seatIds`
4. **Formulaire passager** — champs DTO + option `okapiPassRef`
5. **Récap + hold** — `POST /bookings` → stocker `publicToken`, timer `expiresAt`
6. **Paiement MM** — `POST …/pay` + écran attente + polling
7. **Paiement carte** — redirect `cardFormUrl` + 3 pages success/cancel/failed
8. **Billet** — `GET …/ticket` (QR) + lien PDF
9. **Gestion erreurs** — 409 siège, 422 expiré, 429 rate limit
10. **Annulation** — `POST …/cancel` avant paiement

---

## 15. Checklist front

- [ ] Client HTTP sans JWT, base URL configurable (`NEXT_PUBLIC_API_URL`)
- [ ] Parser collections `member` / `hydra:member`
- [ ] Seat map 100 % driven par `layout.seatIds` + `occupiedSeats`
- [ ] Persister `publicToken` (URL, sessionStorage ou localStorage)
- [ ] Compte à rebours hold depuis `expiresAt`
- [ ] Polling `check-status` après MM et après redirect carte
- [ ] Redirect carte : URL absolue `{API_BASE}{cardFormUrl}`
- [ ] Pages `/agency/booking/success|cancelled|failed` avec `?token=`
- [ ] Afficher `pdfUrl` + download PDF binaire
- [ ] Gérer 409 (siège / déjà payé) et 429 (`Retry-After`)
- [ ] CORS testé depuis le domaine de déploiement
- [ ] Distinguer flux B2C (`/api/public/agency/*`) vs portail partenaire
- [ ] Ne pas appeler `/api/payments/webhook/flexpay` depuis le front

---

## 16. Hors scope front B2C

| Route | Qui |
|-------|-----|
| `POST /api/payments/webhook/flexpay` | FlexPay → backend |
| `PATCH /api/agency/offers/{id}` | Portail partenaire (activer online) |
| `GET /api/agency/*` | Portail partenaire JWT |
| Cron `app:expire-public-agency-bookings` | Serveur (libère holds expirés) |

---

## 17. Ressources

| Ressource | Chemin |
|-----------|--------|
| Tests fonctionnels (source de vérité payloads) | `tests/Functional/PublicAgency/` |
| Guide portail partenaire | `agency-frontend-integration-guide.md` |
| Spec métier agence | `agency-backend-integration-spec.md` |
| OpenAPI / Swagger | `{API_BASE}/api/docs` |
| Env VPS exemple | `.env.vps.dist` |

Questions / écarts : ouvrir une issue avec route + payload + status + body erreur.
