# Vague 4 — Intégration front (backlog prioritaire)

| Champ | Valeur |
|-------|--------|
| **Scope** | Conversion précommande, SMS voyageur, annulation supervisée, réconciliation FlexPay, export CSV, incidents flotte, colis légers |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926180000` |
| **Hors scope** | POS offline (E3.3), notifications push |

---

## E1.7 — Conversion précommande → billet

### Voyageur

```http
POST /api/traveler/preorders/{id}/convert
Authorization: Bearer {travelerJwt}
Content-Type: application/json
```

Body optionnel :

```json
{
  "seatNumber": "03B",
  "travelDate": "2026-09-28"
}
```

**Flux :** précommande `HELD` non expirée → réservation `ONLINE` + billet `ISSUED` → statut précommande `CONVERTED`, lien `booking`.

### Agence (confirm amélioré)

```http
PATCH /api/agency/preorders/{id}/confirm
```

Même logique métier : création booking + billet (plus seulement changement de statut).

---

## E1.9 — Notifications SMS voyageur

Service backend `TravelerNotificationService` (pas de push).

| Méthode | Usage |
|---------|-------|
| `notifyPaymentPaid(phone, ref)` | Hook après paiement FlexPay réussi si téléphone passager |
| `notifyLowWallet(phone, balance)` | Auto après débit si solde &lt; 5000 CDF (`TravelerWalletManager::LOW_BALANCE_THRESHOLD`) |
| `notifyDepartureReminder(...)` | Rappel départ |

**Commande cron (optionnelle) :**

```bash
php bin/console okapi:notify-departures
# ou date explicite :
php bin/console okapi:notify-departures --date=2026-09-27
```

Envoie un SMS aux billets `ISSUED` avec `travelDate` = demain (par défaut).

---

## E3.5 — Annulation limitée + approbation superviseur

Entité `AgencyTicketCancelRequest` (préfixe `CR`).

**Fenêtre d’annulation :** billet `ISSUED` créé il y a moins de **2 h** (`CANCEL_WINDOW_HOURS`) **OU** `travelDate` > aujourd’hui.

```http
POST /api/agency/tickets/{id}/cancel-requests
GET  /api/agency/ticket-cancel-requests
GET  /api/agency/ticket-cancel-requests/{id}
POST /api/agency/ticket-cancel-requests/{id}/approve
POST /api/agency/ticket-cancel-requests/{id}/reject
```

Body création :

```json
{ "reason": "Erreur de saisie" }
```

Body rejet (optionnel) :

```json
{ "notes": "Hors fenêtre d'annulation" }
```

**Approbation :** rôle `SUPERVISOR` ou permission `refund:write` → billet passé `CANCELLED`.

| AgencyTicketCancelRequest.status | `PENDING`, `APPROVED`, `REJECTED` |

---

## E4.4 — Réconciliation FlexPay

```http
GET /api/agency/accounting/reconciliation?from=2026-09-01&to=2026-09-30
```

Réponse :

```json
{
  "from": "2026-09-01",
  "to": "2026-09-30",
  "paidPaymentsCount": 42,
  "paidAmount": 3500000,
  "journalCreditCount": 40,
  "journalCreditAmount": 3400000,
  "unmatchedPaymentIds": ["AP…"],
  "unmatchedJournalSourceIds": ["AP…"]
}
```

Compare `AgencyPayment` `STATUS_PAID` (sur `paidAt`) vs écritures journal `SOURCE_AGENCY_PAYMENT` / `CREDIT`.

---

## E4.6 — Export CSV journal comptable

```http
GET /api/agency/accounting/exports/journal.csv?from=2026-09-01&to=2026-09-30
Authorization: Bearer {agencyJwt}
```

Réponse `text/csv` (téléchargement) — colonnes :

`id`, `entryDate`, `account`, `direction`, `amount`, `currency`, `sourceType`, `sourceId`, `label`

---

## E5.4 — Incidents flotte

Entité `AgencyFleetIncident` (préfixe `FI`). Permission : `fleet:write`.

```http
GET   /api/agency/fleet/incidents
GET   /api/agency/fleet/incidents/{id}
POST  /api/agency/fleet/incidents
PATCH /api/agency/fleet/incidents/{id}
POST  /api/agency/fleet/incidents/{id}/resolve
```

Body création :

```json
{
  "transport": "/api/agency/transports/AT…",
  "driver": "/api/agency/drivers/AD…",
  "type": "BREAKDOWN",
  "severity": "HIGH",
  "lat": -4.321,
  "lng": 15.312,
  "photoUrl": "https://…",
  "notes": "Panne moteur",
  "occurredAt": "2026-09-26T14:30:00+01:00"
}
```

| type | `BREAKDOWN`, `ACCIDENT`, `DELAY`, `OTHER` |
| severity | `LOW`, `MEDIUM`, `HIGH` |
| status | `OPEN`, `RESOLVED` |

---

## E6 — Colis légers

Entité `AgencyParcel` (préfixe `PL`). Code suivi auto `PL-XXXX` (unique par agence). Permission : `booking:write`.

```http
GET   /api/agency/parcels
GET   /api/agency/parcels/{id}
POST  /api/agency/parcels
PATCH /api/agency/parcels/{id}
POST  /api/agency/parcels/{id}/deliver
POST  /api/agency/parcels/{id}/cancel
```

Body création :

```json
{
  "offer": "/api/agency/offers/AO…",
  "transport": "/api/agency/transports/AT…",
  "embarkation": "/api/agency/embarkations/AE…",
  "senderName": "Jean",
  "senderPhone": "+243810011223",
  "recipientName": "Marie",
  "recipientPhone": "+243820033445",
  "weightKg": 12.5,
  "fee": 5000,
  "currency": "CDF",
  "travelDate": "2026-09-27",
  "notes": "Fragile"
}
```

| AgencyParcel.status | `BOOKED`, `IN_TRANSIT`, `DELIVERED`, `CANCELLED` |

---

## Tests fonctionnels

```bash
php bin/phpunit tests/Functional/Agency/HighPriorityBacklogTest.php
```

Couvre : approbation annulation, création colis, création incident, smoke réconciliation.

---

## Récap préfixes ID

| Préfixe | Entité |
|---------|--------|
| CR | AgencyTicketCancelRequest |
| FI | AgencyFleetIncident |
| PL | AgencyParcel |
