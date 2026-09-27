# Vague 8 — Intégration front (bus scolaire)

| Champ | Valeur |
|-------|--------|
| **Scope** | Offres `serviceType` SCHOOL/INTERCITY, contrats scolaires, élèves, présence journalière, roster, départ → embarquement |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926260000` |
| **Permission** | `school:write` (ADMIN via defaults, SUPERVISOR, FLEET_MANAGER) |
| **Hors scope** | Facturation scolaire automatisée, GPS trajet, app parents, multi-école B2B |

---

## Offres — `serviceType`

```http
POST /api/agency/offers
{
  "label": "Navette Lycée Kimwenza",
  "origin": "Kimwenza",
  "destination": "Lycée",
  "transport": "/api/agency/transports/{id}",
  "ticketPrice": 0,
  "departureTime": "06:30",
  "durationMinutes": 45,
  "serviceType": "SCHOOL",
  "onlineSales": true
}
```

- Valeurs : `INTERCITY` (défaut) | `SCHOOL`
- Si `serviceType=SCHOOL` → **`onlineSales` forcé à `false`** (create + update)
- Filtre collection agence : `?serviceType=SCHOOL`
- **Catalogue public** (`GET /api/public/agency/offers`) : uniquement `INTERCITY` + `onlineSales=true` — une offre SCHOOL n’apparaît jamais, même si `onlineSales` était mal positionné

---

## Contrats scolaires

```http
GET/POST  /api/agency/school-contracts
GET/PATCH /api/agency/school-contracts/{id}
DELETE    /api/agency/school-contracts/{id}
```

Création :

```json
{
  "schoolName": "Lycée Kimwenza",
  "schoolPhone": "+243…",
  "schoolAddress": "…",
  "offer": "/api/agency/offers/{id}",
  "transport": "/api/agency/transports/{id}",
  "startDate": "2026-09-01",
  "endDate": "2027-06-30",
  "status": "ACTIVE",
  "monthlyFee": 150000,
  "currency": "CDF",
  "stops": [
    { "code": "STOP1", "label": "Carrefour", "order": 1, "time": "06:15" },
    { "code": "SCHOOL", "label": "Lycée", "order": 2, "time": "07:00" }
  ],
  "notes": "Année scolaire 2026-27"
}
```

- Prefix ID : **`SK`** (pas `SC` — réservé à `SellerCommissionRule`)
- `offer` doit être `serviceType=SCHOOL` sinon **422**
- Statuts : `DRAFT` | `ACTIVE` | `ENDED` | `CANCELLED`

---

## Élèves

```http
GET/POST  /api/agency/school-students
GET/PATCH /api/agency/school-students/{id}
DELETE    /api/agency/school-students/{id}
```

```json
{
  "contract": "/api/agency/school-contracts/{id}",
  "fullName": "Kabongo Junior",
  "phone": "+243…",
  "grade": "6ème",
  "pickupStopCode": "STOP1",
  "dropoffStopCode": "SCHOOL",
  "active": true,
  "externalRef": "MAT-001"
}
```

- Prefix ID : **`SU`**
- Filtres : `?contract.id=…`, `?active=true`, `?fullName=kabongo`
- Si le contrat a des `stops` et que `pickupStopCode` / `dropoffStopCode` ne matchent pas → **422**

---

## Roster du jour

```http
GET /api/agency/school/roster?contractId={SK…}&date=2026-09-27
```

Réponse :

```json
{
  "id": "SK…-2026-09-27",
  "contractId": "SK…",
  "date": "2026-09-27",
  "embarkationId": "AE…",
  "students": [
    {
      "id": "SU…",
      "fullName": "Kabongo Junior",
      "phone": "+243…",
      "grade": "6ème",
      "pickupStopCode": "STOP1",
      "dropoffStopCode": "SCHOOL",
      "attendanceStatus": "PRESENT",
      "attendanceId": "SA…"
    }
  ]
}
```

Élèves **actifs** uniquement. `embarkationId` si une embarquement existe déjà pour `contract.offer` + date.

---

## Présence

```http
POST /api/agency/school/attendance
{
  "contractId": "SK…",
  "date": "2026-09-27",
  "studentId": "SU…",
  "status": "PRESENT"
}
```

- Statuts : `PRESENT` | `ABSENT` | `BOARDED`
- Prefix ID : **`SA`**
- Unique `(student, attendanceDate)` — re-POST met à jour le statut
- Réponse : entité `SchoolAttendance`

---

## Départ / embarquement (helper)

```http
POST /api/agency/school/departures
{
  "contractId": "SK…",
  "date": "2026-09-27",
  "transportId": "AT…",
  "driverId": "AD…"
}
```

- Crée ou réutilise une `AgencyEmbarkation` pour l’offre du contrat + date
- Transport : `transportId` → sinon transport du contrat → sinon transport de l’offre
- Alternative front : créer l’embarquement via `POST /api/agency/embarkations` avec l’offre SCHOOL

---

## Parcours type front

1. Créer offre `serviceType=SCHOOL` (hors catalogue public)
2. Créer contrat + arrêts JSON
3. Inscrire élèves
4. Chaque matin : `GET roster` → `POST attendance` → optionnel `POST departures`
