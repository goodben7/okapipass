# Guide d’intégration front — Portail Agency OkapiPass

| Champ | Valeur |
|-------|--------|
| **Audience** | Dev front (Next.js `/agency`) |
| **Backend** | API Platform / Symfony — préfixe `/api/agency/*` |
| **Statut** | Backend prêt (ventes desk + **module fleet F1–F4 + polish**) |
| **Date** | 2026-09-01 |
| **Collection test** | `bruno/agency/` |
| **Spec métier détaillée** | `agency-backend-integration-spec.md` |

---

## 1. Objectif

Brancher le portail partenaire sur l’API réelle. Ce document est le **contrat pratique** : auth, routes, payloads, pièges mock → API.

**Ne pas confondre :**

| Préfixe | Usage |
|---------|--------|
| `/api/agency/*` | Portail PARTNER (multi-tenant auto) |
| `/api/public/agency/*` | **Boutique B2C voyageur** (sans JWT) — voir `public-agency-b2c-integration-guide.md` |
| `/api/agencies` | Admin ONT — créer / gérer les agences |
| `/api/passes/*`, `/api/ont/*` | Pass ONT / FPT côté ONT |

---

## 2. Auth & bootstrap

### 2.1 Login

```http
POST /api/authentication_token
Content-Type: application/json

{ "username": "agency@gmail.com", "password": "12345678" }
```

Réponse : `{ "token": "…" }` → header `Authorization: Bearer <token>` sur toutes les routes agency.

Compte démo (après seed) :

```bash
php bin/console app:seed-agency-portal
# agency@gmail.com / 12345678
```

### 2.2 Headers

| Méthode | `Content-Type` |
|---------|----------------|
| GET / POST | `application/json` |
| PATCH | `application/merge-patch+json` |
| Upload CSV | `multipart/form-data` (ne pas forcer `application/json`) |

Toujours : `Accept: application/json` (ou `application/ld+json` si vous parsez Hydra).

### 2.3 Bootstrap écran

Au chargement du portail :

1. `GET /api/agency/me` → profil + tarif Pass + permissions
2. `GET /api/agency/dashboard` → KPIs (optionnel)

Exemple `me` :

```json
{
  "id": "me",
  "agency": {
    "id": "AG…",
    "name": "Voyages Plus",
    "email": "agency@gmail.com",
    "phone": "+243…",
    "address": "…",
    "licenseNumber": "AGT-ONT-2024-042",
    "defaultCurrency": "CDF",
    "supportedCurrencies": ["CDF", "USD"],
    "status": "ACTIVE"
  },
  "ontPass": {
    "code": "ROUTIER",
    "label": "Pass routier OkapiPass",
    "price": 3000,
    "currency": "CDF"
  },
  "permissions": [
    "booking:write",
    "ticket:write",
    "embarkation:write",
    "declaration:write",
    "payment:write",
    "refund:write",
    "staff:write",
    "fleet:read",
    "fleet:write",
    "driver:write",
    "maintenance:write",
    "rental:write"
  ],
  "staffRole": "ADMIN"
}
```

**Important :** `/me` est en **lecture seule**. On ne POST pas ce JSON.  
Création d’agence = admin `POST /api/agencies` (hors portail).

### 2.4 Collections Hydra

Les listes renvoient souvent :

```json
{ "member": [ … ] }
```

ou legacy :

```json
{ "hydra:member": [ … ] }
```

Parser les deux : `data.member ?? data["hydra:member"] ?? []`.

Les relations se passent en **IRI** : `"/api/agency/offers/OF…"`.

---

## 3. Mapping mock → API

