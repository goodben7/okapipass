# Spécification d’intégration backend — Module Agency OkapiPass

| Champ | Valeur |
|-------|--------|
| **Document** | Spec API & conception — Portail agence partenaire |
| **Version** | 1.0 |
| **Date** | 2026-07-21 |
| **Audience** | Équipe backend (API Platform / Symfony) + front agency |
| **Source front** | `src/lib/agency/*`, `src/app/agency/*` (prototype mock) |
| **Statut** | Source de vérité pour remplacer `AgencyMockProvider` |

---

## 1. Objectif

Le portail `/agency` est aujourd’hui **100 % mock en mémoire**. Ce document définit :

1. Le **modèle métier** à persister
2. Les **contrats REST** (routes, payloads, codes d’erreur)
3. Les **règles métier** (sièges, FPT, Pass, workflows)
4. Les **intégrations ONT** (tarif Pass, validation Pass, déclarations FPT)
5. La **stratégie de migration** mock → API

Le front consomme déjà des APIs **API Platform** (collections Hydra `member` / `hydra:member`, JWT via `/authentication_token`, IRIs `/api/...`). Les endpoints agency doivent suivre **les mêmes conventions**.

---

## 2. Contexte métier

### 2.1 Acteurs

| Acteur | `personType` | Accès |
|--------|--------------|-------|
| Agence partenaire | `PARTNER` | Portail `/agency` — multi-tenant par `agencyId` |
| Staff agence (futur) | sous-rôles PARTNER | Caissier, embarquement, admin agence |
| ONT | `ONT_ADMIN` / `ONT_AGENT` | Tarif Pass, réception déclarations FPT, validation Pass |

### 2.2 Produits distincts (ne pas confondre)

| Concept | Propriétaire | Description |
|---------|--------------|-------------|
| **Billet agence** | Partenaire | Place de voyage (réf. `VP-YYYY-#####`) |
| **Pass OkapiPass** | ONT | Pass routier (réf. `OP-…`) |
| **FPT** | ONT | *Fond pour la promotion du tourisme* = part Pass ONT facturée / déclarée |

**Règle tarifaire voyageur :**

```
totalVoyageur = ticketPrice (agence) + passCharge
passCharge    = 0 si Pass déjà détenu, sinon ontPass.price
FPT déclaré   = somme des passCharge des billets sans Pass existant
```

Tarif seed actuel : Pass routier `ROUTIER` = **3 000 CDF** (fixé par l’ONT, non éditable par l’agence).

---

## 3. Architecture cible

```
┌─────────────────┐     JWT PARTNER      ┌──────────────────────────┐
│  Next.js /agency │ ──────────────────► │  API Agency (/api/agency) │
└─────────────────┘                      └────────────┬─────────────┘
                                                      │
                         ┌────────────────────────────┼────────────────┐
                         ▼                            ▼                ▼
                  ┌──────────────┐            ┌─────────────┐   ┌────────────┐
                  │ Domain Agency│            │  Module ONT │   │ Notif SMS  │
                  │ seats, FPT   │            │ Pass / FPT  │   │ (provider) │
                  └──────────────┘            └─────────────┘   └────────────┘
```

### 3.1 Multi-tenant

- Toute ressource agency est scopée par `agency` (IRI ou UUID).
- Un `PARTNER` ne voit / modifie **que** les données de son agence (`ownerId` / `agency` lié au user).
- Les endpoints ONT pour déclarations FPT sont séparés (réception côté ONT).

### 3.2 Préfixe recommandé

```
/api/agency/...          # ressources portail partenaire
/api/ont/pass-tariffs    # déjà / à aligner côté ONT
/api/passes/validate     # validation Pass (ONT, callable par PARTNER)
```

---

## 4. Modèle de données

### 4.1 Entités & champs

#### `Agency` (profil)

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `name` | string | ex. Voyages Plus |
| `email` | string | |
| `phone` | string | |
| `address` | string | |
| `licenseNumber` | string | ex. `AGT-ONT-2024-042` |
| `defaultCurrency` | string | `CDF` |
| `status` | enum | ACTIVE / SUSPENDED |
| `createdAt` / `updatedAt` | datetime | |

