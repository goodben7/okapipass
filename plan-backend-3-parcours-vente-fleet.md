# Plan backend — 3 parcours de vente + assignation bus ↔ course

| Champ | Valeur |
|-------|--------|
| **Document** | Spec / plan d’implémentation backend |
| **Produit** | MBIYO Lualaba Mobilité (OkapiPass API + front Okapi-agency) |
| **Version** | 1.0 |
| **Date** | 2026-09-27 |
| **Audience** | Backend (Symfony / API Platform), Product, Front |
| **Statut** | À prioriser / estimer / challenger |
| **Références** | `docs/agency-backend-integration-spec.md`, `docs/vague-8-front-endpoints.md`, `docs/proposition-fonctionnalites-backend.md` |
| **Vague suggérée** | **Vague 9** (parcours vente multi-modes) + extension **Fleet** (assignation véhicule) |

---

## 0. Résumé exécutif

Aujourd’hui le modèle tourne surtout autour d’offres **INTERCITY** (+ vague 8 **SCHOOL**).  
L’objectif de cette vague est de **formaliser trois parcours de vente distincts**, avec règles, stock, billetterie et ops différents, et de **lier explicitement un bus (transport) à une course** dans le fleet management.

| # | Parcours | Code produit | Canal principal | Unité de vente |
|---|----------|--------------|-----------------|----------------|
| 1 | Transport **communal / urbain** | `URBAN` | POS + app voyageur (pass / multi-trajets) | Trajet court / zone / forfait |
| 2 | Transport **inter-urbain / inter-province** | `INTERCITY` | Web B2C + POS + agence | Place nominative (siège) |
| 3 | Transport **scolaire** | `SCHOOL` | Agence + roster (pas de vente B2C libre) | Abonnement / présence / départ |

**Livrable transversal (Fleet) :** assigner un **véhicule** (+ chauffeur optionnel) à une **course** (`Trip` / `Embarkation` / `SchoolDeparture`), avec conflits d’agenda, capacité, et verrous de vente.

---

## 1. Problème actuel (constat front)

| Zone | Constat |
|------|---------|
| Offres | `serviceType` = `INTERCITY` \| `SCHOOL` seulement — **pas d’URBAN** |
| Vente | Un seul funnel B2C « offre → siège → paiement → billet » adapté interurbain |
| Scolaire | Vague 8 en place (contrats `SK`, élèves `SU`, roster `SA`, départ → embarquement) — **facturation / pass parents incomplets** |
| Flotte | Transports, chauffeurs, missions, checklists existent — **pas d’API claire « assigner bus → course »** côté UI métier unifiée |
| Embarquement | L’offre pointe souvent un `transport` « template » ; la course du jour n’a pas toujours un véhicule **opérationnel** distinct |

---

## 2. Principes d’architecture

### 2.1 Séparer **catalogue** / **course** / **titre**

```
Offer (catalogue, tarif, serviceType)
   │
   ▼
Trip / Course (instance datée : jour + heure + véhicule + chauffeur + capacité)
   │
   ├── Booking / Ticket (INTERCITY, URBAN ticketé)
   ├── Pass / Wallet debit (URBAN forfait)
   └── SchoolDeparture + Attendance (SCHOOL)
```

**Règle d’or :** on ne vend **jamais** directement « sur le bus catalogue ». On vend (ou on embarque) sur une **course** (`Trip`) qui a un véhicule assigné (ou un véhicule catalogue temporaire tant que non assigné, avec statut `UNASSIGNED`).

### 2.2 Discriminant unique : `serviceType`

```ts
type OfferServiceType = 'URBAN' | 'INTERCITY' | 'SCHOOL';
```

Toute offre, course, billet, reporting et permission dérive de ce champ.

### 2.3 Multi-tenant inchangé

Toutes les ressources restent scopées `agency`. Les endpoints `/api/agency/*` et `/api/traveler/*` / `/api/public/agency/*` restent les frontières.

### 2.4 Idempotence & concurrence

- Assignation véhicule : lock pessimiste ou contrainte unique `(transport_id, departure_window)`  
- Vente siège INTERCITY : lock siège déjà en place — **réutiliser**  
- URBAN : compteurs de capacité (pas de siège) avec `SELECT … FOR UPDATE` ou bucket Redis

---

## 3. Modèle de données proposé

### 3.1 Évolutions `AgencyOffer`