| Opération mock | Endpoint |
|----------------|----------|
| Profile + ontPass | `GET /api/agency/me` |
| Dashboard | `GET /api/agency/dashboard` |
| Transports CRUD | `/api/agency/transports` |
| Offers CRUD | `/api/agency/offers` |
| Seat map | `GET /api/agency/offers/{id}/seat-availability?travelDate=YYYY-MM-DD` |
| Bookings | `/api/agency/bookings` (+ status, issue-ticket) |
| Tickets | `/api/agency/tickets` (+ status, seat, refund, print, by-reference) |
| Payments | `POST /api/agency/payments`, refund |
| Embarkations | `/api/agency/embarkations` (+ tickets, status, declare) |
| Declarations | `/api/agency/declarations` (+ import-csv, status, summary) |
| Preview SMS/WA | `POST /api/agency/notifications/preview` |
| Validate Pass | `GET /api/passes/validate?ref=OP-…` |
| Staff | `/api/agency/staff` |
| **Fleet — hub KPIs** | `GET /api/agency/fleet/overview` |
| **Fleet — chauffeurs** | `/api/agency/drivers` (+ `GET …/{id}/assignments`) |
| **Fleet — maintenance** | `/api/agency/maintenance-cases` (+ start / complete / cancel) |
| **Fleet — location** | `/api/agency/rental-contracts` (+ confirm / activate / return / cancel) |
| **Fleet — calendrier bus** | `GET /api/agency/transports/{id}/availability?from=&to=` |
| **Fleet — paiement location** | `POST …/rental-contracts/{id}/payments`, `…/payments/check-status` |
| **Fleet — PDF contrat** | `GET /api/agency/rental-contracts/{id}/pdf` |
| **Fleet — carte FlexPay location** | `GET /api/agency/payments/{paymentId}/card/form` |

Voir le détail des payloads en **§4.2.1 → §4.2.4**.

---

## 4. Catalogue des routes (PARTNER)

### 4.1 Contexte

| Méthode | Route | Notes |
|---------|-------|-------|
| GET | `/api/agency/me` | Profil + Pass + RBAC |
| GET | `/api/agency/dashboard` | Stats ventes + bloc `fleet` (KPIs) |

**Exemple `GET /api/agency/dashboard` :**

```json
{
  "id": "dashboard",
  "ticketsToday": 12,
  "activeBookings": 3,
  "fptDue": 45000,
  "activeTransports": 9,
  "recentTickets": [ … ],
  "recentDeclarations": [ … ],
  "departuresToday": [
    {
      "offerId": "OF…",
      "label": "Kin Express",
      "departureTime": "06:00",
      "capacity": 45,
      "occupied": 12,
      "embarkationId": "AE…"
    }
  ],
  "fleet": {
    "totalTransports": 12,
    "activeTransports": 9,
    "maintenanceTransports": 2,
    "inactiveTransports": 1,
    "activeDrivers": 8,
    "driversOnDutyToday": 3,
    "driversWithExpiringLicense": 1,
    "openMaintenanceCases": 2,
    "activeRentals": 1,
    "maintenanceCostThisMonth": 425000
  }
}
```

Le bloc `fleet` reprend les mêmes clés que `GET /api/agency/fleet/overview` → `kpis`. Pour les listes détaillées (maintenance récente, locations actives, permis expirants), utiliser **`/fleet/overview`**.

### 4.2 Transports

| Méthode | Route |
|---------|-------|
| GET | `/api/agency/transports` |
| GET | `/api/agency/transports/{id}` |
| POST | `/api/agency/transports` |
| PATCH | `/api/agency/transports/{id}` |
| DELETE | `/api/agency/transports/{id}` |

**POST body :**

```json
{
  "label": "Bus 45 places",
  "kind": "BUS",
  "plateNumber": "KIN-1234-AB",
  "capacity": 45,
  "status": "ACTIVE"
}
```

`kind` : `BUS` \| `MINIBUS` \| `COASTER` \| `VAN`  
`status` : `ACTIVE` \| `INACTIVE` \| `MAINTENANCE`  
→ `MAINTENANCE` / `INACTIVE` **bloque les ventes**.

