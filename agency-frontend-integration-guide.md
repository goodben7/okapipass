# Guide d’intégration front — Portail Agency OkapiPass

| Champ | Valeur |
|-------|--------|
| **Audience** | Dev front (Next.js `/agency`) |
| **Backend** | API Platform / Symfony — préfixe `/api/agency/*` |
| **Statut** | Backend prêt — remplacer `AgencyMockProvider` |
| **Date** | 2026-08-04 |
| **Collection test** | `bruno/agency/` |
| **Spec métier détaillée** | `agency-backend-integration-spec.md` |

---

## 1. Objectif

Brancher le portail partenaire sur l’API réelle. Ce document est le **contrat pratique** : auth, routes, payloads, pièges mock → API.

**Ne pas confondre :**

| Préfixe | Usage |
|---------|--------|
| `/api/agency/*` | Portail PARTNER (multi-tenant auto) |
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
    "staff:write"
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

---

## 4. Catalogue des routes (PARTNER)

### 4.1 Contexte

| Méthode | Route | Notes |
|---------|-------|-------|
| GET | `/api/agency/me` | Profil + Pass + RBAC |
| GET | `/api/agency/dashboard` | Stats |

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
| 409 | Conflit siège |
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

---

## 8. Checklist front

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
| Collection Bruno | `bruno/agency/` (+ README) |
| CSV exemple | `bruno/agency/declarations/fixtures/sample.csv` |
| Seed local | `php bin/console app:seed-agency-portal` |

Questions / écarts mock ↔ API : ouvrir une issue ou ping backend avec la route + payload + status code.