#### `AgencyTransport`

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `label` | string | |
| `kind` | `BUS` \| `MINIBUS` \| `COASTER` \| `VAN` | Détermine layout sièges |
| `plateNumber` | string | unique par agence |
| `capacity` | int | Doit matcher le layout généré |
| `status` | `ACTIVE` \| `INACTIVE` \| `MAINTENANCE` | MAINTENANCE → bloquer ventes (recommandé) |
| `createdAt` | datetime | |

**Layout sièges (règle serveur) :**

| `kind` | Disposition | Colonnes |
|--------|-------------|----------|
| BUS, COASTER | 2+2 | A B \| C D |
| MINIBUS, VAN | 2+1 | A B \| C |

Identifiants sièges : `{row:02d}{col}` → `01A`, `02B`, …

#### `AgencyOffer` (ligne / tarif)

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `transport` | IRI | |
| `label` | string | |
| `origin` / `destination` | string | |
| `ticketPrice` | int/decimal | **Hors** Pass ONT |
| `currency` | string | |
| `departureTime` | `HH:mm` | |
| `durationMinutes` | int | |
| `active` | bool | |

#### `AgencyBooking`

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `offer` | IRI | |
| `passengerName` | string | |
| `passengerId` | string | pièce d’identité |
| `passengerPhone` | string | requis pour SMS |
| `seatNumber` | string | obligatoire, normalisé UPPER |
| `travelDate` | date | `YYYY-MM-DD` |
| `status` | BookingStatus | |
| `okapiPassRef` | string\|null | UPPER |
| `createdAt` | datetime | |

`BookingStatus` : `PENDING` \| `CONFIRMED` \| `CANCELLED` \| `COMPLETED`

#### `AgencyTicket`

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `reference` | string | **unique** `VP-{YYYY}-{#####}` séquence atomique |
| `booking` | IRI\|null | si issu d’une réservation |
| `offer` | IRI | |
| `passengerName` / `passengerId` / `passengerPhone` | | |
| `seatNumber` | string | |
| `travelDate` | date | |
| `ticketPrice` | number | snapshot à l’émission |
| `passPrice` | number | 0 si Pass existant |
| `currency` | string | |
| `status` | TicketStatus | |
| `okapiPassRef` | string\|null | |
| `hasExistingPass` | bool | dérivé / stocké |
| `embarkation` | IRI\|null | |
| `declaration` | IRI\|null | |
| `notes` | text\|null | |
| `qrPayload` | string\|null | payload chiffré (aligner `qr-utils`) |
| `createdAt` | datetime | |

`TicketStatus` : `ISSUED` \| `BOARDED` \| `CANCELLED` \| `USED`

#### `AgencyEmbarkation`

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `label` | string | |
| `offer` | IRI | |
| `transport` | IRI | |
| `departureDate` | date | |
| `departureTime` | `HH:mm` | |
| `status` | EmbarkationStatus | |
| `tickets` | IRI[] | manifeste |
| `declaration` | IRI\|null | |
| `notes` | text\|null | |
| `departedAt` / `declaredAt` | datetime\|null | |
| `createdAt` | datetime | |

`EmbarkationStatus` : `PLANNED` \| `BOARDING` \| `DEPARTED` \| `DECLARED` \| `CLOSED`

#### `PassDeclaration` (lot FPT)

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | |
| `agency` | IRI | |
| `label` | string | |
| `source` | `manual` \| `csv` \| `embarkation` | |
| `embarkation` | IRI\|null | |
| `status` | `draft` \| `submitted` \| `paid` | **minuscules** (aligné front) |
| `lines` | DeclarationLine[] | embarquées ou table fille |
| `currency` | string | |
| `fptTotal` | number | calculé serveur |
| `createdAt` / `submittedAt` / `paidAt` | | |

#### `DeclarationLine`

| Champ | Type |
|-------|------|
| `id` | uuid |
| `referenceBillet` | string |
| `date` | date |
| `passengerName` / `passengerId` | string |
| `origin` / `destination` | string |
| `ticketPrice` | number |
| `currency` | string |
| `passPrice` | number |
| `okapiPassRef` | string\|null |
| `hasExistingPass` | bool |

### 4.2 Relations (ER simplifié)

