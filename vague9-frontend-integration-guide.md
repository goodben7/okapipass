# Vague 9 — Intégration front (assignation bus ↔ course)

| Champ | Valeur |
|-------|--------|
| **Scope** | Assignation véhicule (+ chauffeur) à une **course**, seat map INTERCITY sur le bus de course, bridge SCHOOL |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926280000` |
| **Permissions** | `fleet:write` (assign / unassign) ; pour une offre `SCHOOL`, `school:write` **ou** `fleet:write` suffit |
| **Architecture** | **Embarkation = Trip (course)**. Pas d’entité `AgencyTrip` séparée. Les URI `/agency/trips/*` sont des alias de `/agency/embarkations/*`. |

---

## Modèle mental

```
Offer (catalogue)
  └── AgencyEmbarkation / « Trip » (course datée, préfixe AE)
        ├── transport  (nullable = UNASSIGNED)
        ├── driver     (nullable)
        └── AgencyTripAssignment (audit, préfixe TA)
```

- Création d’embarquement : `transport` **optionnel** (`null` = course sans bus).
- Assignation : `POST/PATCH …/assign` pose le véhicule, ferme l’ancien TA (`unassignedAt`), ouvre un TA.
- Unassign : `DELETE …/assign` remet `transport` et `driver` à `null` (si politique OK).

---

## Assignation

```http
POST   /api/agency/embarkations/{id}/assign
PATCH  /api/agency/embarkations/{id}/assign
DELETE /api/agency/embarkations/{id}/assign

# Alias Trip
POST   /api/agency/trips/{id}/assign
PATCH  /api/agency/trips/{id}/assign
DELETE /api/agency/trips/{id}/assign
```

Body assign / reassign :

```json
{
  "transportId": "ATxxxxxxxx",
  "driverId": "ADxxxxxxxx",
  "force": false,
  "reason": "Remplacement panne"
}
```

Body unassign (optionnel) :

```json
{
  "force": false,
  "reason": "Bus en maintenance"
}
```

Réponse `200` :

```json
{
  "tripId": "AExxxx",
  "embarkationId": "AExxxx",
  "status": "ASSIGNED",
  "transport": { "id": "AT…", "label": "…", "plateNumber": "…", "capacity": 40 },
  "driver": { "id": "AD…", "name": "…" },
  "warnings": ["DRIVER_LICENSE_EXPIRES_IN_7D"],
  "assignmentId": "TAxxxx"
}
```

### Erreurs

| HTTP | Code (message) | Cas |
|------|----------------|-----|
| 422 | `TRIP_NOT_ASSIGNABLE` | Statut `DEPARTED` / `DECLARED` / `CLOSED` |
| 409 | `TRANSPORT_NOT_AVAILABLE` | Transport non `ACTIVE` (sauf `force`) |
| 409 | `TRANSPORT_SCHEDULE_CONFLICT` | Même bus, même jour, fenêtre horaire qui se chevauche (durée offre ou 4h) |
| 409 | `CAPACITY_BELOW_SOLD` | Capacité véhicule &lt; sièges déjà vendus / holds |
| 409 | `DRIVER_SCHEDULE_CONFLICT` | Même chauffeur déjà sur une autre course le même jour |
| 403 | Missing `fleet:write` | Permission insuffisante |

`force=true` : ignore dispo maintenance / status ACTIVE (admin). Pour unassign avec places vendues, `force` est requis.

---

## Listing flotte (planning)

```http
GET /api/agency/fleet/trips?date=2026-09-27&serviceType=INTERCITY&unassignedOnly=true
```

- Permission : `fleet:read`
- Réponse : `{ "id": "fleet-trips", "trips": [ … ] }`
- Chaque trip : `id` (= embarkation), `serviceType`, `transportId`, `unassigned`, etc.

Alias collection :

```http
GET /api/agency/trips
GET /api/agency/trips/{id}
```

(mêmes ressources que les embarkations)

---

## Audit assignations

```http
GET /api/agency/trip-assignments?embarkation.id=AExxxx
GET /api/agency/trip-assignments?embarkation=/api/agency/embarkations/AExxxx
```

Champs : `transport`, `driver`, `assignedBy`, `assignedAt`, `unassignedAt`, `reason`.

---

## Seat map INTERCITY → véhicule de course

```http
GET /api/agency/offers/{offerId}/seat-availability?travelDate=YYYY-MM-DD
GET /api/public/agency/offers/{offerId}/seats?travelDate=YYYY-MM-DD
```

Résolution :

1. Cherche l’embarkation `(offer, travelDate)`
2. Si `embarkation.transport` → layout / capacité de **ce** bus
3. Sinon → `offer.transport` + `vehicleUnassigned: true`

Champs ajoutés :

| Champ | Type |
|-------|------|
| `vehicleUnassigned` | bool |
| `transportId` | string? |
| `transportLabel` | string? |
| `plateNumber` | string? |
| `embarkationId` | string? |

---

## SCHOOL — assign transport

```http
PATCH /api/agency/school/departures/{id}/assign-transport
```

- `{id}` = id d’embarkation (`AE…`) créé par `POST /agency/school/departures`
- Body : même `AssignTripTransportDto` que ci-dessus
- Délègue à `AgencyEmbarkationManager::assign`

Création départ : si contrat / offre sans transport → embarkation **sans** bus (à assigner ensuite).

Roster enrichi :

```json
{
  "embarkationId": "AE…",
  "transportId": "AT…",
  "transportLabel": "Bus scolaire 2",
  "plateNumber": "…",
  "students": [ … ]
}
```

---

## Webhooks (best-effort)

Événements supplémentaires :

- `trip.transport_assigned` — `{ tripId, embarkationId, transportId, driverId, assignmentId }`
- `trip.transport_unassigned` — `{ tripId, embarkationId, reason }`

---

## Création embarkation sans transport

```http
POST /api/agency/embarkations
{
  "label": "Kin-Matadi J+3",
  "offer": "/api/agency/offers/AOxxxx",
  "departureDate": "2026-10-01",
  "departureTime": "06:00"
}
```

`transport` omis ou `null` → course unassigned ; puis `POST …/assign`.

---

## Checklist front

- [ ] UI flotte : liste `GET /agency/fleet/trips?unassignedOnly=true`
- [ ] Sheet assign bus → `POST /agency/trips/{id}/assign`
- [ ] Seat map : afficher warning si `vehicleUnassigned`
- [ ] School : bouton assign sur départ → `PATCH …/assign-transport`
- [ ] Afficher codes erreur `TRANSPORT_SCHEDULE_CONFLICT` / `CAPACITY_BELOW_SOLD` dans toasts