| Champ | Type | Notes |
|-------|------|-------|
| `serviceType` | enum | `URBAN` \| `INTERCITY` \| `SCHOOL` (**étendre** l’existant) |
| `onlineSales` | bool | SCHOOL → forcé `false` (déjà) ; URBAN configurable ; INTERCITY défaut `true` |
| `seatMode` | enum | `ASSIGNED_SEAT` (INTERCITY) \| `CAPACITY_ONLY` (URBAN) \| `NONE` (SCHOOL) |
| `zoneId` / `corridorId` | uuid? | URBAN : zone tarifaire ; INTERCITY : corridor |
| `defaultTransport` | IRI | Template flotte (layout sièges / capacité) |
| `bookingHoldMinutes` | int | INTERCITY / URBAN ticketé |
| `passEligible` | bool | URBAN : débit forfait multi-jours |
| `requiresPassengerId` | bool | INTERCITY : true ; URBAN : false par défaut |

**Migration :** `serviceType` existant `INTERCITY`/`SCHOOL` → ajouter `URBAN` ; backfill `seatMode` depuis `serviceType`.

### 3.2 Nouvelle entité (recommandée) : `AgencyTrip` (Course)

> Alternative acceptable si vous préférez étendre `AgencyEmbarkation` : alors `Embarkation` = course.  
> **Recommandation :** introduire `Trip` comme planifié, et `Embarkation` comme session ops du jour (check-in). Sinon fusionnez explicitement dans la doc d’API pour éviter double vérité.

| Champ | Type | Notes |
|-------|------|-------|
| `id` | uuid | Préfixe suggéré `TR` |
| `agency` | IRI | |
| `offer` | IRI | Détermine `serviceType` |
| `serviceType` | enum | Dénormalisé pour filtres |
| `travelDate` | date | |
| `departureTime` | time | Peut overrider l’offre |
| `origin` / `destination` | string \| checkpoint | INTERCITY ; URBAN peut être zone |
| `status` | enum | `PLANNED` \| `ASSIGNED` \| `BOARDING` \| `DEPARTED` \| `CLOSED` \| `CANCELLED` |
| `transport` | IRI \| null | **Bus assigné** — cœur du besoin fleet |
| `driver` | IRI \| null | |
| `capacity` | int | Snapshot au moment de l’assignation |
| `soldCount` / `boardedCount` | int | Compteurs |
| `schoolContract` | IRI \| null | Si SCHOOL |
| `notes` | string? | |
| `createdAt` / `updatedAt` | datetime | |

**Contraintes :**

1. Un `transport` ne peut pas être sur 2 courses qui se chevauchent (fenêtre `[departure, departure+duration]`).  
2. `capacity` ≥ layout véhicule ; si véhicule `MAINTENANCE` → assignation refusée.  
3. Passage `PLANNED` → `ASSIGNED` dès qu’un transport est lié.  
4. Vente INTERCITY bloquée si politique agence `requireVehicleBeforeSale=true` et `transport=null`.

### 3.3 Assignation flotte (journal)

`AgencyTripAssignment` (audit) :

| Champ | Type |
|-------|------|
| `id` | uuid |
| `trip` | IRI |
| `transport` | IRI |
| `driver` | IRI? |
| `assignedBy` | user |
| `assignedAt` | datetime |
| `unassignedAt` | datetime? |
| `reason` | string? |

Permet historique « quel bus a fait quelle course » pour fuel, incidents, compta.

### 3.4 Titres de transport (par parcours)

| Parcours | Entité titre | Particularités |
|----------|---------------|----------------|
| INTERCITY | `AgencyTicket` (existant) | Siège, passager, QR, PDF |
| URBAN | `AgencyTicket` **ou** `UrbanRide` + `TravelerPass` | Capacité ; QR validable N fois / jour selon produit |
| SCHOOL | Pas de billet B2C ; `SchoolAttendance` + départ | Contrat `SK`, élève `SU` |

### 3.5 Produits URBAN (nouveaux)

| Entité | Rôle |
|---------|------|
| `UrbanZone` | Zone / ligne communale (libellé, checkpoints) |
| `UrbanFare` | Tarif unitaire ou paliers |
| `TravelerPass` (étendre) | Forfait N trajets / validité J jours **scopé URBAN** |

---

## 4. Parcours 1 — Transport communal (`URBAN`)

### 4.1 Besoin métier

Vente rapide, forte rotation, souvent **sans siège nominatif** : lignes urbaines / communales (ex. Kolwezi intra-ville, navettes courtes).

