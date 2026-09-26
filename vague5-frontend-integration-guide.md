# Vague 5 — Intégration front (backlog médium)

| Champ | Valeur |
|-------|--------|
| **Scope** | Caps promo/loyauté, KPI POS, bonus vendeur, ventilation ONT, multi-dépôt, alertes caisse, famille voyageur, missions chauffeur, docs RH, rapports flotte |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926200000` |
| **Hors scope** | POS offline (E3.3) |

---

## E2.6–2.7 — Caps promo / loyauté + audit bénéfices

### Champs promo (`Promotion`)

| Champ | Type | Description |
|-------|------|-------------|
| `maxDiscountAmount` | int\|null | Plafond absolu de la remise |
| `excludedWeekdays` | int[]\|null | 0=dim … 6=sam — jours exclus |
| `maxUsesPerPhone` | int\|null | Limite d’usage par téléphone |

Même logique optionnelle sur `LoyaltyRule` : `maxDiscountAmount`, `excludedWeekdays`.

```http
POST /api/agency/promotions
PATCH /api/agency/promotions/{id}
POST /api/agency/promotions/validate
```

Exemple création :

```json
{
  "code": "WEEKEND10",
  "label": "10% plafonné",
  "discountType": "percent_off",
  "discountValue": 10,
  "maxDiscountAmount": 5000,
  "excludedWeekdays": [1, 2],
  "maxUsesPerPhone": 2,
  "active": true
}
```

### Audit bénéfices

```http
GET /api/agency/loyalty/benefits?from=2026-09-01&to=2026-09-30&phone=+2438…
```

Réponse : `items[]` (ticketId, promoCode, loyaltyRuleId, discountAmount…), `totalDiscount`.

---

## E3.9 — KPI POS

```http
GET /api/agency/pos/kpi?date=2026-09-26
```

Structure :

```json
{
  "date": "2026-09-26",
  "ticketsCount": 12,
  "salesCount": 12,
  "caTotal": 480000,
  "mix": { "CASH": 300000, "MM": 150000, "CARD": 30000 },
  "bySeller": [{ "sellerId": "US…", "tickets": 5, "amount": 200000 }],
  "byPointOfSale": [{ "pointOfSale": "GARE-CENTRALE", "tickets": 8, "amount": 320000 }]
}
```

---

## E2.5 — Bonus vendeur POS

### Règles

```http
GET/POST /api/agency/pos/commission-rules
GET/PATCH /api/agency/pos/commission-rules/{id}
```

Body création :

```json
{
  "periodType": "day",
  "targetTickets": 10,
  "targetRevenue": 200000,
  "bonusType": "fixed",
  "bonusValue": 5000,
  "active": true
}
```

`periodType`: `day` \| `month` — `bonusType`: `percent` \| `fixed`.

À la fermeture d’une session POS, si les cibles sont atteintes → ligne `SellerBonusLedger` (`ACCRUED`), idempotente par vendeur+période+règle.

### Ledger

```http
GET /api/agency/pos/seller-bonuses
GET /api/agency/pos/seller-bonuses/{id}
```

---

## E4.5 — Ventilation ONT / plateforme

`AccountingAgencyManager::recordFromAgencyPayment` poste désormais :

| Compte | Direction | Contenu |
|--------|-----------|---------|
| CASH / MM / CARD | DEBIT | Encaissement (tender) |
| SALES | CREDIT | Portion billet (`amount − passPrice`) |
| PASS_ONT | CREDIT | `passPrice` si > 0 |
| COMMISSION | DEBIT | Frais plateforme (défaut **0 %**) |

```http
GET /api/agency/accounting/reports/margin?from=&to=
```

→ `ca`, `passOnt`, `commission`, `net`, `currency`.

---

## E4.7 — Multi-dépôt (mono-agence)

```http
GET/POST /api/agency/depots
GET/PATCH /api/agency/depots/{id}
```

```json
{ "code": "GOMBE", "label": "Dépôt Gombe", "active": true }
```

FK nullable `depot` sur `PosSession` et `AccountingDailyClose`.

```http
GET /api/agency/accounting/consolidation?from=&to=
```

Agrégats par dépôt ; sessions/closes sans dépôt → code `"DEFAULT"`.

---

## E4.8 — Alertes

À la confirmation d’un cash handover : `variance = declared − expectedCash`.  
Si `|variance| > 5000` → ligne journal `VARIANCE`.

```http
GET /api/agency/accounting/alerts
```

→ `cashVariances`, `agedPendingPayments` (>24h PENDING), `cancelRateSpike` (null MVP).

Permission : `accounting:read` (ADMIN / SUPERVISOR / partner owner).

---

## E1.8 — Groupe familial

```http
GET/POST /api/traveler/family
GET/PATCH/DELETE /api/traveler/family/{id}
```

JWT `ROLE_TRAVELER` — scoped au owner.

```json
{
  "fullName": "Marie Enfant",
  "phone": "+2438…",
  "relation": "CHILD",
  "dateOfBirth": "2015-06-01",
  "idDocument": "CD-…"
}
```

Relations : `CHILD` \| `SPOUSE` \| `PARENT` \| `OTHER`.

Optionnel : `beneficiaryId` sur `POST /api/traveler/preorders` pour préremplir passager.

---

## E5.1 — Mission du jour chauffeur

```http
GET /api/agency/fleet/driver-missions?date=2026-09-26&driver={driverId}
```

Par embarkation : chauffeur, transport, offre, `passengerCount`, `checklistStatus`.

---

## E5.5 — Docs RH chauffeur

```http
GET/POST /api/agency/driver-documents
GET/PATCH/DELETE /api/agency/driver-documents/{id}
```

Filtre : `?driver.id=AD…`  
Types : `PERMIT` \| `TRAINING` \| `SANCTION` \| `INSURANCE` \| `OTHER`.

Overview flotte enrichi : `expiringDriverDocuments`, `expiringDocuments[]`.

---

## E5.6 / 5.11–5.12 — Planning préventif & coût/km

Champs `AgencyTransport` :

- `nextServiceKm`, `nextServiceDate`
- `insuranceExpiresAt`, `technicalControlExpiresAt`

```http
GET /api/agency/fleet/reports/cost-per-km?transport=AT…&from=&to=
```

→ `fuelCost`, `maintenanceCost`, `deltaOdometer`, `costPerKm`.

Overview KPIs : `dueService`, `expiringInsurance`.

---

## E5 — Rapport flotte résumé

```http
GET /api/agency/fleet/reports/summary?from=&to=
```

→ `availabilityPercent`, `occupancyPercent`, `incidentCount`, `maintenanceCost`.

---

## Permissions

| Clé | Usage |
|-----|--------|
| `accounting:read` | Dépôts, rapports marge/consolidation/alertes |
| `pos:write` | KPI, commission-rules, sessions |
| `loyalty:write` | Promos / règles |
| `fleet:read` | Missions, rapports flotte |
| `driver:write` | Documents RH |

---

## Notes migration

```bash
php bin/console doctrine:migrations:migrate -n
# Version20260926200000
```

Préfixes ID : `TB` (bénéficiaires), `SC` (règles commission), `SB` (ledger bonus), `DP` (dépôts), `DD` (docs chauffeur).
