# Vague 7 — Intégration front (partial gaps)

| Champ | Valeur |
|-------|--------|
| **Scope** | Profil voyageur, PDF/share billets, consommation forfaits, push stub, sièges premium, binding POS, résolution variance caisse, exports Excel, alertes cancelRateSpike, rapports flotte, attestation assurance, QR TTL 2h, fill forecast réel, dashboard DG, versioning horaires |
| **Migration** | `php bin/console doctrine:migrations:migrate` → `Version20260926240000` |
| **Hors scope** | POS offline (E3.3), correspondances, overbooking, B2B, revendeurs, RGPD, multilingue, notation, GPS réel |
| **E4.7** | Consolidation multi-dépôt = MVP **mono-agence** (pas d’entité `AgencyOrganization`) |

---

## E1.2 — Profil voyageur

```http
GET/PATCH /api/traveler/me
```

Champs ajoutés : `idDocument`, `emergencyContactName`, `emergencyContactPhone`, `preferences` (JSON).

---

## E1.3 — PDF billet voyageur

```http
GET /api/traveler/tickets/{id}/pdf
Authorization: Bearer <JWT TRAVELER>
```

`pdfUrl` sur `GET /api/traveler/tickets` → `/api/traveler/tickets/{id}/pdf`.

---

## E1.4 — Partage billet

```http
POST /api/traveler/tickets/{id}/share
{ "toPhone": "+243…" }
```

Réponse : `smsMessageId`, `shareUrl`, `whatsappUrl`, `shareToken`.

Public :

```http
GET /api/public/tickets/share/{token}
GET /api/public/tickets/share/{token}/pdf
```

Token TTL : 7 jours. SMS reste le canal primaire.

---

## E1.6 — Forfaits / passes

```http
GET  /api/traveler/passes
POST /api/traveler/passes/purchase
```

`TravelerPassManager::consumeTrip` décrémente `tripsRemaining` → `EXHAUSTED` à 0.

Sur `POST /api/agency/tickets/validate-qr` : si le billet a un FK `travelerPass`, consommation unitaire + refresh QR (TTL **2h**).

---

## E1.9 — Push stub

```http
POST /api/traveler/push-tokens
{ "deviceToken": "…", "platform": "ios|android" }
```

Stockage dans `User.preferences.pushTokens`. **Pas d’envoi** — SMS reste primaire.

---

## E2.6 — Sièges premium

- Layout : `seatClasses` (rangée 1 = `PREMIUM`, sinon `STANDARD`)
- `excludedSeatClasses` JSON sur `Promotion` / `LoyaltyRule`
- Remise refusée si classe exclue (POS passe `seatNumber`)

---

## E3.1 — Device binding POS

```http
POST /api/agency/pos/sessions/open
{ "pointOfSale": "…", "deviceId": "tablet-1", "pin": "1234" }
```

- `pin` obligatoire si le staff a un `pinHash`
- Deuxième session ouverte avec autre `deviceId` → **409**
- Close : `POST …/sessions/{id}/close?deviceId=tablet-1` (requis si `deviceId` stocké)

---

## E3.8 — Résolution variance

```http
POST /api/agency/pos/cash-handovers/{id}/resolve
{ "resolutionJustification": "…" }
```

Uniquement `CONFIRMED` avec `variance ≠ 0` → statut `RESOLVED`.

---

## E4.6 — Export journal Excel

```http
GET /api/agency/accounting/exports/journal.csv?from=&to=
GET /api/agency/accounting/exports/journal.xls?from=&to=
```

`.xls` = corps CSV `;` + MIME Excel.

---

## E4.8 — cancelRateSpike

`GET /api/agency/accounting/alerts` → `cancelRateSpike` si taux 7j > **15%** ou **≥ 2×** le taux des 7j précédents.

Dashboard DG :

```http
GET /api/agency/dashboard
```

Champs : `cashRiskCount`, `cancelRate7d`, `cancelRateToday`.

---

## E5 — Rapports flotte

| Endpoint | Contenu |
|----------|---------|
| `GET /agency/fleet/reports/cost-per-km?transport=&from=&to=` | + `litersPer100Km` |
| `GET /agency/fleet/reports/punctuality?from=&to=` | `actualDepartedAt` vs horaire planifié |
| `GET /agency/fleet/reports/drivers?from=&to=` | trips, onTimePercent, incidentCount |
| `GET /agency/fleet/reports/cost-by-corridor?from=&to=` | best-effort par corridor |

`actualDepartedAt` = `AgencyEmbarkation.departedAt` (posé au passage `DEPARTED` ou à la soumission checklist).

---

## Assurance

```http
GET /api/agency/tickets/{id}/insurance-attestation
```

PDF Dompdf si `insuranceOpted` ; sinon **422**.

---

## Fill forecast

`GET /agency/analytics/fill-forecast?offer=&date=` — moyenne d’occupation **même jour de semaine** (4–8 semaines), plus de stub `×1.2`.

---

## Versioning horaires (SH)

Préfixe ID : **`SH`**

```http
PATCH /api/agency/offers/{id}   # departureTime → append history
GET   /api/agency/offers/{offerId}/schedule-history
```

---

## Préfixes ID nouveaux

| Préfixe | Entité |
|---------|--------|
| `SH` | AgencyOfferScheduleHistory |
| (prefs) | push tokens dans `User.preferences` |

---

## Checklist front

- [ ] Profil voyageur (pièce + urgence)
- [ ] Téléchargement PDF membre + partage WhatsApp/lien
- [ ] Liste passes + affichage tripsRemaining
- [ ] POS : envoyer `deviceId` à l’open/close
- [ ] Resolve variance caisse
- [ ] Export `.xls` compta
- [ ] Dashboard `cashRiskCount` / `cancelRate7d`
- [ ] Rapports flotte punctualité / chauffeurs
- [ ] Attestation assurance (si option cochée)