### 4.2 Funnel cible

```
Voyageur / POS
  → Choisir ligne URBAN + date (+ créneau)
  → Choisir produit : ticket unitaire | débit pass multi-jours | cash POS
  → Paiement (MM / wallet / cash)
  → Titre QR (validité courte)
  → Validation embarquement (scan) décrémente capacité course
```

### 4.3 Règles métier

| ID | Règle |
|----|-------|
| U1 | `seatMode = CAPACITY_ONLY` — pas de plan de sièges |
| U2 | Capacité course = capacité véhicule assigné (ou défaut offre) |
| U3 | Overbooking interdit sauf flag agence `urbanAllowStandee` + plafond |
| U4 | Pass multi-jours : 1 scan = 1 consommation ; anti-fraude (cooldown / géofence optionnelle P2) |
| U5 | Annulation : politique courte (ex. T-30 min) |
| U6 | Reporting : recettes par ligne / véhicule / chauffeur |

### 4.4 APIs proposées

```
# Catalogue public / traveler
GET  /api/public/agency/offers?serviceType=URBAN&zone=
GET  /api/public/agency/trips?offer=&date=
POST /api/public/agency/bookings          # body: tripId, product=UNIT|PASS, quantity?
POST /api/traveler/passes/purchase       # forfait URBAN (étendre)

# POS
POST /api/agency/pos/sales               # serviceType=URBAN, tripId, cash/MM

# Ops
POST /api/agency/trips/{id}/validate-boarding   # scan QR → boardedCount++
```

### 4.5 Impact front (indicatif)

- Nouveau chip **Communal** sur offres + funnel `/voyage` allégé (pas de seat map)  
- POS : mode « vente rapide »  
- Compte voyageur : pass urbains + historique scans

### 4.6 Critères d’acceptation backend

- [ ] Créer offre `URBAN` avec `seatMode=CAPACITY_ONLY`  
- [ ] Générer / lister courses du jour  
- [ ] Vendre jusqu’à capacité puis `409 CAPACITY_FULL`  
- [ ] Débit pass + scan embarquement atomiques  
- [ ] KPIs dashboard : ventes URBAN séparées d’INTERCITY  

---

## 5. Parcours 2 — Inter-urbain / inter-province (`INTERCITY`)

### 5.1 Besoin métier

Parcours **déjà dominant** : corridors (ex. Kinshasa→Lubumbashi), siège assigné, Pass ONT / FPT, hold, PDF, partage.

### 5.2 Funnel (consolidé)

```
Offre INTERCITY → Trip (date) → Seat map (véhicule de la course)
  → Passager + ID → Quote (ticket + Pass ONT) → Hold → Pay → Ticket VP-…
  → Embarquement / manifeste / bagages
```

### 5.3 Évolutions requises vs existant

| ID | Évolution | Pourquoi |
|----|-----------|----------|
| I1 | Course `Trip` obligatoire (ou Embarkation datée) | Le seat map doit refléter le **bus du jour**, pas seulement le template offre |
| I2 | Assignation véhicule avant boarding (soft) / avant vente (hard option) | Ops flotte |
| I3 | Si changement de bus après ventes : **remap sièges** ou gel si layouts incompatibles | Éviter sièges fantômes |
| I4 | `serviceType=INTERCITY` explicite partout (filtres, analytics) | Séparer du communal |
| I5 | Groupes / promo / wallet déjà vagues 1–7 — **ne pas casser** | Régression |

### 5.4 APIs (delta)

```
GET  /api/agency/trips?serviceType=INTERCITY&date=&offer=
POST /api/agency/trips
PATCH /api/agency/trips/{id}/assign-transport   # ← cœur fleet
GET  /api/public/agency/offers/{id}/seats?date= # doit résoudre via Trip.transport
```

**Comportement seats :**

1. Résoudre `Trip` pour `(offer, date)`  
2. Si `Trip.transport` → layout de ce véhicule  
3. Sinon → `Offer.defaultTransport` + warning `vehicleUnassigned=true`

### 5.5 Critères d’acceptation

- [ ] Seat map = véhicule assigné à la course  
- [ ] Assignation incompatible (capacité < sièges vendus) → `409 SEAT_LAYOUT_CONFLICT`  
- [ ] Régression : funnel B2C actuel + compte billets OK  
- [ ] Reporting FPT inchangé  

---

## 6. Parcours 3 — Transport scolaire (`SCHOOL`)