```
Agency 1──* Transport
Agency 1──* Offer ──* Booking
Offer  *──1 Transport
Booking 0..1──1 Ticket
Offer 1──* Ticket
Embarkation *──* Ticket (manifeste)
Embarkation 0..1──1 PassDeclaration
PassDeclaration 1──* DeclarationLine
Agency 1──* PassDeclaration
```

### 4.3 Index & contraintes critiques

| Contrainte | Description |
|------------|-------------|
| **UQ** `(agency, offer, travelDate, seatNumber)` actifs | Anti-surbooking (bookings non CANCELLED + tickets non CANCELLED) |
| **UQ** `AgencyTicket.reference` | Global ou par agence |
| **CHK** siège ∈ layout(transport.kind, capacity) | |
| Cascade soft | Soft-delete préféré ; hard delete transport/offre bloqué si dépendances |

---

## 5. Règles métier (à implémenter côté serveur)

### 5.1 Disponibilité sièges

Pour `(offerId, travelDate)` :

1. Charger offre → transport → `buildSeatLayout(kind, capacity)`
2. Occupés =
   - bookings où `status ≠ CANCELLED` et même offre/date
   - **+** tickets où `status ≠ CANCELLED`, même offre/date, **et** `booking IS NULL`  
     *(évite double comptage réservation déjà convertie)*
3. Option `excludeBookingId` pour édition de réservation

Validation sélection :

| Cas | Erreur HTTP | Message suggéré |
|-----|-------------|-----------------|
| Siège vide | 422 | Sélectionnez un siège sur le plan du bus. |
| Hors layout | 422 | Siège {X} invalide pour ce véhicule. |
| Déjà pris | 409 | Le siège {X} est déjà réservé. |
| Bus plein | 409 | Bus complet — aucune place disponible. |

Normalisation : `trim().toUpperCase()`.

### 5.2 Pass & FPT

```
hasExistingPass = Boolean(okapiPassRef?.trim())
passPrice       = hasExistingPass ? 0 : ontPassTariff.price
fptLine         = hasExistingPass ? 0 : ontPassTariff.price
fptTotal(decl)  = Σ fptLine
```

- Validation Pass (recommandé) : appeler service ONT `GET /api/passes/validate?ref=OP-…`
- En V1, accepter ref inconnue avec warning (comportement mock actuel) **ou** rejeter 422 — **à trancher** (recommandation : valider strictement en prod)

### 5.3 Référence billet

```
VP-{YYYY}-{#####}   # séquence atomique par agence + année (pas length+1)
```

### 5.4 Workflows d’état

#### Booking

```
create → PENDING
PENDING → CONFIRMED | CANCELLED
issueTicket(booking) → ticket ISSUED + booking CONFIRMED
ticket BOARDED|USED → booking COMPLETED
ticket CANCELLED → booking CANCELLED
```

#### Ticket

```
create / issue → ISSUED
ISSUED → BOARDED | CANCELLED
BOARDED → USED | CANCELLED
addToEmbarkation (si ISSUED) → BOARDED
removeFromEmbarkation (si BOARDED) → ISSUED
```

#### Embarkation

```
create → PLANNED
addTickets (si PLANNED) → BOARDING
PLANNED|BOARDING → DEPARTED  (set departedAt)
declare → DECLARED (+ PassDeclaration status=submitted)
DECLARED → CLOSED
```

#### Declaration

```
CSV / manuel → draft
draft → submitted   (soumission ONT)
embarkation declare → submitted (direct)
submitted → paid    (règlement FPT — intégration paiement ONT)
```

---

## 6. Catalogue API REST

Conventions :

- Auth : `Authorization: Bearer <JWT>`
- Role : `personType=PARTNER` (+ ownership agence)
- Content-Type : `application/json` (ou `application/ld+json` API Platform)
- Listes : Hydra collection `{ "member": [...], "totalItems": N }`
- Erreurs : Problem Details (`title`, `detail`, `violations[]`)
- Dates : ISO-8601 ; dates jour : `YYYY-MM-DD`
- Montants : nombres (CDF sans décimales côté front actuel) — documenter scale

