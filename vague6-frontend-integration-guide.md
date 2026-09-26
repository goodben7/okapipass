# Vague 6 — Intégration front (backlog low / transverse MVP)

| Champ | Valeur |
|-------|--------|
| **Scope** | RBAC étendu, audit log, Idempotency-Key, webhooks stub, blacklist, règles mineurs, politique annulation/report, QR anti-copie, analytics corridors, tarifs datés, assurance optionnelle, ETA stub |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926220000` |
| **Hors scope** | POS offline (E3.3), correspondances multi-legs, overbooking/waitlist, API B2B volume, réseau revendeurs, GPS réel |

---

## RBAC — rôles `DRIVER` / `ACCOUNTANT` / `FLEET_MANAGER`

| Rôle | Permissions |
|------|-------------|
| `DRIVER` | `fleet:read`, `embarkation:write` |
| `ACCOUNTANT` | `accounting:read`, `payment:write`, `refund:write`, `cash:collect` |
| `FLEET_MANAGER` | `fleet:read`, `fleet:write`, `driver:write`, `maintenance:write`, `rental:write` |

```http
POST /api/agency/staff
{ "email": "…", "password": "…", "displayName": "…", "role": "DRIVER" }
```

---

## Audit log

Préfixe ID : **`AL`**

```http
GET /api/agency/audit-logs?from=2026-09-01&to=2026-09-30&action=pos.sale
```

Actions hookées (best-effort) : `pos.sale`, `cash.handover.confirm`, `ticket.cancel.approve`, `accounting.daily_close`, `ticket.reschedule`.

---

## Idempotency-Key (POS)

```http
POST /api/agency/pos/sales
Idempotency-Key: <client-uuid>
```

Rejeu → même `{ ticketId, paymentId, amount, … }` sans double vente. Table `idempotency_record` (`IK`).

---

## Webhooks (stub)

Préfixe **`WH`** — permission `staff:write`.

```http
GET/POST /api/agency/webhooks
GET/PATCH/DELETE /api/agency/webhooks/{id}
```

```json
{
  "url": "https://example.com/hooks",
  "secret": "shared-secret",
  "events": ["payment.paid", "ticket.issued"],
  "active": true
}
```

Dispatch HTTP POST best-effort (signature HMAC `X-OkapiPass-Signature`) depuis fulfillment FlexPay / ticket issue.

---

## Blacklist

Préfixe **`BL`** — permission `staff:write`.

```http
GET/POST /api/agency/blacklist
GET/PATCH/DELETE /api/agency/blacklist/{id}
```

```json
{ "type": "PHONE", "value": "+2438…", "reason": "fraude", "active": true }
```

`type`: `PHONE` \| `ID_DOCUMENT`. Bloque `POST /agency/pos/sales` et booking public → **422**.

---

## Mineurs / accompagnement

Sur offre : `minUnaccompaniedAge` (int\|null).

Sur vente POS / booking public (optionnel) :

| Champ | Type |
|-------|------|
| `passengerDateOfBirth` | `YYYY-MM-DD` |
| `escortTicketId` | string |
| `escortName` | string |

Si âge < `minUnaccompaniedAge` sans escort → **422**.

---

## Politique annulation / report

Colonnes agence (défauts) :

| Champ | Défaut |
|-------|--------|
| `cancelWindowHours` | 2 |
| `refundFeePercent` | 0 |
| `rescheduleFeeFlat` | 0 |

Cancel-request existant utilise `cancelWindowHours` de l’agence.

```http
POST /api/agency/tickets/{id}/reschedule
{ "offerId": "AO…", "travelDate": "2026-10-01", "seatNumber": "03A" }
```

Libère l’ancien siège, assigne le nouveau sous stock ; `rescheduleFee` renseigné sur le ticket.

---

## QR anti-copie MVP

À l’émission : token court TTL (24h) dans le ticket + champ `token` dans le payload QR (BC conservée).

```http
POST /api/agency/tickets/validate-qr
{ "token": "…" }
```

Permission `embarkation:write` — marque `usedAt`, passe le ticket en `BOARDED` si `ISSUED`.

---

## Analytics corridors / forecast

```http
GET /api/agency/analytics/corridors?from=&to=
```

→ `corridors[]` : `origin`, `destination`, `ticketsSold`, `capacityEstimate`, `occupancyPercent`.  
Permission : `accounting:read`.

```http
GET /api/agency/analytics/fill-forecast?offer=AO…&date=2026-09-26
```

→ `occupancyPercent` + `projectedFillPercent` (stub linéaire). Permission : `fleet:read`.

---

## Tarifs datés (thin)

Préfixe **`PH`** (`AgencyOfferPriceHistory`).  
PATCH `ticketPrice` sur offre → ferme la période ouverte et ouvre une nouvelle.  
Quote / POS / issuance utilisent le prix effectif à la date de voyage si historique présent.

---

## Nice-to-have livrés

### Assurance

Sur vente POS : `"insuranceOpted": true` → `insuranceFee` (500 CDF MVP) ajouté au ticket.

### ETA stub

```http
GET /api/agency/fleet/departures/{embarkationId}/eta
```

```json
{ "etaMinutes": 15, "lat": -4.3276, "lng": 15.3136, "status": "STUB" }
```

---

## Permissions (rappel)

| Clé | Usage Vague 6 |
|-----|----------------|
| `staff:write` | Webhooks, blacklist |
| `accounting:read` | Corridors analytics |
| `fleet:read` | Fill-forecast, ETA |
| `embarkation:write` | Validate QR |
| `ticket:write` | Reschedule |
| `pos:write` | Ventes + Idempotency |

---

## Notes migration

```bash
php bin/console doctrine:migrations:migrate -n
# Version20260926220000
```

Préfixes ID : `AL` (audit), `IK` (idempotency), `WH` (webhooks), `BL` (blacklist), `PH` (price history).