### 6.1 Besoin métier

Pas une vente place à place B2C. C’est un **contrat école / parents**, roster quotidien, présence, départ bus, suivi parent (compte voyageur — déjà partiel vague 8).

### 6.2 Funnel ops

```
Contrat SK + élèves SU + stops
  → Trip / SchoolDeparture (date) + assign-transport
  → Roster : PRESENT / ABSENT / BOARDED
  → Départ → Embarkation
  → Parent : GET /traveler/school/children
```

### 6.3 Évolutions vs vague 8

| ID | Évolution | Priorité |
|----|-----------|----------|
| S1 | Lier `SchoolDeparture` ↔ `Trip` + `transport` assigné | P0 |
| S2 | Empêcher départ sans véhicule (ou warning supervisor) | P0 |
| S3 | Capacité véhicule ≥ présents du jour | P0 |
| S4 | Facturation mensuelle / fee contrat (hors scope vague 8) | P1 |
| S5 | Notification parent (SMS) à l’embarquement | P1 |
| S6 | Multi-bus par contrat (matin / soir, aller / retour) | P1 |

### 6.4 APIs (delta)

```
PATCH /api/agency/school/departures/{id}/assign-transport
# body: { transportId, driverId? }
# crée / met à jour Trip lié + Assignment audit

POST /api/agency/school/departures   # existant — enrichir réponse transport
GET  /api/agency/trips?serviceType=SCHOOL&contract=&date=
```

### 6.5 Critères d’acceptation

- [ ] Départ scolaire avec bus + chauffeur visibles  
- [ ] Refus si bus déjà assigné à une autre course chevauchante  
- [ ] Roster + parent view non régressés  
- [ ] Permission `school:write` + `fleet:write` pour assignation  

---

## 7. Fleet — Assigner un bus à une course

### 7.1 Besoin UI (portail agence)

Dans **Fleet management** (et/ou fiche course / planning) :

1. Liste des courses du jour (filtre `serviceType`, statut)  
2. Action **Assigner un bus** → sheet : véhicule dispo + chauffeur  
3. Voir conflits (maintenance, overlap, permis expiré)  
4. Réassigner / retirer avec motif  
5. Lien depuis carte flotte / calendrier transport

### 7.2 Endpoint canonique

```
POST   /api/agency/trips/{id}/assign
PATCH  /api/agency/trips/{id}/assign      # réassignation
DELETE /api/agency/trips/{id}/assign      # unassign (si politique le permet)

Body:
{
  "transportId": "uuid",
  "driverId": "uuid|null",
  "force": false,           // admin only — ignore soft warnings
  "reason": "Remplacement panne"
}

Response 200:
{
  "tripId": "...",
  "status": "ASSIGNED",
  "transport": { "id", "label", "plateNumber", "capacity" },
  "driver": { "id", "name" } | null,
  "warnings": ["DRIVER_LICENSE_EXPIRES_IN_7D"],
  "assignmentId": "..."
}

Errors:
400 VALIDATION
403 FORBIDDEN (fleet:write)
404 TRIP_NOT_FOUND
409 TRANSPORT_NOT_AVAILABLE      # maintenance / inactive
409 TRANSPORT_SCHEDULE_CONFLICT  # overlap autre trip
409 CAPACITY_BELOW_SOLD          # sièges/places déjà vendus > capacité
409 DRIVER_SCHEDULE_CONFLICT
422 TRIP_NOT_ASSIGNABLE          # DEPARTED / CLOSED
```

### 7.3 Endpoint listing planning

```
GET /api/agency/fleet/trips?from=&to=&serviceType=&unassignedOnly=

# Pour UI « courses sans bus »
GET /api/agency/fleet/trips?unassignedOnly=true&date=2026-09-27
```

### 7.4 Disponibilité véhicule

```
GET /api/agency/fleet/transports/{id}/availability?from=&to=
# réutilise / étend AgencyTransportAvailability existant
```

Règles de dispo :

- status ∈ `ACTIVE`  
- pas de work-order bloquant  
- pas d’overlap trip  
- checklist départ OK (soft warning P1)

### 7.5 Permissions

| Action | Permission |
|--------|------------|
| Voir courses / assignations | `fleet:read` |
| Assigner / réassigner | `fleet:write` |
| Force assign | `ADMIN` / `SUPERVISOR` |
| Assignation scolaire | `fleet:write` **et** `school:write` (ou l’un des deux selon politique — **à trancher**) |