### 6.1 Auth & contexte

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/authentication_token` | Existant — username/password → JWT |
| GET | `/users?email=` | Existant — profil ; `personType=PARTNER` → redirect `/agency` |
| GET | `/api/agency/me` | **Nouveau** — profil agence + tarif Pass courant |

**`GET /api/agency/me`** — Response 200 :

```json
{
  "agency": {
    "id": "…",
    "name": "Voyages Plus",
    "email": "…",
    "phone": "…",
    "address": "…",
    "licenseNumber": "AGT-ONT-2024-042",
    "defaultCurrency": "CDF"
  },
  "ontPass": {
    "code": "ROUTIER",
    "label": "Pass routier OkapiPass",
    "price": 3000,
    "currency": "CDF"
  },
  "permissions": ["booking:write", "ticket:write", "embarkation:write", "declaration:write"]
}
```

---

### 6.2 Transports

| Méthode | Route | Body / Query |
|---------|-------|--------------|
| GET | `/api/agency/transports` | `?status=ACTIVE` |
| GET | `/api/agency/transports/{id}` | |
| POST | `/api/agency/transports` | create |
| PUT/PATCH | `/api/agency/transports/{id}` | update |
| DELETE | `/api/agency/transports/{id}` | 409 si offres actives |

**POST body :**

```json
{
  "label": "Bus Grand Luxe 01",
  "kind": "BUS",
  "plateNumber": "OKP-1234",
  "capacity": 45,
  "status": "ACTIVE"
}
```

---

### 6.3 Offres / tarifs

| Méthode | Route | Notes |
|---------|-------|-------|
| GET | `/api/agency/offers` | `?active=true` |
| GET | `/api/agency/offers/{id}` | |
| POST | `/api/agency/offers` | |
| PUT/PATCH | `/api/agency/offers/{id}` | `ticketPrice` éditable ; Pass non inclus |
| DELETE | `/api/agency/offers/{id}` | 409 si bookings/tickets futurs |

```json
{
  "label": "Kinshasa → Lubumbashi Express",
  "origin": "Kinshasa",
  "destination": "Lubumbashi",
  "transport": "/api/agency/transports/{id}",
  "ticketPrice": 85000,
  "currency": "CDF",
  "departureTime": "06:00",
  "durationMinutes": 1440,
  "active": true
}
```

---

### 6.4 Disponibilité sièges

| Méthode | Route |
|---------|-------|
| GET | `/api/agency/offers/{offerId}/seat-availability?travelDate=YYYY-MM-DD&excludeBookingId=` |

**Response 200 :**

```json
{
  "offerId": "…",
  "travelDate": "2026-07-20",
  "capacity": 45,
  "availableCount": 38,
  "isFull": false,
  "layout": {
    "kind": "BUS",
    "rows": 12,
    "columns": ["A", "B", "C", "D"],
    "aisleAfter": 1,
    "seatIds": ["01A", "01B", "…"]
  },
  "occupiedSeats": ["04B", "08C"]
}
```

---

### 6.5 Réservations

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/agency/bookings` | `?status=&q=&travelDate=&page=` |
| GET | `/api/agency/bookings/{id}` | |
| POST | `/api/agency/bookings` | crée + valide siège ; SMS optionnel |
| PATCH | `/api/agency/bookings/{id}` | édition (siège, date, passager) — revalider siège |
| PATCH | `/api/agency/bookings/{id}/status` | `{ "status": "CONFIRMED" \| "CANCELLED" }` |
| POST | `/api/agency/bookings/{id}/issue-ticket` | émet billet ; idempotent |

**POST `/api/agency/bookings` :**

```json
{
  "offer": "/api/agency/offers/{id}",
  "passengerName": "Kabongo Jean",
  "passengerId": "CD-123456",
  "passengerPhone": "+243810000000",
  "seatNumber": "12A",
  "travelDate": "2026-07-20",
  "okapiPassRef": null,
  "status": "PENDING",
  "sendSms": true
}
```

**Response 201 :**

```json
{
  "booking": { "…": "…" },
  "smsMessageId": "SMS-…"
}
```

**POST issue-ticket — 201 :**

```json
{
  "ticket": {
    "id": "…",
    "reference": "VP-2026-00053",
    "status": "ISSUED",
    "ticketPrice": 85000,
    "passPrice": 3000,
    "hasExistingPass": false
  }
}
```

