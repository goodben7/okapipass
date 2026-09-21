# Guide d’intégration front — Dashboard ONT

| Champ | Valeur |
|-------|--------|
| **Audience** | Dev front portail ONT (`ROLE_ONT_ADMIN` / `ROLE_ONT_AGENT`) |
| **Endpoint** | `GET /api/ont/dashboard` |
| **Auth** | JWT Bearer |
| **Temps réel** | Polling recommandé (`pollSuggestedSeconds`, défaut **15 s**) |
| **Date** | 2026-09-21 |

---

## 1. Accès

```http
POST /api/authentication_token
Content-Type: application/json

{ "username": "ont-admin@…", "password": "…" }
```

Puis :

```http
GET /api/ont/dashboard
Authorization: Bearer {jwt}
Accept: application/json
```

Option période (KPI billets / Pass du mois) :

```http
GET /api/ont/dashboard?periodMonth=2026-08
```

| Qui | Accès |
|-----|--------|
| `ROLE_ONT_ADMIN` | oui |
| `ROLE_ONT_AGENT` | oui |
| `ROLE_SUPER_ADMIN` | oui |
| `ROLE_PARTNER` | **403** |

---

## 2. Shape réponse

```json
{
  "id": "ont-dashboard",
  "generatedAt": "2026-09-21T09:30:00+01:00",
  "periodMonth": "2026-09",
  "pollSuggestedSeconds": 15,
  "kpis": {
    "agenciesActive": 12,
    "agenciesTotal": 15,
    "ticketsToday": 42,
    "ticketsMonth": 1180,
    "passesActive": 540,
    "passesIssuedMonth": 87,
    "fptDraft": 9000,
    "fptSubmitted": 45000,
    "fptPaid": 210000,
    "fptDue": 54000,
    "currency": "CDF",
    "paymentsPending": 3,
    "paymentsPaidToday": 25
  },
  "fptByMonth": [
    {
      "periodMonth": "2026-08",
      "draft": 0,
      "submitted": 30000,
      "paid": 120000,
      "total": 150000
    }
  ],
  "recentDeclarations": [
    {
      "id": "PD…",
      "label": "FPT mensuel 2026-08",
      "source": "monthly",
      "status": "submitted",
      "periodMonth": "2026-08",
      "fptTotal": 30000,
      "currency": "CDF",
      "agencyId": "AG…",
      "agencyName": "Voyages Plus",
      "submittedAt": "…",
      "createdAt": "…"
    }
  ],
  "topAgenciesByFptDue": [
    {
      "agencyId": "AG…",
      "agencyName": "Voyages Plus",
      "fptDue": 30000,
      "currency": "CDF"
    }
  ],
  "alerts": [
    {
      "type": "FPT_MONTHLY_DRAFT",
      "severity": "warning",
      "message": "Voyages Plus : déclaration mensuelle 2026-07 encore en brouillon.",
      "agencyId": "AG…",
      "periodMonth": "2026-07",
      "declarationId": "PD…"
    }
  ]
}
```

---

## 3. UX recommandée

| Bloc UI | Source |
|---------|--------|
| Cartes KPI | `kpis.*` |
| Courbe / barres FPT | `fptByMonth` |
| Fil d’activité | `recentDeclarations` |
| Classement agences | `topAgenciesByFptDue` |
| Bannière alertes | `alerts` (`severity`: `warning` \| `critical`) |

**Polling « temps réel » :**
1. Au montage : `GET /ont/dashboard`
2. Toutes les `pollSuggestedSeconds` (15) : refetch
3. Afficher `generatedAt` (« Mis à jour à … »)
4. Pause le polling si l’onglet est caché (`document.visibilityState`)

Marquer FPT payé (existant) :

```http
PATCH /api/ont/fpt-declarations/{id}/pay
Authorization: Bearer {jwt}
```

(`ROLE_ONT_ADMIN` uniquement)

---

## 4. Checklist front ONT

- [ ] Login JWT ONT (pas PARTNER)
- [ ] Page `/ont/dashboard` avec KPIs
- [ ] Sélecteur mois `periodMonth`
- [ ] Polling 15 s + indicateur `generatedAt`
- [ ] Liste alertes + deep-link déclaration / agence
- [ ] Ne pas appeler `/api/agency/*` depuis le portail ONT

Tests backend : `tests/Functional/Ont/OntDashboardTest.php`