### 7.6 Événements / webhooks (recommandé)

| Event | Payload |
|-------|---------|
| `trip.transport_assigned` | tripId, transportId, driverId |
| `trip.transport_unassigned` | tripId, reason |
| `trip.capacity_changed` | oldCapacity, newCapacity |

---

## 8. Matrice comparative des 3 parcours

| Dimension | URBAN | INTERCITY | SCHOOL |
|-----------|-------|-----------|--------|
| `serviceType` | `URBAN` | `INTERCITY` | `SCHOOL` |
| Siège | Non | Oui | Non |
| Vente B2C online | Oui (si `onlineSales`) | Oui | Non |
| POS | Oui (rapide) | Oui | Non (ops roster) |
| Titre | Ticket / Pass | Ticket `VP-` + Pass ONT | Présence élève |
| Course | Trip capacité | Trip + seats | Trip + roster |
| Bus obligatoire avant vente | Option agence | Option / soft | Avant départ |
| FPT / Pass ONT | Non (sauf décision produit) | Oui | Non |
| Parent app | Non | Non | Oui (lecture) |
| Facturation | Unitaire / forfait | Par place | Mensuelle contrat (P1) |

---

## 9. Plan d’implémentation backend (phasé)

### Phase A — Fondations (1–1,5 sprints) — **bloquant**

1. Enum `serviceType` + `URBAN` + `seatMode`  
2. Entité `AgencyTrip` (+ migration, fixtures)  
3. Générateur de trips (cron / commande) depuis offres actives pour J…J+N  
4. API `assign` + contraintes overlap / capacité / maintenance  
5. Audit `AgencyTripAssignment`  
6. Adapter seat map INTERCITY → `Trip.transport`  
7. Tests unitaires + API (cas 409)

**DoD Phase A :** on peut créer une course INTERCITY, assigner un bus, vendre des sièges sur ce bus.

### Phase B — URBAN (1–1,5 sprints)

1. Zones / fares (minimal : origin-destination string OK en V1)  
2. Booking `CAPACITY_ONLY`  
3. Produit pass URBAN (étendre traveler passes)  
4. Validation boarding scan  
5. POS mode rapide  
6. Analytics recettes URBAN

**DoD Phase B :** vente unitaire + pass + full capacité en test.

### Phase C — SCHOOL × Fleet (0,5–1 sprint)

1. Brancher départs scolaires sur `Trip` + `assign`  
2. Garde-fous capacité vs présents  
3. Affichage transport sur roster / parent (label bus)

**DoD Phase C :** départ scolaire impossible (ou warn) sans bus ; front vague 8 consomme les nouveaux champs.

### Phase D — Durcissement (continu)

1. Remap sièges si changement de bus  
2. Webhooks + audit trail  
3. Notifications SMS parent / voyageur  
4. Facturation scolaire  
5. Observabilité (metrics Prometheus : assign conflicts, capacity full, sales by serviceType)

---

## 10. Migrations & compatibilité

| Étape | Action |
|-------|--------|
| M1 | Ajouter enum value `URBAN` (Doctrine) |
| M2 | Table `agency_trip` + `agency_trip_assignment` |
| M3 | Backfill : pour chaque (offer INTERCITY, date future avec bookings) → créer Trip lié au `offer.transport` |
| M4 | Colonne `ticket.trip_id` nullable → progressivement NOT NULL pour nouveaux tickets |
| M5 | SCHOOL departures : `trip_id` FK |
| M6 | Feature flag `TRIP_ASSIGNMENT_REQUIRED` par agence |

**Compat front :**  
- Réponses offers : toujours renvoyer `serviceType`  
- Trips : nouveaux endpoints ; seat map enrichi `vehicleUnassigned`  
- School departures : champs `transport` / `transportLabel` additionnels (non breaking)

---

## 11. Contrats d’erreur (standardiser)

| Code | HTTP | Usage |
|------|------|-------|
| `CAPACITY_FULL` | 409 | URBAN / SCHOOL |
| `SEAT_TAKEN` | 409 | INTERCITY (existant) |
| `TRANSPORT_SCHEDULE_CONFLICT` | 409 | Assign |
| `TRANSPORT_NOT_AVAILABLE` | 409 | Maintenance |
| `CAPACITY_BELOW_SOLD` | 409 | Réassign bus trop petit |
| `SEAT_LAYOUT_CONFLICT` | 409 | Layout différent après ventes |
| `TRIP_NOT_ASSIGNABLE` | 422 | Statut terminal |
| `SERVICE_TYPE_MISMATCH` | 400 | Ex. vendre URBAN sur trip SCHOOL |