| Code | Cas |
|------|-----|
| 409 | Siège pris / bus plein / billet déjà émis |
| 422 | Validation champs / Pass invalide |
| 404 | Booking introuvable |

---

### 6.6 Billets

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/agency/tickets` | `?status=&hasExistingPass=&q=&travelDate=` |
| GET | `/api/agency/tickets/{id}` | |
| GET | `/api/agency/tickets/by-reference/{ref}` | lookup `VP-…` |
| POST | `/api/agency/tickets` | émission manuelle (wizard) |
| PATCH | `/api/agency/tickets/{id}/status` | `{ "status": "BOARDED" }` |
| PATCH | `/api/agency/tickets/{id}/seat` | `{ "seatNumber": "05C" }` — **doit** revalider |
| GET | `/api/agency/tickets/{id}/print` | données impression + QR |

**POST émission manuelle :**

```json
{
  "offer": "/api/agency/offers/{id}",
  "passengerName": "Ilunga Sarah",
  "passengerId": "CD-998877",
  "passengerPhone": "+243820000000",
  "seatNumber": "05A",
  "travelDate": "2026-07-20",
  "okapiPassRef": "OP-MOCK-2044",
  "notes": "Guichet 2",
  "sendSms": true
}
```

---

### 6.7 Embarquements

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/agency/embarkations` | `?departureDate=&status=` |
| GET | `/api/agency/embarkations/{id}` | + tickets embarqués |
| POST | `/api/agency/embarkations` | create |
| POST | `/api/agency/embarkations/{id}/tickets` | `{ "ticketIds": ["…"] }` |
| DELETE | `/api/agency/embarkations/{id}/tickets/{ticketId}` | retire du manifeste |
| PATCH | `/api/agency/embarkations/{id}/status` | BOARDING / DEPARTED / CLOSED |
| POST | `/api/agency/embarkations/{id}/declare` | crée déclaration `submitted` |

**POST create :**

```json
{
  "label": "Départ Kin→Matadi 06:00",
  "offer": "/api/agency/offers/{id}",
  "transport": "/api/agency/transports/{id}",
  "departureDate": "2026-07-20",
  "departureTime": "06:00",
  "notes": null,
  "ticketIds": ["uuid1", "uuid2"]
}
```

**POST declare — effets :**

1. Créer `PassDeclaration` source=`embarkation`, status=`submitted`
2. Lignes depuis billets non `CANCELLED`
3. `fptTotal` calculé
4. Lier `ticket.declaration` + `embarkation.declaration`
5. Status embarquement → `DECLARED`
6. Tickets encore `ISSUED` → `BOARDED`

Idempotence : si déjà déclaré → **409** ou retourner la déclaration existante.

---

