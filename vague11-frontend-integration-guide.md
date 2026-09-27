# Vague 11 — Intégration front (Phase D / P2)

| Champ | Valeur |
|-------|--------|
| **Scope** | Remap sièges INTERCITY, factures scolaires, anti-fraude boarding (cooldown + géofence soft) |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926320000` |
| **Permissions** | Assign / remap : `fleet:write` ; factures scolaires : `school:write` ; boarding : `embarkation:write` |
| **Précédent** | Vague 9 (assign) + Vague 10 (URBAN) |

---

## 1. Remap sièges / `SEAT_LAYOUT_CONFLICT`

Lors d’un `POST …/assign` (ou reassign) sur une course **ASSIGNED_SEAT / INTERCITY** avec sièges déjà vendus :

| Cas | HTTP | Comportement |
|-----|------|--------------|
| Tous les sièges vendus existent sur le nouveau layout | 200 | Assign OK, `remappedSeats: []` |
| Sièges manquants, `force=false` | 409 | Message commence par `SEAT_LAYOUT_CONFLICT` |
| Sièges manquants, `force=true` | 200 | Remap vers premiers sièges libres ; `remappedSeats` rempli |
| Pas assez de places libres pour remap | 409 | `CAPACITY_BELOW_SOLD` |

```http
POST /api/agency/embarkations/{id}/assign
POST /api/agency/trips/{id}/assign
```

```json
{
  "transportId": "ATxxxx",
  "force": true,
  "reason": "Remplacement bus"
}
```

Réponse enrichie :

```json
{
  "tripId": "AExxxx",
  "status": "ASSIGNED",
  "transport": { "id": "AT…", "capacity": 8 },
  "remappedSeats": [
    { "ticketId": "AKxxxx", "from": "01D", "to": "01A" }
  ],
  "warnings": [],
  "assignmentId": "TAxxxx"
}
```

**UI :** si 409 `SEAT_LAYOUT_CONFLICT`, proposer confirmation admin (`force=true`) et afficher le détail des remaps après succès.

---

## 2. Factures scolaires (préfixe **IV**)

> Préfixe `IV` (pas `SI` — réservé à `SurprisePoolItem`).

```http
POST /api/agency/school-contracts/{id}/invoices/generate
GET  /api/agency/school-invoices?contract.id=&status=
GET  /api/agency/school-invoices/{id}
POST /api/agency/school-invoices/{id}/mark-paid
POST /api/agency/school-invoices/{id}/cancel
```

### Générer

```json
{ "periodYm": "2026-10" }
```

- `amount` = snapshot de `contract.monthlyFee` (422 si fee = 0)
- Idempotent : régénérer la même période renvoie la facture existante
- Statuts : `DRAFT` \| `ISSUED` \| `PAID` \| `CANCELLED`

### Marquer payée

```json
{ "notes": "Virement reçu" }
```

→ `status=PAID`, `paidAt` renseigné ; écriture comptable CREDIT `SALES` (source `SCHOOL_INVOICE`) si applicable.

### Annuler

`POST …/cancel` — interdit si déjà `PAID`.

---

## 3. Anti-fraude boarding

### Cooldown (5 min)

Constante : `AgencyQrPayloadBuilder::BOARDING_COOLDOWN_SECONDS = 300`.

- Ticket déjà scanné récemment (`lastBoardedAt`) → **409** `BOARDING_COOLDOWN:…`
- Pass voyageur URBAN consommé récemment (`lastConsumedAt`) → même code

Le token QR est toujours rotaté après un scan réussi ; le second scan utilise le **nouveau** token du billet, mais le cooldown bloque.

### Géofence (soft, MVP)

Body optionnel :

```json
{
  "token": "…",
  "lat": -4.3276,
  "lng": 15.3136
}
```

Réponse :

```json
{
  "ticketId": "AKxxxx",
  "status": "BOARDED",
  "boardedCount": 1,
  "geofenceWarning": true,
  "warnings": ["GEOFENCE_UNCHECKED"]
}
```

| Warning | Signification |
|---------|----------------|
| `GEOFENCE_UNCHECKED` | Coords fournies mais aucun point de référence dépôt/transport |
| `GEOFENCE_DISTANCE` | Distance > 500 m d’un point de référence (futur) |

**Pas de hard-block** en MVP : le boarding réussit toujours ; afficher un bandeau warning côté ops.

---

## 4. Checklist front

- [ ] Sheet assign bus : gérer `SEAT_LAYOUT_CONFLICT` + confirmation `force`
- [ ] Afficher `remappedSeats` après reassign forcé
- [ ] Écran factures scolaires (liste + generate + mark-paid)
- [ ] Scan boarding : gérer `BOARDING_COOLDOWN` (toast « réessayez dans 5 min »)
- [ ] Optionnel : envoyer `lat`/`lng` device et afficher `geofenceWarning`

---

## 5. Tests backend

```bash
php bin/phpunit tests/Functional/Agency/PhaseDHardeningTest.php
```