Body erreur aligné API Platform / front `agencyApiError`.

---

## 12. Sécurité & conformité

- JWT + voters multi-tenant (agency scope)  
- RBAC : `fleet:write`, `school:write`, `pos:write`, `offers:write`  
- PII élèves : restreindre aux rôles school ; logs sans nom complet si possible  
- Idempotency-Key sur `POST /assign` et ventes POS  
- Rate-limit scans boarding URBAN  

---

## 13. Tests exigés (backend)

| Suite | Cas critiques |
|-------|----------------|
| Unit | Overlap scheduler ; capacity math ; seatMode branching |
| API | Assign happy path + 5× 409 |
| API | INTERCITY sale after assign uses correct layout |
| API | URBAN sell to full then reject |
| API | SCHOOL departure assign + roster |
| Migration | Backfill trips sans doublons |
| Load (P2) | 100 ventes concurrentes URBAN même trip |

---

## 14. Estimation indicative (backend seul)

| Phase | Effort (ordre de grandeur) |
|-------|----------------------------|
| A Fondations Trip + Assign | 8–12 j/dev |
| B URBAN | 8–12 j/dev |
| C SCHOOL bridge | 3–5 j/dev |
| D Durcissement | 5–8 j/dev |
| **Total** | **≈ 24–37 j/dev** |

À calibrer selon état réel des entités Embarkation / Fleet déjà en prod.

---

## 15. Questions ouvertes pour l’équipe backend

1. **Trip vs Embarkation :** nouvelle entité `Trip` ou Embarkation = course unique ?  
2. **URBAN Pass ONT / FPT :** exonéré ou assujetti ? (hypothèse doc : exonéré)  
3. **Assignation obligatoire avant vente INTERCITY :** soft (warning) ou hard ?  
4. **Changement de bus après ventes :** remap auto vs gel + remboursement ?  
5. **Génération des trips :** cron nuit J+14 ou à la volée au premier GET seats ?  
6. **Permissions school assign :** `fleet:write` seul ou croisement `school:write` ?  
7. **Multi-agence / location bus :** un transport loué peut-il être assigné (rentals vague 5) ?

---

## 16. Annexes — payloads exemples

### 16.1 Création course

```http
POST /api/agency/trips
Content-Type: application/json

{
  "offer": "/api/agency/offers/{id}",
  "travelDate": "2026-10-01",
  "departureTime": "06:00",
  "transport": null,
  "driver": null
}
```

### 16.2 Assigner un bus

```http
POST /api/agency/trips/{tripId}/assign
Content-Type: application/json

{
  "transportId": "{transportUuid}",
  "driverId": "{driverUuid}",
  "reason": "Planning semaine 40"
}
```

### 16.3 Vente URBAN (public)

```http
POST /api/public/agency/bookings
Content-Type: application/json

{
  "tripId": "{tripUuid}",
  "product": "UNIT",
  "passengerPhone": "+2438…",
  "quantity": 1,
  "paymentMethod": "MOBILE_MONEY"
}
```

### 16.4 Offre étendue

```json
{
  "label": "Navette centre — Bel-Air",
  "serviceType": "URBAN",
  "seatMode": "CAPACITY_ONLY",
  "onlineSales": true,
  "passEligible": true,
  "ticketPrice": 500,
  "currency": "CDF",
  "departureTime": "07:00",
  "defaultTransport": "/api/agency/transports/{id}"
}
```

---

## 17. Synthèse pour priorisation Product / Backend

| Priorité | Item |
|----------|------|
| **P0** | `Trip` + `assign` bus + conflits agenda |
| **P0** | Brancher seat map INTERCITY sur véhicule de course |
| **P0** | Bridge SCHOOL départ → assign transport |
| **P1** | Parcours URBAN complet (capacité + pass + POS) |
| **P1** | Audit assignations + webhooks |
| **P2** | Remap sièges, facturation scolaire, anti-fraude géofence |

---

**Contact front :** ce document est aligné sur le portail `Okapi-agency` (offres `serviceType`, flotte `/agency/fleet`, scolaire vague 8, compte voyageur).  
Dès validation du modèle `Trip` vs `Embarkation`, le front pourra ouvrir les tickets UI (sheet assignation, chip Communal, funnel sans seat map).