### 6.8 Déclarations FPT

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/agency/declarations` | liste + filtre status |
| GET | `/api/agency/declarations/{id}` | + lines |
| POST | `/api/agency/declarations` | création manuelle / CSV |
| POST | `/api/agency/declarations/import-csv` | upload ou body texte |
| PATCH | `/api/agency/declarations/{id}/status` | `draft→submitted→paid` |
| GET | `/api/agency/declarations/summary` | `{ fptDue, byCurrency }` |

**POST import-csv :**

- Accept : `text/csv` ou `{ "content": "…", "label": "…" }`
- Séparateur `;` ou `,`
- Colonnes requises (aliases FR/EN) :

| Canonique | Alias acceptés |
|-----------|----------------|
| `referenceBillet` | reference, ref, billet |
| `date` | travelDate, date_voyage |
| `passengerName` | passager, name |
| `passengerId` | piece, id_document |
| `origin` / `destination` | |
| `ticketPrice` | prix, price |
| `currency` | devise |

**À ajouter (gap mock) :**

| Colonne | Requis |
|---------|--------|
| `okapiPassRef` | non |
| `hasExistingPass` | non (dérivable) |

Sans Pass → FPT facturé sur chaque ligne.

**GET summary :**

```json
{
  "fptDue": 15000,
  "currency": "CDF",
  "draft": 6000,
  "submitted": 9000,
  "paid": 12000
}
```

`fptDue` = Σ `fptTotal` où status ∈ `{draft, submitted}`.

---

### 6.9 Dashboard

| Méthode | Route |
|---------|-------|
| GET | `/api/agency/dashboard` |

```json
{
  "ticketsToday": 12,
  "activeBookings": 5,
  "fptDue": 15000,
  "activeTransports": 2,
  "recentTickets": […],
  "recentDeclarations": […],
  "departuresToday": [
    {
      "offerId": "…",
      "label": "Kin → Matadi",
      "departureTime": "06:00",
      "capacity": 45,
      "occupied": 31,
      "embarkationId": null
    }
  ]
}
```

---

### 6.10 Intégrations ONT (contrat transversal)

| Méthode | Route | Appelant |
|---------|-------|----------|
| GET | `/api/pass-types` ou `/api/ont/pass-tariffs?code=ROUTIER` | Agency (lecture) |
| GET | `/api/passes/validate?ref=OP-…` | Agency guichet |
| POST | `/api/ont/fpt-declarations` | Agency soumet lot |
| PATCH | `/api/ont/fpt-declarations/{id}/pay` | ONT / paiement |

**Validate Pass — 200 :**

```json
{
  "valid": true,
  "ref": "OP-ABC123",
  "holder": "Kabongo Jean",
  "status": "ACTIVE",
  "expiresAt": null
}
```

**404 / 422** si invalide.

---

### 6.11 Notifications

| Hook métier | Canal | Payload |
|-------------|-------|---------|
| Création booking | SMS | réf, trajet, siège, total |
| Émission billet | SMS | réf `VP-…`, trajet, siège |
| Confirmation | WhatsApp | URL `wa.me` générée côté serveur ou front |

Endpoint optionnel :

| POST | `/api/agency/notifications/preview` | body booking/ticket → textes SMS/WA |

Envoi réel : provider SMS (Africa’s Talking / autre) — asynchrone ; retourner `smsMessageId`.

---

## 7. Mapping Front mock → API

| Opération `AgencyMockProvider` | Endpoint cible |
|--------------------------------|----------------|
| (state) profile + ontPass | `GET /api/agency/me` |
| `upsertTransport` / `removeTransport` | POST/PATCH/DELETE `/api/agency/transports` |
| `upsertOffer` / `removeOffer` | POST/PATCH/DELETE `/api/agency/offers` |
| `getSeatAvailability` | `GET …/offers/{id}/seat-availability` |
| `upsertBooking` | `POST/PATCH /api/agency/bookings` |
| `setBookingStatus` | `PATCH …/bookings/{id}/status` |
| `issueTicketFromBooking` | `POST …/bookings/{id}/issue-ticket` |
| `createManualTicket` | `POST /api/agency/tickets` |
| `setTicketStatus` | `PATCH …/tickets/{id}/status` |
| `updateTicketSeat` | `PATCH …/tickets/{id}/seat` |
| `createEmbarkation` | `POST /api/agency/embarkations` |
| `addTicketsToEmbarkation` | `POST …/embarkations/{id}/tickets` |
| `removeTicketFromEmbarkation` | `DELETE …/embarkations/{id}/tickets/{tid}` |
| `setEmbarkationStatus` | `PATCH …/embarkations/{id}/status` |
| `declareEmbarkation` | `POST …/embarkations/{id}/declare` |
| `createDeclarationFromLines` | `POST /api/agency/declarations` |
| `updateDeclarationStatus` | `PATCH …/declarations/{id}/status` |
| `fptDue` | `GET /api/agency/declarations/summary` |

---

## 8. Sécurité

| Règle | Détail |
|-------|--------|
| Auth JWT | Obligatoire sauf endpoints publics existants |
| Isolation tenant | Filtrer **toujours** par `agency` du user PARTNER |
| RBAC (V2) | `ADMIN` / `CASHIER` / `EMBARKATION` / `READONLY` |
| Audit | Logger émissions, annulations, déclarations, paiements FPT |
| PII | `passengerId` (pièce) — chiffrement au repos recommandé |
| Rate limit | validate Pass, SMS, import CSV |
| Idempotency-Key | Sur `POST` billets / issue-ticket / declare (recommandé) |

---

## 9. Priorisation d’implémentation backend

### Sprint A — Socle (bloque le front)

1. `Agency` + lien user PARTNER  
2. CRUD Transports / Offres  
3. Seat availability + contrainte anti-surbooking  
4. Bookings + issue-ticket  
5. Tickets manuels + status  
6. `GET /agency/me` + dashboard basique  

### Sprint B — Exploitation

7. Embarkations + manifeste  
8. Declare → PassDeclaration  
9. Déclarations liste / status / summary  
10. Import CSV  

### Sprint C — ONT & notif

11. Validate Pass  
12. Soumission FPT côté ONT  
13. SMS provider  
14. Print / QR payload  

### Sprint D — Extensions (hors mock actuel)

15. Paiement caisse agence  
16. Users staff agence  
17. Remboursements  
18. Multi-devise  

---

## 10. Critères d’acceptation (tests)

| ID | Scénario | Attendu |
|----|----------|---------|
| AC-01 | Deux POST booking même siège concurrent | Un 201, un 409 |
| AC-02 | Booking avec Pass valide | `passPrice=0`, FPT ligne = 0 |
| AC-03 | Issue ticket 2× | 2e appel idempotent / 409 |
| AC-04 | Declare embarkation | Déclaration `submitted`, `fptTotal` correct |
| AC-05 | CSV sans Pass | FPT = N × tarif ONT |
| AC-06 | PARTNER A lit données PARTNER B | 404 / liste vide |
| AC-07 | Transport MAINTENANCE + offre | Vente refusée 422 (si règle activée) |
| AC-08 | Réf billet | Format `VP-YYYY-#####`, unique |

