# Vague 3 — Intégration front (flotte ops, bagages, manifeste)

| Champ | Valeur |
|-------|--------|
| **Scope** | Carburant, checklist départ, ordres de travail, franchise bagages, manifeste, no-show |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926160000` |
| **Hors scope** | Sync POS offline (E3.3) |

---

## E5 — Opérations flotte

Permissions : `fleet:write` (carburant), `driver:write` ou `fleet:write` (checklist), `maintenance:write` (OT).

### Carburant (`FL…`)

```http
GET  /api/agency/fleet/fuel-logs
GET  /api/agency/fleet/fuel-logs/{id}
POST /api/agency/fleet/fuel-logs
```

Body création :

```json
{
  "transport": "/api/agency/transports/AT…",
  "driver": "/api/agency/drivers/AD…",
  "liters": 120,
  "amount": 360000,
  "currency": "CDF",
  "odometerKm": 84500,
  "fueledAt": "2026-09-26T08:30:00+01:00",
  "notes": "Plein route"
}
```

### Checklist départ (`CK…`)

```http
GET  /api/agency/fleet/departures/checklists
GET  /api/agency/fleet/departures/checklists/{id}
POST /api/agency/fleet/departures/checklists
POST /api/agency/fleet/departures/checklists/{id}/submit
```

Body création :

```json
{
  "transport": "/api/agency/transports/AT…",
  "driver": "/api/agency/drivers/AD…",
  "offer": "/api/agency/offers/AO…",
  "travelDate": "2026-09-27",
  "odometerKm": 120500,
  "fuelLevelPercent": 85,
  "vehicleOk": true,
  "notes": "Contrôle OK"
}
```

| AgencyDepartureChecklist.status | `DRAFT`, `SUBMITTED` |

### Ordres de travail (`WO…`)

Routes sous `/api/agency/fleet/maintenance/work-orders` :

```http
GET   /api/agency/fleet/maintenance/work-orders
GET   /api/agency/fleet/maintenance/work-orders/{id}
POST  /api/agency/fleet/maintenance/work-orders
PATCH /api/agency/fleet/maintenance/work-orders/{id}
POST  /api/agency/fleet/maintenance/work-orders/{id}/start
POST  /api/agency/fleet/maintenance/work-orders/{id}/complete
POST  /api/agency/fleet/maintenance/work-orders/{id}/cancel
```

Body création :

```json
{
  "transport": "/api/agency/transports/AT…",
  "maintenanceCase": "/api/agency/maintenance-cases/MC…",
  "title": "Remplacement embrayage",
  "description": "Immobilisation atelier",
  "partsCost": 180000,
  "laborCost": 90000,
  "immobilize": true,
  "vendorName": "Garage Nord"
}
```

| AgencyWorkOrder.status | `OPEN`, `IN_PROGRESS`, `DONE`, `CANCELLED` |

**Effet véhicule** : si `immobilize=true` et OT ouvert → transport `MAINTENANCE`. Restauration `ACTIVE` quand plus aucun cas MC bloquant ni OT immobilisant ouvert.

---

## E6 — Bagages & manifeste

### Politique bagages sur l'offre

Champs ajoutés sur `AgencyOffer` (PATCH `/api/agency/offers/{id}`) :

| Champ | Défaut | Description |
|-------|--------|-------------|
| `baggageFreeKg` | 20 | Franchise incluse (kg) |
| `baggageExcessPricePerKg` | 0 | Tarif excédent / kg (CDF) |
| `noShowReleaseMinutes` | 30 | Minutes avant départ pour libérer les no-shows |

### Enregistrement bagage (`BX…`)

```http
POST /api/agency/tickets/{id}/baggage
{ "kg": 35 }
```

Réponse :

```json
{
  "excess": { "id": "BX…", "kg": 35, "freeKgApplied": 20, "excessKg": 15, "amount": 37500, "status": "RECORDED", … },
  "amount": 37500,
  "excessKg": 15
}
```

- Met à jour `ticket.baggageKg`
- Calcule l'excédent depuis la politique de l'offre
- Statut `WAIVED` si montant = 0, sinon `RECORDED`

```http
GET /api/agency/baggage-excesses
GET /api/agency/baggage-excesses/{id}
```

| AgencyBaggageExcess.status | `RECORDED`, `PAID`, `WAIVED` |

### Manifeste passagers

```http
GET /api/agency/manifests?offerId=AO…&travelDate=2026-09-26
```

Réponse :

```json
{
  "offerId": "AO…",
  "travelDate": "2026-09-26",
  "tickets": [
    { "id": "AK…", "reference": "…", "passengerName": "…", "seatNumber": "02A", "status": "ISSUED", "baggageKg": 35 }
  ],
  "boardedCount": 0,
  "issuedCount": 12,
  "noShowCount": 0
}
```

### Libération no-shows

```http
POST /api/agency/manifests/release-noshows
{ "offerId": "AO…", "travelDate": "2026-09-26" }
→ { "releasedCount": 3, "offerId": "AO…", "travelDate": "2026-09-26" }
```

Règles MVP :
- Date passée → libère tous les billets `ISSUED` non embarqués
- Date du jour → libération autorisée si `now >= departureTime - noShowReleaseMinutes`
- Billets passés en `NO_SHOW` → siège libéré (comme `CANCELLED`)

| AgencyTicket.status (ajout) | `NO_SHOW` |

---

## Permissions utilisées

| Endpoint | Permission |
|----------|------------|
| Fuel logs (POST) | `fleet:write` |
| Checklists (POST/submit) | `driver:write` **ou** `fleet:write` |
| Work orders | `maintenance:write` |
| Baggage ticket | `ticket:write` |
| Manifeste / release no-shows | `embarkation:write` |

Les rôles ADMIN/partenaire incluent déjà ces permissions via `GET /api/agency/me`.

---

## Hors scope Vague 3

- Sync POS offline (E3.3)
- Paiement encaissement excédent bagage (`PAID` manuel côté guichet — statut à basculer ultérieurement)
- Export PDF manifeste / F12
