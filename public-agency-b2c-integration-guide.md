# Guide d'intégration front — Boutique B2C agence (API publique)

| Champ | Valeur |
|-------|--------|
| **Audience** | Dev front (Next.js, Vite, etc.) — parcours **voyageur** sans compte |
| **Backend** | API Platform / Symfony — préfixe `/api/public/agency/*` |
| **Base URL prod** | `https://api.ont.digisafrica.tech` |
| **Auth** | Aucune — endpoints `PUBLIC_ACCESS` |
| **Format** | JSON (`application/json`) |
| **Statut** | Backend prêt MVP — 32 tests PHPUnit (`PublicAgency/`) |
| **Date** | 2026-09-01 |
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

## 3. Parcours voyageur (vue d'ensemble)

### 3.1 Trois modes d'achat

| Mode | Création | Token clé | Paiement | Billet émis | Notifications post-paiement |
|------|----------|-----------|----------|-------------|----------------------------|
| **Solo** | `POST /bookings` | `publicToken` booking | `POST /bookings/{token}/pay` | 1 billet / 1 siège | SMS + WhatsApp → `passengerPhone` |
| **Pour un tiers** | `POST /bookings` (coords **passager**) | idem | `POST …/pay` + `payerPhone` optionnel | 1 billet au passager | Push MM → `payerPhone` (ou `passengerPhone` si omis) ; billet → `passengerPhone` |
| **Groupe / famille** | `POST /booking-groups` | `publicToken` groupe | `POST /booking-groups/{token}/pay` | **1 seul billet groupé** (tous les sièges) | SMS + WhatsApp → `contactPhone` si renseigné |

Le front choisit le mode **avant** la création du hold : solo/tiers → `/bookings` ; groupe → `/booking-groups`.

### 3.2 Flow solo ou achat pour tiers

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
         │ (+ payerPhone?)  │    │ (polling fallback)  │    │ GET …/pdf   │
         └──────────────────┘    └─────────────────────┘    └─────────────┘
```

**Achat pour un tiers :** à l'étape « passager », saisir nom + téléphone du **voyageur**. À l'étape paiement MM, envoyer `payerPhone` = numéro du payeur (celui qui valide le push USSD).

### 3.3 Flow groupe / famille

```
┌─────────────┐    ┌──────────────┐    ┌─────────────────────┐    ┌──────────────┐
│  Recherche  │───▶│ Multi-sièges │───▶│  Hold groupe        │───▶│  Paiement    │
│ GET /offers │    │ GET …/seats  │    │ POST /booking-groups│    │ POST …/pay   │
└─────────────┘    └──────────────┘    └─────────────────────┘    └──────┬───────┘
                                                                          │
                    ┌─────────────────────────────────────────────────────┘
                    ▼
         ┌─────────────────────┐    ┌─────────────────────────────────────┐
         │ POST …/check-status │───▶│ 1 billet groupé                     │
         │ (même token groupe) │    │ GET …/booking-groups/{token}/ticket │
         └─────────────────────┘    │ GET …/ticket/pdf                  │
                                    └─────────────────────────────────────┘
