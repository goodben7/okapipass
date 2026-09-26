# Vague 2 — Intégration front (wallet, passes, précommandes, compta, fidélité)

| Champ | Valeur |
|-------|--------|
| **Scope** | Wallet voyageur, passes multi-jours, précommandes, journal compta, points/surprises |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926140000` |
| **Webhook FlexPay** | Les recharges wallet utilisent la référence `WT…` (id topup) |

---

## E4 — Wallet voyageur

Auth : `Authorization: Bearer {token}` + rôle `ROLE_TRAVELER` (OTP Vague 1).

```http
GET  /api/traveler/wallet
→ { "id": "WA…", "balance": 0, "currency": "CDF", "userId": "US…" }

GET  /api/traveler/wallet/ledger?limit=50
→ collection { id, type, amount, balanceAfter, reference, label, createdAt }

POST /api/traveler/wallet/topups
{ "amount": 5000, "phone": "+243810012345", "method": "MOBILE_MONEY" }
→ { "id": "WT…", "status": "PENDING", "providerTx": "…" }

GET  /api/traveler/wallet/topups/{id}
```

### Flux recharge Mobile Money

1. Le voyageur crée un topup → statut `PENDING`, FlexPay reçoit `reference = WT{id}`.
2. Webhook FlexPay (`POST /api/flexpay/webhook`) : résolution par `WT…` ou `providerTx`.
3. Succès → `fulfillTopup` : statut `PAID`, crédit wallet, écriture ledger `TYPE_TOPUP`.
4. Échec → statut `FAILED`.

### Enums wallet

| Entité | Constantes |
|--------|------------|
| **WalletTopup.status** | `PENDING`, `PAID`, `FAILED`, `CANCELLED` |
| **WalletTopup.method** | `MOBILE_MONEY`, `CARD` |
| **WalletLedger.type** | `TOPUP`, `DEBIT`, `ADJUST`, `REFUND` |

---

## E5 — Passes multi-jours

### Agence (CRUD produits)

```http
GET/POST  /api/agency/pass-products
PATCH     /api/agency/pass-products/{id}
```

Body création :

```json
{
  "code": "WEEKLY-KIN-LUB",
  "label": "Pass hebdo Kin-Lub",
  "origin": "Kinshasa",
  "destination": "Lubumbashi",
  "tripsAllowed": 4,
  "validityDays": 7,
  "price": 45000,
  "currency": "CDF",
  "active": true
}
```

### Voyageur

```http
GET  /api/traveler/pass-products
POST /api/traveler/passes/purchase
{ "productId": "PP…", "payWithWallet": true }
```

**v1** : seul `payWithWallet: true` est accepté. Débit wallet + création `TravelerPass` `ACTIVE` (`validFrom=aujourd'hui`, `validUntil=+validityDays`).

| TravelerPass.status | `ACTIVE`, `EXHAUSTED`, `EXPIRED`, `CANCELLED` |

---

## E6 — Précommandes

```http
POST /api/traveler/preorders
{
  "offerId": "AO…",
  "travelDate": "2026-10-15",
  "quantity": 1,
  "passengerName": "Jean Dupont"
}
```

- Statut initial : `HELD`
- `holdUntil` = now + `offer.preorderHoldHours` (défaut 72 h)
- Réservation online future : hold étendu à `max(bookingHoldMinutes, preorderHoldHours × 60)` minutes

### Agence

```http
GET   /api/agency/preorders
GET   /api/agency/preorders/{id}
PATCH /api/agency/preorders/{id}/confirm
PATCH /api/agency/preorders/{id}/cancel
```

| TravelerPreorder.status | `HELD`, `CONVERTED`, `EXPIRED`, `CANCELLED` |

---

## E7 — Comptabilité agence

```http
GET  /api/agency/accounting/journal?entryDate[after]=2026-09-01&entryDate[before]=2026-09-30&account=MM
GET  /api/agency/accounting/daily-closes
GET  /api/agency/accounting/daily-closes/{id}
POST /api/agency/accounting/daily-closes
{ "businessDate": "2026-09-26", "notes": "Clôture guichet" }
```

- Journal auto après paiement agence `PAID` (online) : entrée `CREDIT` sur canal `CASH` / `MM` / `CARD`.
- Clôture journalière idempotente : une seule clôture `CLOSED` par agence et par date UTC.

| AccountingJournal.account | `SALES`, `CASH`, `MM`, `CARD`, `WALLET`, `COMMISSION`, `PASS_ONT`, `VARIANCE` |
| AccountingJournal.direction | `DEBIT`, `CREDIT` |
| AccountingJournal.sourceType | `AGENCY_PAYMENT`, `CASH_HANDOVER`, `WALLET_TOPUP`, `MANUAL` |

---

## E8 — Points & surprises

### Règles fidélité (extension Vague 1)

```http
POST /api/agency/loyalty/rules
{
  "label": "Points par billet",
  "triggerType": "trip_count",
  "window": "lifetime",
  "threshold": 0,
  "rewardType": "points",
  "rewardValue": 0,
  "pointsEarn": 50,
  "active": true
}
```

Champs ajoutés : `surprisePool` (ref `SP…`), `pointsEarn`.

Attribution simplifiée : à chaque billet payé online, si règle active `rewardType=points`, crédit `pointsEarn`.

### Pools surprise

```http
GET/POST  /api/agency/surprise-pools
PATCH     /api/agency/surprise-pools/{id}
POST      /api/agency/surprise-pools/{id}/items
{ "label": "10% off", "rewardType": "percent_off", "rewardValue": 10, "weight": 3 }
POST      /api/agency/surprise-pools/{id}/draw
{ "userId": "US…" }
→ { "itemId", "rewardType", "rewardValue", "pointsCredited?" }
```

Tirage pondéré (`weight`) parmi items actifs. Si `rewardType=points`, crédit compte fidélité du voyageur.

### Voyageur — solde points

```http
GET /api/traveler/loyalty?agencyId=AG…
→ { "id": "LA…", "agencyId", "phone", "points" }
```

| LoyaltyPointLedger.reason | `EARN_TRIP`, `REDEEM`, `SURPRISE`, `ADJUST` |
| SurprisePoolItem.rewardType | `percent_off`, `fixed_off`, `free_seat`, `points` |

---

## Webhook FlexPay — ordre de résolution

1. Paiement B2C (`PaymentManager`)
2. Topup wallet (`TravelerWalletManager`, ref `WT…`)
3. Paiement agence online (`PublicAgencyPaymentManager`, ref `ABP…`)

---

## Hors scope Vague 2

- Topup carte wallet
- Achat pass sans wallet
- `remainingQuantity` sur items surprise (tirage sans décrément stock)
- Conversion automatique précommande → booking