---

## 11. Écarts mock → prod (à ne pas recopier)

| Comportement mock | Correction backend |
|-------------------|--------------------|
| State perdu au refresh | Persistance DB |
| Compteur VP = `length+1` | Séquence atomique |
| `updateTicketSeat` sans check | Revalider occupation |
| Delete transport orphelin | Bloquer si dépendances |
| CSV ignore Pass existant | Colonnes + calcul FPT |
| `lookupMockPass` non bloquant | Valider ONT en prod |
| Paiement FPT = flip status | Vrai flux paiement |
| Auth = token sans check PARTNER | Guard `personType` + agency |
| Sièges seed `12A` vs layout `01A` | Harmoniser données / capacité |

---

## 12. Glossaire

| Terme | Définition |
|-------|------------|
| **FPT** | Fond pour la promotion du tourisme — part Pass ONT déclarée |
| **Billet** | Document de voyage agence (`VP-…`) |
| **Pass** | OkapiPass ONT (`OP-…`) |
| **Embarquement** | Départ groupé + manifeste |
| **Déclaration** | Lot de lignes FPT soumis à l’ONT |
| **Offre** | Ligne commerciale (trajet + prix billet + véhicule) |

---

## 13. Annexes

### A. Statuts — valeurs exactes

| Domaine | Valeurs |
|---------|---------|
| Booking | `PENDING`, `CONFIRMED`, `CANCELLED`, `COMPLETED` |
| Ticket | `ISSUED`, `BOARDED`, `CANCELLED`, `USED` |
| Embarkation | `PLANNED`, `BOARDING`, `DEPARTED`, `DECLARED`, `CLOSED` |
| Declaration | `draft`, `submitted`, `paid` |
| Transport | `ACTIVE`, `INACTIVE`, `MAINTENANCE` |
| TransportKind | `BUS`, `MINIBUS`, `COASTER`, `VAN` |

### B. Fichiers front de référence

- `src/lib/agency/types.ts`
- `src/lib/agency/AgencyMockProvider.tsx`
- `src/lib/agency/seats.ts`
- `src/lib/agency/pricing.ts`
- `src/lib/agency/csv.ts`
- `src/lib/agency/notifications.ts`
- `src/lib/agency/labels.ts`
- `src/lib/qr-utils.ts`
- `src/app/agency/**`

### C. Login démo (à retirer en prod)

- Email : `agency@gmail.com` / MDP : `12345678`  
- Bypass front — remplacer par JWT PARTNER réel.

---

**Fin du document.**  
Questions / décisions ouvertes à valider avec le PO : validation stricte du Pass, format montants (string vs number), soft-delete, et périmètre paiement FPT (agence vs ONT).