### 4.2.1 Chauffeurs (fleet F1)

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/drivers` |
| GET/PATCH/DELETE | `/api/agency/drivers/{id}` |

**POST body :**

```json
{
  "fullName": "Jean Kabongo",
  "phone": "+243812345678",
  "licenseNumber": "LIC-2026-001",
  "licenseExpiresAt": "2027-12-31",
  "status": "ACTIVE",
  "notes": "Senior driver"
}
```

`status` : `ACTIVE` \| `INACTIVE` \| `SUSPENDED` — seuls les `ACTIVE` sont assignables.

Filtres liste : `?status=ACTIVE`, `?licenseNumber=LIC-2026-001`, `?fullName=Kabongo`.

**Embarquement avec chauffeur** — champ optionnel `driver` (IRI) :

```json
{
  "label": "Matadi 06:00",
  "offer": "/api/agency/offers/OF…",
  "transport": "/api/agency/transports/AT…",
  "driver": "/api/agency/drivers/AD…",
  "departureDate": "2026-09-15",
  "departureTime": "06:00"
}
```

Historique assignations : `GET /api/agency/embarkations?driver.id=AD…`

| Erreur | Code | Cas |
|--------|------|-----|
| 409 | Plaque permis dupliquée ou delete avec embarquements liés |
| 422 | Chauffeur `INACTIVE` / `SUSPENDED` assigné à embarquement |

### 4.2.2 Maintenance véhicule (fleet F2)

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/maintenance-cases` |
| GET/PATCH | `/api/agency/maintenance-cases/{id}` |
| POST | `/api/agency/maintenance-cases/{id}/start` |
| POST | `/api/agency/maintenance-cases/{id}/complete` |
| POST | `/api/agency/maintenance-cases/{id}/cancel` |

**POST body (ouvrir un dossier) :**

```json
{
  "transport": "/api/agency/transports/AT…",
  "type": "REPAIR",
  "title": "Freins avant",
  "description": "Bruit au freinage",
  "odometerKm": 120000,
  "estimatedCost": 250000,
  "vendorName": "Garage Central"
}
```

`type` : `REPAIR` \| `INSPECTION` \| `PREVENTIVE` \| `ACCIDENT` \| `OTHER`  
`status` (lecture) : `OPEN` → `IN_PROGRESS` → `DONE` (ou `CANCELLED`)

**Effet automatique sur le bus :**