```

**UX groupe recommandée :** (1) nom du groupe → (2) sélection multi-sièges sur le plan → (3) détails passagers optionnels par siège → (4) `contactPhone` organisateur → (5) paiement unique (`quote.total`).

Webhook FlexPay → backend (`POST /api/payments/webhook/flexpay`) — **pas appelé par le front**.

---

## 4. Catalogue des routes

| Domaine | Routes principales |
|---------|-------------------|
| Catalogue | `GET /offers`, `GET /offers/{id}`, `GET …/quote`, `GET …/seats` |
| Solo | `POST /bookings`, `GET\|POST /bookings/{token}/*` |
| Groupe | `POST /booking-groups`, `GET\|POST /booking-groups/{token}/*` |
| Paiement carte | `GET /payments/{paymentId}/card/form` |

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

### 5.2 Réservation groupée (famille / groupe)

Pour acheter **plusieurs sièges en une seule commande** avec **un seul billet** après paiement :

```http
POST /api/public/agency/booking-groups
Content-Type: application/json

{
  "offerId": "AO…",
  "travelDate": "2026-09-15",
  "groupName": "Famille Kabongo",
  "contactPhone": "+243812345678",
  "passengers": [
    { "seatNumber": "01A" },
    {
      "seatNumber": "01B",
      "passengerName": "Marie Kabongo",
      "passengerId": "ID-101"
    }
  ]
}
```

| Champ | Requis | Contraintes |
|-------|--------|-------------|
| `offerId` | oui | Offre online |
| `travelDate` | oui | `YYYY-MM-DD` |
| `groupName` | oui | max 120 — nom affiché sur le billet groupé |
| `contactPhone` | non | max 20 — organisateur ; reçoit SMS + WhatsApp + lien PDF (**recommandé**) |
| `passengers` | oui | **2 à 20** entrées |
| `passengers[].seatNumber` | oui | Sièges **distincts**, format `01A` |
| `passengers[].passengerName` | non | Optionnel — manifest interne / affichage détail |
| `passengers[].passengerId` | non | Optionnel (défaut serveur `SEAT-{seatNumber}`) |
| `passengers[].passengerPhone` | non | Optionnel — **non utilisé** pour les notifications groupe |

> Seul `seatNumber` est obligatoire par ligne. Les champs passager servent au manifeste ; le billet émis porte `passengerName` = `groupName` et `passengerPhone` = `contactPhone`.

Réponse **201** (test `testCreateGroupBookingWithOptionalPassengerFields`) :

```json
{
  "publicToken": "64_hex_chars…",
  "groupId": "BG…",
  "groupName": "Famille Kabongo",
  "contactPhone": "+243812345678",
  "status": "PENDING",
  "paymentStatus": "UNPAID",
  "expiresAt": "2026-09-08T10:15:00+01:00",
  "isExpired": false,
  "travelDate": "2026-09-15",
  "quote": {
    "ticketPrice": 170000,
    "passPrice": 6000,
    "total": 176000,
    "currency": "CDF",
    "hasExistingPass": false,
    "passengerCount": 2
  },
  "offer": {
    "id": "AO…",
    "label": "PublicBooking Express",
    "origin": "Kinshasa",
    "destination": "Matadi",
    "departureTime": "06:00"
  },
  "passengers": [
    {
      "bookingId": "AB…",
      "seatNumber": "01A",
      "passengerName": null,
      "passengerId": "SEAT-01A",
      "passengerPhone": null,
      "okapiPassRef": null,
      "quote": {
        "ticketPrice": 85000,
        "passPrice": 3000,
        "total": 88000,
        "currency": "CDF",
        "hasExistingPass": false
      }
    },
    {
      "bookingId": "AB…",
      "seatNumber": "01B",
      "passengerName": "Marie Kabongo",
      "passengerId": "ID-101",
      "passengerPhone": null,
      "okapiPassRef": null,
      "quote": { "…": "…" }
    }
  ],
  "ticketReference": null,
  "pdfUrl": null
}
```

**Endpoints groupés** (même logique que solo, préfixe `/booking-groups/`) :

| Action | Route |
|--------|-------|
| Lire | `GET …/booking-groups/{publicToken}` |
| Annuler | `POST …/booking-groups/{publicToken}/cancel` |
| Payer | `POST …/booking-groups/{publicToken}/pay` (+ `payerPhone` optionnel, § 6.4) |
| Poll paiement | `POST …/booking-groups/{publicToken}/pay/check-status` |
| Billet groupé émis | `GET …/booking-groups/{publicToken}/ticket` |
| Wrapper ticket | `GET …/booking-groups/{publicToken}/tickets` |
| PDF billet groupé | `GET …/booking-groups/{publicToken}/ticket/pdf` |

Après paiement réussi : `ticketReference` et `pdfUrl` sont renseignés sur la ressource groupe ; **un seul** SMS/WhatsApp part vers `contactPhone` (pas un message par siège).

| Erreur | Code | Message |
|--------|------|---------|
| Moins de 2 passagers | 400 | Validation Symfony (`passengers` min 2) |
| Siège déjà pris | 409 | `Le siège … est déjà réservé.` |
| Sièges dupliqués | 422 | Règle métier groupe |
| Hold expiré (lecture) | auto | `status: CANCELLED`, `isExpired: true` |

### 5.3 Lire une réservation

```http
GET /api/public/agency/bookings/{publicToken}
```

Même shape que la création. Si le hold est expiré, le serveur peut auto-annuler : `status: CANCELLED`, `isExpired: true`.

| Erreur | Code | Message |
|--------|------|---------|
| Token inconnu | 404 | `Booking not found.` |

### 5.4 Annuler (non payée)

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

### 5.5 Lire / annuler un groupe

```http
GET /api/public/agency/booking-groups/{publicToken}
POST /api/public/agency/booking-groups/{publicToken}/cancel
```

Même shape que la création groupe (§ 5.2). Annulation : body vide, réponse **200** avec `status: CANCELLED` (test `testCancelUnpaidGroupBooking`).

| Erreur | Code | Message |
|--------|------|---------|
| Token inconnu | 404 | `Booking group not found.` |
| Déjà payé | 409 | Conflit annulation groupe payé |

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

### 6.4 Paiement groupe

Même DTO que le solo (`PayPublicAgencyBookingDto`) :

```http
POST /api/public/agency/booking-groups/{publicToken}/pay
Content-Type: application/json

{
  "method": "MOBILE_MONEY",
  "payerPhone": "+243811111111"
}
```

| Champ | Comportement groupe |
|-------|---------------------|
| `method` | `MOBILE_MONEY` ou `CARD` (obligatoire) |
| `payerPhone` | Push MM sur ce numéro si fourni ; sinon sur `contactPhone` du groupe |

Polling :

```http
POST /api/public/agency/booking-groups/{publicToken}/pay/check-status
```

Réponse payée (test `testGroupPaymentIssuesSingleGroupedTicket`) — shape `PublicAgencyBookingGroupPaymentResource` :

```json
{
  "publicToken": "…",
  "groupId": "BG…",
  "groupName": "Famille Kabongo",
  "paymentId": "AP…",
  "paymentStatus": "PAID",
  "paymentMethod": "MOBILE_MONEY",
  "amount": 176000,
  "currency": "CDF",
  "passengerCount": 2,
  "ticketReferences": ["VP-2026-00042"],
  "groupStatus": "CONFIRMED",
  "groupPaymentStatus": "PAID"
}
```

> **`ticketReferences`** contient **une seule** référence pour tout le groupe. Utiliser ensuite `GET …/ticket` pour le détail QR / sièges.

**UX MM groupe :** même boucle polling que § 6.3, mais sur les routes `/booking-groups/…`. Après `PAID`, rediriger vers l'écran billet groupé.

| Erreur | Code | Message |
|--------|------|---------|
| Hold expiré | 422 | `Booking hold has expired.` |
| Déjà payé | 409 | `Group is already paid.` |
| Token inconnu | 404 | `Booking group not found.` |

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

### 7.3 Billet groupé (JSON + PDF)

```http
GET /api/public/agency/booking-groups/{publicToken}/ticket
```

Disponible **uniquement après paiement réussi**. Réponse **200** (test `testGroupPaymentIssuesSingleGroupedTicket`) :

```json
{
  "publicToken": "…",
  "ticketId": "AK…",
  "reference": "VP-2026-00042",
  "status": "ISSUED",
  "passengerName": "Famille Kabongo",
  "passengerPhone": "+243812345678",
  "seatNumber": "02B, 02C",
  "travelDate": "2026-09-15",
  "ticketPrice": 170000,
  "passPrice": 6000,
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
  "pdfUrl": "/api/public/agency/booking-groups/{publicToken}/ticket/pdf",
  "isGroupTicket": true,
  "groupName": "Famille Kabongo",
  "groupSeats": ["02B", "02C"],
  "passengerCount": 2
}
```

#### Champs spécifiques groupe — rendu UI

| Champ | Usage front |
|-------|-------------|
| `isGroupTicket` | `true` → afficher badge « Billet groupe » |
| `groupName` | Titre principal (famille / équipe) |
| `groupSeats` | Liste des sièges à afficher (badges ou tableau) |
| `passengerCount` | Nombre de voyageurs |
| `seatNumber` | Chaîne concaténée (fallback si `groupSeats` absent) |
| `passengerName` / `passengerPhone` | = `groupName` / `contactPhone` du hold |

PDF binaire :

```http
GET /api/public/agency/booking-groups/{publicToken}/ticket/pdf
```

Réponse **200** : `Content-Type: application/pdf`, body `%PDF…` (test `testDownloadGroupedTicketPdf`).

Endpoint alternatif `GET …/tickets` renvoie un wrapper `{ publicToken, groupId, groupName, ticket: { … } }` — préférer `GET …/ticket` pour l'écran voyageur.

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

> Les routes `/booking-groups/*` ne sont **pas** rate-limitées actuellement ; appliquer un backoff côté front si vous enchaînez les créations groupe.

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

#### Notifications post-paiement (backend automatique)

| Mode | Destinataire SMS + WhatsApp | Push Mobile Money |
|------|----------------------------|-------------------|
| Solo | `passengerPhone` | `payerPhone` ?? `passengerPhone` |
| Achat pour tiers | `passengerPhone` (billet) | `payerPhone` |
| Groupe | `contactPhone` (si renseigné) — **1 seul envoi** | `payerPhone` ?? `contactPhone` |

Le message groupe inclut la liste des sièges et le lien PDF relatif. Sans `contactPhone` sur un groupe, aucune notification n'est envoyée (le billet reste accessible via `GET …/ticket`).

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
| 201 | Création réservation solo ou groupe |
| 400 | Body invalide / validation Symfony |
| 404 | Offre, booking, ticket ou paiement introuvable |
| 409 | Conflit siège, déjà payé, paiement en cours |
| 422 | Hold expiré, date invalide, règle métier |
| 429 | Rate limit booking / pay |
| 500 | Bug serveur |

Erreurs API Platform : format **RFC 7807** (`title`, `detail`, `status`).

---

## 14. Ordre d'intégration recommandé (écrans)

### Parcours solo (MVP)

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

### Extension — achat pour un tiers

11. **Toggle UI** « J'achète pour quelqu'un d'autre » — formulaire passager inchangé (coords voyageur)
12. **Étape paiement MM** — champ optionnel payeur + envoi `payerPhone` dans `POST …/pay`
13. **Copy UX** — « Validez le paiement sur le téléphone +243… » (numéro payeur) ; « Le billet sera envoyé au passager »

### Extension — réservation groupée

14. **Mode groupe** — toggle ou CTA « Réserver plusieurs places »
15. **Nom groupe + contactPhone** — champs obligatoires / recommandés
16. **Seat picker multi** — sélection 2–20 sièges, puis `POST /booking-groups`
17. **Détail passagers** — champs optionnels par siège (seul `seatNumber` requis côté API)
18. **Paiement unique** — `POST /booking-groups/{token}/pay` (`amount` = `quote.total`)
19. **Écran billet groupé** — `GET …/ticket` : afficher `groupSeats`, `passengerCount`, QR unique
20. **PDF groupe** — `GET …/ticket/pdf`

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
- [ ] **Achat tiers :** champ payeur + `payerPhone` à l'étape paiement MM
- [ ] **Groupe :** flux `/booking-groups`, seat picker multi, token groupe distinct
- [ ] **Groupe :** afficher billet unique (`isGroupTicket`, `groupSeats`, `passengerCount`)
- [ ] **Groupe :** polling sur `/booking-groups/{token}/pay/check-status`
- [ ] **Groupe :** `contactPhone` recommandé (notifications + billet PDF par SMS/WA)

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
