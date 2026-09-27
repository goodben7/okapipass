# Vague 10 — Intégration front (parcours URBAN)

| Champ | Valeur |
|-------|--------|
| **Scope** | Offres `URBAN` + vente capacité (sans plan de sièges) + validate-boarding course |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926300000` |
| **Permissions** | Vente : `ticket:write` / POS ; embarquement : `embarkation:write` |
| **Précédent** | Vague 9 (assign bus ↔ course) — voir `vague9-frontend-integration-guide.md` |

---

## Modèle mental

```
Offer.serviceType = URBAN
Offer.seatMode    = CAPACITY_ONLY   (défaut si URBAN)
        │
        ▼
Vente (agence / POS / public)  →  ticket avec seatNumber = "GA"
        │                           (pas de plan de sièges)
        ▼
soldCount = bookings actifs + tickets manuels  (compteur lignes, pas sièges uniques)
        │
        ▼
POST …/validate-boarding { token }  →  BOARDED
```

| serviceType | seatMode défaut | Catalogue public | Siège requis |
|-------------|-----------------|------------------|--------------|
| `INTERCITY` | `ASSIGNED_SEAT` | oui si `onlineSales` | oui |
| `URBAN` | `CAPACITY_ONLY` | oui si `onlineSales` | non (défaut `GA`) |
| `SCHOOL` | `NONE` | **jamais** | n/a |

---

## 1. Créer / éditer une offre URBAN

```http
POST /api/agency/offers
PATCH /api/agency/offers/{id}
```

```json
{
  "label": "Ligne centre — Limete",
  "origin": "Gare Centrale",
  "destination": "Limete",
  "transport": "/api/agency/transports/ATxxxx",
  "ticketPrice": 1500,
  "currency": "CDF",
  "departureTime": "07:00",
  "durationMinutes": 45,
  "serviceType": "URBAN",
  "onlineSales": true,
  "active": true
}
```

Réponse : `serviceType=URBAN`, `seatMode=CAPACITY_ONLY` (sauf si `seatMode` est envoyé explicitement).

Filtres collection : `?serviceType=URBAN`.

---

## 2. Funnel front URBAN (recommandé)

1. **Catalogue** — `GET /api/public/agency/offers` (ou agence)  
   - Afficher `serviceType` + `seatMode`  
   - Si `CAPACITY_ONLY` → **pas d’étape plan de sièges**
2. **Disponibilité** — `GET …/seat-availability?travelDate=` ou public `…/seats?travelDate=`  
   - Lire `availableCount`, `soldCount`, `isFull`  
   - `layout.kind === "CAPACITY_ONLY"` → UI compteur, pas de grille
3. **Réservation / vente** — omettre `seatNumber` (ou envoyer `"GA"`)
4. **Paiement** — inchangé (public pay / POS cash)
5. **Embarquement** — `POST /api/agency/trips/{id}/validate-boarding` avec le token QR

### Erreurs vente

| HTTP | Message | Cas |
|------|---------|-----|
| 409 | `CAPACITY_FULL:…` | `sold + qty > capacity` |
| 422 | Offer has no transport | pas de véhicule résolu |
| 422 | Sélectionnez un siège… | offre `ASSIGNED_SEAT` sans siège |

---

## 3. Endpoints vente sans siège

```http
POST /api/agency/bookings
POST /api/agency/tickets
POST /api/agency/pos/sales
POST /api/public/agency/bookings
```

Body (extrait) — `seatNumber` **optionnel** si `CAPACITY_ONLY` :

```json
{
  "offer": "/api/agency/offers/AOxxxx",
  "passengerName": "Jean",
  "passengerId": "CD-1",
  "passengerPhone": "+2438…",
  "travelDate": "2026-09-30"
}
```

Public :

```json
{
  "offerId": "AOxxxx",
  "travelDate": "2026-09-30",
  "passengerName": "Jean",
  "passengerId": "CD-1",
  "passengerPhone": "+2438…"
}
```

---

## 4. Validate-boarding (course)

```http
POST /api/agency/trips/{id}/validate-boarding
POST /api/agency/embarkations/{id}/validate-boarding
```

```json
{ "token": "<qrToken du billet>" }
```

Réponse `200` :

```json
{
  "ticketId": "AKxxxx",
  "status": "BOARDED",
  "boardedCount": 12,
  "seatNumber": "GA",
  "passengerName": "Jean",
  "reference": "…",
  "embarkationId": "AExxxx"
}
```

Règles :
- Permission `embarkation:write`
- Le billet doit matcher `offer` + `travelDate` de la course
- L’embarquer ne consomme **pas** de capacité supplémentaire (la capacité = ventes)
- `POST /api/agency/tickets/validate-qr` reste disponible (sans check trip)

---

## 5. Pass produits URBAN

```http
POST /api/agency/pass-products
```

Champ optionnel `serviceType`: `null` (tous) | `INTERCITY` | `URBAN`.

MVP : achat pass wallet + vente unitaire séparés. Scan QR d’un billet lié à un pass décrémente via `consumeTripForTicket` (existant).

---

## 6. Fleet

```http
GET /api/agency/fleet/trips?date=2026-09-30&serviceType=URBAN
```

Déjà filtré sur `offer.serviceType`.

---

## 7. Analytics / journal

Filtrer plus tard le journal / KPI POS par `serviceType=URBAN`.  
Pas de nouveau endpoint KPI dans cette vague (optionnel).

---

## Checklist QA front

- [ ] Créer offre URBAN → `seatMode=CAPACITY_ONLY`, visible public si online
- [ ] Funnel sans étape sièges ; booking/POS sans `seatNumber` → 201, siège `GA`
- [ ] Remplir capacité → prochain 409 `CAPACITY_FULL`
- [ ] SCHOOL toujours hors catalogue public
- [ ] validate-boarding sur trip → 200 `BOARDED`
- [ ] INTERCITY inchangé (siège obligatoire + plan)