- Dossier `OPEN` / `IN_PROGRESS` / `WAITING_PARTS` → transport passe en `MAINTENANCE` → **ventes bloquées** (desk + B2C)
- Tous les dossiers bloquants clôturés → transport repasse `ACTIVE` (sauf s'il était `INACTIVE`)

**Workflow actions :**

```http
POST /api/agency/maintenance-cases/{id}/start
POST /api/agency/maintenance-cases/{id}/complete
{ "actualCost": 150000, "description": "Travaux terminés" }
POST /api/agency/maintenance-cases/{id}/cancel
```

Historique par bus : `GET /api/agency/maintenance-cases?transport.id=AT…`

### 4.2.3 Location / charter (fleet F3)

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/rental-contracts` |
| GET/PATCH | `/api/agency/rental-contracts/{id}` |
| POST | `/api/agency/rental-contracts/{id}/confirm` |
| POST | `/api/agency/rental-contracts/{id}/activate` |
| POST | `/api/agency/rental-contracts/{id}/return` |
| POST | `/api/agency/rental-contracts/{id}/cancel` |
| GET | `/api/agency/transports/{transportId}/availability?from=2026-09-01&to=2026-09-30` |

**POST body (créer un contrat en brouillon) :**

```json
{
  "transport": "/api/agency/transports/AT…",
  "driver": "/api/agency/drivers/AD…",
  "clientName": "Société Minex",
  "clientPhone": "+243812345678",
  "clientCompany": "Minex SARL",
  "startAt": "2026-09-10T08:00:00",
  "endAt": "2026-09-13T18:00:00",
  "pickupLocation": "Kinshasa Gare Centrale",
  "dropoffLocation": "Matadi Port",
  "dailyRate": 150000,
  "totalAmount": 450000,
  "depositAmount": 100000,
  "currency": "CDF",
  "notes": "Location charter 3 jours"
}
```

`driver` est optionnel. Seuls les contrats `DRAFT` sont modifiables (PATCH).

**Statuts :** `DRAFT` → `CONFIRMED` → `ACTIVE` → `RETURNED` — annulation possible depuis `DRAFT` ou `CONFIRMED`.

**Workflow actions :**

```http
POST /api/agency/rental-contracts/{id}/confirm
POST /api/agency/rental-contracts/{id}/activate
POST /api/agency/rental-contracts/{id}/return
POST /api/agency/rental-contracts/{id}/cancel
```

**Effet sur les ventes passagers :**

- Contrat `CONFIRMED` ou `ACTIVE` qui chevauche une **date de voyage** → ventes bloquées (desk + B2C) pour cette date uniquement
- Les jours hors période de location restent vendables
- Un contrat `DRAFT` ou `CANCELLED` ne bloque pas

**Calendrier de disponibilité** — réponse par jour (max 90 jours) :

```json
{
  "transportId": "AT…",
  "from": "2026-09-01",
  "to": "2026-09-30",
  "days": [
    { "date": "2026-09-10", "available": true, "reason": null },
    { "date": "2026-09-11", "available": false, "reason": "RENTAL" }
  ]
}
```

Valeurs `reason` : `RENTAL`, `MAINTENANCE`, `INACTIVE`.

Filtres liste : `?status=CONFIRMED`, `?transport.id=AT…`, `?clientName=Minex`.

| Erreur | Code | Cas |
|--------|------|-----|
| 409 | Deux locations confirmées/actives se chevauchent sur le même bus |
| 422 | Modification hors `DRAFT`, activation/retour hors statut attendu, vente passager sur date louée |

**Paiement location (Phase 2 polish) :**

```http
POST /api/agency/rental-contracts/{id}/payments
{ "method": "CASH", "amount": 100000, "notes": "Acompte guichet" }
POST /api/agency/rental-contracts/{id}/payments/check-status
GET  /api/agency/rental-contracts/{id}/pdf
GET  /api/agency/payments/{paymentId}/card/form   # si method=CARD
```

- `method` : `CASH` (encaissement immédiat guichet) \| `MOBILE_MONEY` \| `CARD` (FlexPay)
- Montant par défaut : `depositAmount` si renseigné, sinon `totalAmount`
- Contrat payable en statut `CONFIRMED` ou `ACTIVE` uniquement
- Un seul paiement `PAID` par contrat (MVP)

**Réponses paiement (`POST …/payments`, `POST …/check-status`) — status 201 / 200 :**

Encaissement cash immédiat :

```json
{
  "id": "AP…",
  "reference": "CRP-…-A1B2C3",
  "amount": 100000,
  "currency": "CDF",
  "method": "CASH",
  "status": "PAID",
  "channel": "RENTAL",
  "paidAt": "2026-08-31T10:15:00+00:00",
  "rentalContract": "/api/agency/rental-contracts/RC…"
}
```

Mobile Money (en attente → poll) :

```json
{
  "id": "AP…",
  "reference": "CRP-…-D4E5F6",
  "amount": 360000,
  "currency": "CDF",
  "method": "MOBILE_MONEY",
  "status": "PENDING",
  "channel": "RENTAL",
  "provider": "FLEXPAY",
  "providerTransactionId": "TX-…"
}
```

Après `POST …/payments/check-status` (stub / FlexPay OK) → `"status": "PAID"`.

Carte (redirection formulaire) :

```json
{
  "id": "AP…",
  "method": "CARD",
  "status": "PENDING",
  "channel": "RENTAL",
  "providerResponse": {
    "mode": "HTML_FORM",
    "formUrl": "/api/agency/payments/AP…/card/form"
  }
}
```

Ouvrir `formUrl` (même origine API) dans un navigateur ou WebView — auto-submit vers FlexPay.

| Erreur | Code | Cas paiement location |
|--------|------|------------------------|
| 409 | Paiement `PAID` déjà existant, ou MM en cours avec autre method |
| 422 | Contrat non `CONFIRMED`/`ACTIVE`, montant invalide |
| 403 | Rôle sans `payment:write` (ex. `READONLY`) |

**Alertes fleet (Phase 2 polish — backend / ops, pas d’UI front obligatoire) :**

- Ouverture dossier maintenance → WhatsApp agence (`MAINTENANCE_ALERT`)
- Cron : `php bin/console app:fleet:send-license-alerts --days=30`

### 4.2.4 Hub fleet / KPIs (fleet F4)

| Méthode | Route |
|---------|-------|
| GET | `/api/agency/fleet/overview` |
| GET | `/api/agency/drivers/{id}/assignments` |

**Vue d’ensemble flotte** — hub pour l’écran `/agency/fleet` :

```http
GET /api/agency/fleet/overview
```

```json
{
  "id": "overview",
  "kpis": {
    "totalTransports": 12,
    "activeTransports": 9,
    "maintenanceTransports": 2,
    "inactiveTransports": 1,
    "activeDrivers": 8,
    "driversOnDutyToday": 3,
    "driversWithExpiringLicense": 1,
    "openMaintenanceCases": 2,
    "activeRentals": 1,
    "maintenanceCostThisMonth": 425000
  },
  "recentMaintenanceCases": [ … ],
  "activeRentals": [ … ],
  "expiringLicenses": [ … ]
}
```

Le bloc `fleet` est aussi exposé dans `GET /api/agency/dashboard` (même structure `kpis`).

**Historique embarquements d’un chauffeur :**

```http
GET /api/agency/drivers/AD…/assignments
```

```json
{
  "driverId": "AD…",
  "driverName": "Jean Kabongo",
  "assignments": [
    {
      "embarkationId": "AE…",
      "label": "Matadi 06:00",
      "status": "PLANNED",
      "departureDate": "2026-09-15",
      "departureTime": "06:00",
      "offerId": "OF…",
      "offerLabel": "Kin Express",
      "transportId": "AT…",
      "transportLabel": "Bus 45 places"
    }
  ]
}
```

**Permissions RBAC fleet** (retournées par `/me`) :

| Permission | Usage |
|------------|-------|
| `fleet:read` | Consulter overview / calendrier |
| `fleet:write` | Accès module fleet (ADMIN) |
| `driver:write` | CRUD chauffeurs |
| `maintenance:write` | Dossiers maintenance |
| `rental:write` | Contrats location |

> **RBAC staff :** seul le rôle **`ADMIN`** (titulaire agence) reçoit les permissions fleet par défaut.  
> `CASHIER` / `EMBARKATION` / `READONLY` n’ont **pas** `fleet:*` — masquer le menu Fleet pour ces rôles.

**Structure écrans recommandée (`/agency/fleet`) :**

```
/agency/fleet
  /overview          ← GET /fleet/overview (+ KPIs dashboard)
  /transports        ← CRUD existant §4.2
  /transports/{id}
    /calendar        ← GET …/availability?from=&to=
    /maintenance     ← liste ?transport.id= + actions
  /drivers           ← CRUD §4.2.1
  /drivers/{id}      ← détail + GET …/assignments
  /rentals           ← CRUD + workflow §4.2.3
  /rentals/{id}      ← paiement, PDF, statut
```

### 4.3 Offres

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/offers` |
| GET/PATCH/DELETE | `/api/agency/offers/{id}` |
| GET | `/api/agency/offers/{offerId}/seat-availability?travelDate=2026-08-10` |

**POST body :**

```json
{
  "label": "Kinshasa → Matadi matin",
  "origin": "Kinshasa",
  "destination": "Matadi",
  "transport": "/api/agency/transports/AT…",
  "ticketPrice": 25000,
  "currency": "CDF",
  "departureTime": "06:00",
  "durationMinutes": 180,
  "active": true
}
```

#### Vente en ligne B2C (widget voyageur)

Pour exposer une offre sur le front voyageur (`/api/public/agency/*`), activer la vente online :

```http
PATCH /api/agency/offers/{id}
Authorization: Bearer {jwt}
Content-Type: application/merge-patch+json

{
  "onlineSales": true,
  "bookingHoldMinutes": 15
}
```

| Champ | Effet |
|-------|-------|
| `onlineSales: true` | Offre visible dans `GET /api/public/agency/offers` |
| `bookingHoldMinutes` | Durée du hold B2C (défaut 15 min) |

Sans `onlineSales`, l'offre reste **desk only** (portail partenaire).

Le parcours voyageur complet (solo, achat pour tiers, réservation groupée, `payerPhone`, billet groupé unique) est documenté dans **`public-agency-b2c-integration-guide.md`** — ce guide portail ne duplique pas ces routes.

Impact maintenance / location : un transport en `MAINTENANCE` ou une location active bloque aussi les ventes B2C pour les dates concernées (même règle que le desk).

### 4.4 Sièges (critique pour le front)

Le plan **n’est pas** une table seats. Il est **calculé** côté serveur depuis `kind` + `capacity`.

Format des IDs : `01A`, `01B`, `02A`… (**2 digits** + lettre).  
❌ Ne pas réutiliser des IDs mock type `12A` si le layout serveur ne les génère pas.

Réponse typique `seat-availability` :

```json
{
  "offerId": "OF…",
  "travelDate": "2026-08-10",
  "layout": {
    "kind": "BUS",
    "rows": 12,
    "columns": ["A", "B", "C", "D"],
    "aisleAfter": 1,
    "seatIds": ["01A", "01B", "…"],
    "capacity": 45
  },
  "occupied": ["01A", "02C"],
  "available": ["01B", "01C", "…"]
}
```

Afficher **uniquement** `layout.seatIds` ; désactiver les `occupied`.

### 4.5 Réservations

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/bookings` |
| GET/PATCH | `/api/agency/bookings/{id}` |
| PATCH | `/api/agency/bookings/{id}/status` |
| POST | `/api/agency/bookings/{id}/issue-ticket` |

**POST body :**

```json
{
  "offer": "/api/agency/offers/OF…",
  "passengerName": "Kabongo Jean",
  "passengerId": "CD-19-845621",
  "passengerPhone": "+243810000001",
  "seatNumber": "01A",
  "travelDate": "2026-08-10",
  "okapiPassRef": "OP-MOCK-2044",
  "status": "PENDING",
  "sendSms": false
}
```

- Siège déjà pris → **409**
- `issue-ticket` est **idempotent** (2e appel = même billet)
- `okapiPassRef` optionnel : si Pass valide → `passPrice = 0`

### 4.6 Billets

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/tickets` |
| GET | `/api/agency/tickets/{id}` |
| GET | `/api/agency/tickets/by-reference/{reference}` |
| GET | `/api/agency/tickets/{id}/print` |
| PATCH | `/api/agency/tickets/{id}/status` |
| PATCH | `/api/agency/tickets/{id}/seat` |
| POST | `/api/agency/tickets/{id}/refund` |

Référence billet : `VP-YYYY-#####` (≠ Pass `OP-…`, ≠ GoPass catalog).

**POST manuel (wizard)** — mêmes champs passager/offre/siège que booking.

### 4.7 Paiements guichet

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/payments` |
| GET | `/api/agency/payments/{id}` |
| POST | `/api/agency/payments/{id}/refund` |

```json
{
  "ticket": "/api/agency/tickets/AK…",
  "method": "CASH",
  "notes": null
}
```

`method` : selon enums backend (`CASH`, etc.). Permission : `payment:write` / `refund:write`.

### 4.8 Embarquements

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/embarkations` |
| GET | `/api/agency/embarkations/{id}` |
| POST | `/api/agency/embarkations/{id}/tickets` |
| DELETE | `/api/agency/embarkations/{id}/tickets/{ticketId}` |
| PATCH | `/api/agency/embarkations/{id}/status` |
| POST | `/api/agency/embarkations/{id}/declare` |

```json
{
  "label": "Départ Matadi 06:00",
  "offer": "/api/agency/offers/OF…",
  "transport": "/api/agency/transports/AT…",
  "departureDate": "2026-08-10",
  "departureTime": "06:00",
  "ticketIds": []
}
```

- `tickets: []` = aucun billet lié (souvent `PLANNED`)
- `declare` → crée une déclaration FPT `submitted` (idempotent si déjà déclarée)

### 4.9 Déclarations FPT

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/declarations` |
| GET | `/api/agency/declarations/{id}` |
| POST | `/api/agency/declarations/import-csv` |
| PATCH | `/api/agency/declarations/{id}/status` |
| GET | `/api/agency/declarations/summary` |

**Import CSV — 2 modes :**

**A. JSON (texte)**

```json
{
  "label": "Import août",
  "content": "referenceBillet;date;passengerName;…"
}
```

**B. Fichier multipart**

```
POST /api/agency/declarations/import-csv
Content-Type: multipart/form-data

file: <sample.csv>
label: Import août
```

Fixture exemple : `bruno/agency/declarations/fixtures/sample.csv`

Limites :
- max **2 Mo**
- max **5 000** lignes data
- rate-limit **20 imports / min** / partenaire → **429**

Colonnes (`;` ou `,`) :

| Canonique | Alias |
|-----------|--------|
| `referenceBillet` | reference, ref, billet |
| `date` | travelDate, date_voyage |
| `passengerName` | passager, name |
| `passengerId` | piece, id_document |
| `origin` / `destination` | |
| `ticketPrice` | prix, price |
| `currency` | devise |
| `okapiPassRef` | passRef, pass (optionnel) |
| `hasExistingPass` | optionnel |

Sans Pass → FPT = tarif ROUTIER par ligne (ex. 3000 CDF).

Statuts déclaration : `draft` → `submitted` → `paid` (pay côté ONT).

### 4.10 Notifications (preview)

```http
POST /api/agency/notifications/preview
```

```json
{
  "type": "ticket",
  "targetId": "AK…"
}
```

⚠️ Utiliser **`targetId`**, pas `id` (sinon erreur API Platform « Update is not allowed »).

`type` : `booking` \| `ticket`  
Réponse : `smsText`, `whatsappUrl`, `whatsappText`.

### 4.11 Staff (RBAC)

| Méthode | Route |
|---------|-------|
| GET/POST | `/api/agency/staff` |
| GET/PATCH/DELETE | `/api/agency/staff/{id}` |

```json
{
  "email": "caissier@agence.cd",
  "password": "Secret123!",
  "displayName": "Caissier 1",
  "phone": "+2438…",
  "role": "CASHIER"
}
```

Rôles : `ADMIN` \| `CASHIER` \| `EMBARKATION` \| `READONLY`  
Masquer les actions UI selon `permissions` de `/me`.

### 4.12 Pass ONT (validation)

```http
GET /api/passes/validate?ref=OP-MOCK-2044
```

Réponse : `{ ref, valid, holder, status, expiresAt, warning? }`

Tarifs Pass (lecture) : `GET /api/ont/pass-tariffs` (si exposé au PARTNER / ONT).

---

## 5. Règles métier à respecter dans l’UI

```
totalVoyageur = ticketPrice + passCharge
passCharge    = 0 si Pass détenu / valide, sinon ontPass.price (ex. 3000 CDF)
FPT           = somme des passCharge des lignes sans Pass
```

| Produit | Format | Qui |
|---------|--------|-----|
| Billet agence | `VP-YYYY-#####` | Partenaire |
| Pass OkapiPass | `OP-…` | ONT |
| Déclaration | `PD…` | Partenaire → ONT |

Ne **jamais** fusionner avec les anciennes APIs Trip/Ticket grand public.

---

## 6. Codes HTTP utiles

| Code | Cas |
|------|-----|
| 201 | Création OK |
| 200 | Lecture / PATCH / actions |
| 401 | Token manquant / invalide |
| 403 | Rôle insuffisant |
| 404 | Ressource autre agence (tenant) ou inexistante |
| 409 | Conflit siège, permis chauffeur dupliqué, chevauchement location, paiement location déjà payé |
| 415 | Content-Type incorrect |
| 422 | Validation métier / CSV |
| 429 | Rate-limit import CSV |
| 500 | Bug — ex. body `id` sur preview notif (utiliser `targetId`) |

---

## 7. Ordre d’intégration recommandé (écrans)

1. **Auth** + `GET /me` (remplacer mock profile)
2. **Transports** + **Offers**
3. **Seat availability** (adapter le seat picker aux `seatIds` serveur)
4. **Bookings** → **issue-ticket**
5. **Tickets** manuels + print + by-reference
6. **Payments** + refund
7. **Embarkations** + declare
8. **Declarations** + import CSV (fichier) + summary
9. **Pass validate** dans le wizard vente
10. **Staff** + gating permissions
11. **Notification preview** (SMS / WA)
12. **Fleet hub** — `GET /fleet/overview` + dashboard `fleet`
13. **Chauffeurs** — CRUD + select sur wizard embarquement (`driver` IRI)
14. **Maintenance** — fiche bus, workflow start/complete/cancel (ventes bloquées auto)
15. **Calendrier transport** — `GET …/transports/{id}/availability` (vue planning)
16. **Locations** — contrats charter + confirm/activate/return
17. **Paiement location** — cash desk + poll FlexPay + PDF contrat

Les étapes 12–17 peuvent suivre les ventes desk (1–11) une fois le guichet stable.

---

## 8. Checklist front

### Ventes desk (existant)

- [ ] Supprimer / feature-flag `AgencyMockProvider`
- [ ] Client HTTP : JWT + parse `member` / `hydra:member`
- [ ] PATCH en `merge-patch+json`
- [ ] IRI pour `offer`, `transport`, `ticket`
- [ ] Sièges = réponse `seat-availability` (pas mock layout)
- [ ] Preview notif : champ `targetId`
- [ ] Import CSV : support `FormData` + fichier
- [ ] Afficher `ontPass.price` depuis `/me`
- [ ] Gating UI via `permissions` / `staffRole`
- [ ] Distinguer `VP-…` (billet) vs `OP-…` (Pass)
- [ ] Tester contre Bruno `bruno/agency/` + seed

### Module fleet (F1–F4 + polish)

- [ ] Menu Fleet visible si `fleet:read` (typiquement `ADMIN`)
- [ ] Page overview : `GET /api/agency/fleet/overview`
- [ ] Dashboard accueil : afficher le bloc `fleet` retourné par `/dashboard`
- [ ] Chauffeurs : CRUD + badge permis expirant (data `expiringLicenses`)
- [ ] Embarquement : champ optionnel `driver` (select chauffeurs `ACTIVE`)
- [ ] Fiche bus : onglet maintenance + actions POST start/complete/cancel
- [ ] Fiche bus : calendrier dispo (`reason`: `RENTAL` \| `MAINTENANCE` \| `INACTIVE`)
- [ ] Locations : workflow DRAFT → CONFIRMED → ACTIVE → RETURNED
- [ ] Location : paiement (`CASH` / `MOBILE_MONEY` / `CARD`) + poll `check-status`
- [ ] Location : bouton télécharger PDF (`GET …/pdf`, `Accept: application/pdf`)
- [ ] Fiche chauffeur : historique `GET /drivers/{id}/assignments`
- [ ] Gérer 409 chevauchement location à la confirmation
- [ ] Ne pas proposer vente passager sur dates `available: false` (calendrier)

### Vente online B2C (configuration portail)

- [ ] Toggle `onlineSales` sur les offres destinées au widget voyageur
- [ ] Ajuster `bookingHoldMinutes` si besoin (défaut 15)
- [ ] Ne pas implémenter le parcours B2C dans `/agency` — consommer `public-agency-b2c-integration-guide.md` sur le front voyageur séparé

---

## 9. Hors scope portail (ne pas appeler depuis `/agency`)

| Route | Qui |
|-------|-----|
| `POST /api/agencies` | Admin (`ROLE_AGENCY_CREATE`) — crée agence + user |
| `PATCH /api/agencies/{id}` | Admin |
| `POST /api/ont/fpt-declarations/{id}/pay` | ONT_ADMIN — marquer FPT payé |

À la création admin, `licenseNumber` / multi-devises ne sont pas tous dans le DTO create : défauts serveur (`CDF`). Le seed démo remplit licence + `["CDF","USD"]`.

---

## 10. Ressources

| Ressource | Chemin |
|-----------|--------|
| Spec métier complète | `agency-backend-integration-spec.md` |
| **Guide B2C voyageur** | **`public-agency-b2c-integration-guide.md`** (solo, tiers, groupes) |
| **Module fleet (ce guide)** | **§4.2.1 → §4.2.4** |
| Collection Bruno | `bruno/agency/` (+ README — fleet à compléter côté Bruno) |
| CSV exemple | `bruno/agency/declarations/fixtures/sample.csv` |
| Seed local | `php bin/console app:seed-agency-portal` |
| Tests API fleet | `tests/Functional/Agency/AgencyDriverTest.php`, `AgencyMaintenanceCaseTest.php`, `AgencyRentalContractTest.php`, `AgencyFleetOverviewTest.php`, `AgencyFleetPolishTest.php` |

Questions / écarts mock ↔ API : ouvrir une issue ou ping backend avec la route + payload + status code.
