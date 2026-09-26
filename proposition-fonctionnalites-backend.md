# Proposition fonctionnelle — MBIYO Lualaba Mobilité

| Champ | Valeur |
|-------|--------|
| **Document** | Cahier des fonctionnalités à soumettre à l’équipe backend |
| **Produit** | MBIYO Lualaba Mobilité (front Okapi-agency + API OkapiPass) |
| **Version** | 1.0 |
| **Date** | 2026-09-26 |
| **Audience** | Backend (Symfony / API Platform), Product, Front |
| **Statut** | Proposition — à prioriser / estimer |

---

## 1. Objectif

Étendre la plateforme au-delà de la vente billet + portail agence actuels, pour couvrir :

1. Un **espace membre voyageur** (compte, historique, crédit, précommandes)
2. Un **programme fidélité / offres** (bonus, réductions, surprises par trajet)
3. Une **interface POS vendeurs** (bus / cash) + **cash collect**
4. Une **comptabilité transporteur** renforcée
5. Une **flotte** complète (chauffeurs, maintenance, rapports)
6. Des modules **recommandés** issus des pratiques inter-urbain / inter-province / urbain

Chaque épique ci-dessous décrit : **besoin métier**, **périmètre fonctionnel**, **entités / APIs attendues**, **règles**, **dépendances**.

---

## 2. Synthèse des épiques

| # | Épique | Priorité suggérée | Complexité |
|---|--------|-------------------|------------|
| E1 | Espace membre & voyageurs | P0 | Haute |
| E2 | Fidélité, bonus & offres surprises | P1 | Moyenne–Haute |
| E3 | POS vendeurs + cash collect | P0 | Haute |
| E4 | Comptabilité transporteur | P1 | Haute |
| E5 | Flotte avancée (chauffeurs, maintenance, rapports) | P1 | Haute |
| E6 | Modules additionnels (recommandés) | P2 | Variable |

---

## 3. E1 — Espace membre & utilisateurs (voyageurs)

### 3.1 Besoin

Permettre au public d’avoir un **compte personnel** pour :

- Voir et retrouver ses billets payés
- Gérer un **solde / crédit** (wallet)
- Acheter des **tickets multi-jours** ou forfaits
- **Précommander** des billets (hold long / réservation différée)

### 3.2 Fonctionnalités

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E1.1 | Inscription / connexion voyageur | Téléphone (OTP SMS) + email optionnel ; JWT `TRAVELER` |
| E1.2 | Profil membre | Nom, pièce d’identité, contacts d’urgence, préférences |
| E1.3 | Historique billets | Liste filtrable (à venir / passés / annulés) + détail + PDF/QR |
| E1.4 | Partage billet | Envoi WhatsApp / SMS / lien token au bénéficiaire |
| E1.5 | Wallet / crédit | Recharge Mobile Money / carte ; débit à l’achat ; historique mouvements |
| E1.6 | Tickets multi-jours | Produit « N trajets » ou « validité J jours » sur corridor défini |
| E1.7 | Précommande | Réservation sans départ immédiat ; conversion en billet à une date choisie (sous stock) |
| E1.8 | Groupe familial | Compte parent + bénéficiaires (enfants / proches) |
| E1.9 | Notifications | Push / SMS : rappel départ, confirmation paiement, crédit bas |

### 3.3 Entités / contrats API (indicatif)

```
POST   /api/public/auth/otp/request
POST   /api/public/auth/otp/verify
GET    /api/traveler/me
PATCH  /api/traveler/me
GET    /api/traveler/tickets
GET    /api/traveler/tickets/{id}
GET    /api/traveler/wallet
POST   /api/traveler/wallet/topup
GET    /api/traveler/wallet/ledger
GET    /api/traveler/passes          # multi-jours / forfaits
POST   /api/traveler/passes/purchase
POST   /api/traveler/preorders
POST   /api/traveler/preorders/{id}/convert
```

### 3.4 Règles métier clés

- Un billet appartient à un **titulaire** (peut différer du payeur — déjà partiellement vrai côté B2C)
- Le wallet est **multi-devise** si besoin (CDF par défaut) ; pas de découvert
- Précommande : expiration + libération de stock selon politique agence
- Multi-jours : consommation unitaire par trajet (scan QR / validation embarquement)

### 3.5 Questions pour le backend

- OTP : provider SMS déjà en place ou nouveau ?
- Wallet : ledger double-écriture interne ou intégration PSP wallet ?
- Les forfaits multi-jours sont-ils **par agence** ou **réseau MBIYO** ?

---

## 4. E2 — Bonus, réductions & offres surprises (fidélité)

### 4.1 Besoin

Dans la gestion agence, configurer des **avantages clients fidèles** selon des **paramètres par trajet** (corridor), pour automatiser remises et « surprises ».

### 4.2 Fonctionnalités

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E2.1 | Barème fidélité | Points ou compteur de trajets par voyageur / téléphone |
| E2.2 | Règles par corridor | Ex. Kolwezi→Lubumbashi : −10 % dès 5 trajets / mois |
| E2.3 | Codes promo | Codes manuels ou générés (usage unique / multiple) |
| E2.4 | Offres surprises | Remise aléatoire ou cadeau (siège gratuit, bagage) selon seuils |
| E2.5 | Bonus staff | Commission / bonus vendeur POS sur objectifs |
| E2.6 | Plafonds & exclusions | Max remise, jours exclus, sièges premium exclus |
| E2.7 | Audit | Qui a bénéficié de quoi, sur quel billet |

### 4.3 Modèle de règle (exemple)

```yaml
loyalty_rule:
  agency_id: ...
  corridor: { origin, destination }   # ou offer_id
  trigger:
    type: trip_count | spend | first_purchase | birthday
    window: rolling_30d | calendar_month | lifetime
    threshold: 5
  reward:
    type: percent_off | fixed_off | free_seat | surprise_pool
    value: 10
    surprise_pool_id: optional
  stackable: false
  active: true
```

### 4.4 APIs (indicatif)

```
GET/POST   /api/agency/loyalty/rules
PATCH      /api/agency/loyalty/rules/{id}
GET/POST   /api/agency/promotions
POST       /api/agency/promotions/validate   # quote-time
GET        /api/agency/loyalty/customers
GET        /api/public/agency/quote          # étendu : discount applied
```

### 4.5 Intégration vente

Au moment du **quote** / **hold** / **pay** :

1. Identifier le client (tél / compte)
2. Évaluer règles applicables au corridor
3. Appliquer la meilleure offre non cumulable (ou stack selon config)
4. Tracer `discountAmount`, `ruleId`, `promoCode` sur le billet

---

## 5. E3 — App / interface POS vendeurs + cash collect

### 5.1 Besoin

Interface **POS-like** pour :

- Vendeurs à bord (bus)
- Vendeurs cash au guichet / marché
- Processus de **collecte de cash** (remise caissier / superviseur)

### 5.2 Personas

| Rôle | Capacités |
|------|-----------|
| `SELLER_BUS` | Vente à bord, scan siège, encaissement cash / MM |
| `SELLER_CASH` | Vente hors guichet, stock tickets offline-capable |
| `CASHIER` | Réception cash collect, clôture caisse |
| `SUPERVISOR` | Validation remises, écarts, annulations |

### 5.3 Fonctionnalités

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E3.1 | Login vendeur | PIN / OTP device ; session liée à un `pointOfSale` |
| E3.2 | Vente rapide | Trajet → siège → montant → mode paiement → billet |
| E3.3 | Mode offline | File d’attente locale + sync (critique terrain RDC) |
| E3.4 | Impression / QR | Ticket thermique ou QR affiché |
| E3.5 | Annulation limitée | Fenêtre courte + motif + validation superviseur |
| E3.6 | Cash session | Ouverture / clôture de session vendeur |
| E3.7 | Cash collect | Remise d’espèces : montant déclaré → reçu → validation caissier |
| E3.8 | Écarts | Différence déclaré vs attendu ; justification |
| E3.9 | Objectifs / KPI | Nb billets, CA jour, taux MM vs cash |

### 5.4 Flux cash collect

```
1. Vendeur clôture sa session → total cash attendu calculé
2. Vendeur crée un CashHandover (montant déclaré + photo optionnelle)
3. Caissier / superviseur réceptionne → confirme ou rejette
4. Écriture comptable : CashIn (agence) + SoldOut vendeur
5. Si écart → incident + workflow résolution
```

### 5.5 APIs (indicatif)

```
POST /api/pos/sessions/open
POST /api/pos/sessions/{id}/close
POST /api/pos/sales
GET  /api/pos/sales
POST /api/pos/cash-handovers
POST /api/pos/cash-handovers/{id}/confirm
POST /api/pos/cash-handovers/{id}/reject
GET  /api/pos/sync/pull
POST /api/pos/sync/push
```

### 5.6 Contraintes techniques à anticiper

- **Idempotency-Key** sur ventes (sync offline)
- Horodatage serveur vs device
- Lier chaque vente à `transportId` / `embarkationId` / `sellerId`
- Sécurité device (révocation terminal)

---

## 6. E4 — Comptabilité transporteur (renforcement)

### 6.1 Besoin

Donner au transporteur une vision **claire et exploitable** de sa trésorerie et de sa rentabilité (guichet, POS, Mobile Money, Pass ONT, commissions).

### 6.2 Fonctionnalités

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E4.1 | Plan comptable simplifié | Comptes : ventes, cash, MM, commissions, Pass, écarts |
| E4.2 | Journal des écritures | Chaque billet / handover / remboursement génère des lignes |
| E4.3 | Clôture journalière | Z-report par point de vente / agence |
| E4.4 | Rapprochement PSP | Matching FlexPay / MM vs billets |
| E4.5 | Commissions ONT / MBIYO | Ventilation ticket vs Pass vs frais plateforme |
| E4.6 | Exports | CSV / Excel / PDF pour expert-comptable |
| E4.7 | Multi-agence / multi-dépôt | Consolidation groupe transporteur |
| E4.8 | Alertes | Écart caisse, taux d’annulation anormal, impayés |

### 6.3 Rapports minimum

1. CA jour / semaine / mois (par corridor, par vendeur, par canal)
2. Mix paiement (Cash / MM / Carte / Wallet)
3. Impayés & remboursements
4. Marge après Pass ONT + commission plateforme
5. Performance POS vs guichet vs online

### 6.4 APIs (indicatif)

```
GET /api/agency/accounting/ledger
GET /api/agency/accounting/daily-close
POST /api/agency/accounting/daily-close
GET /api/agency/accounting/reports/sales
GET /api/agency/accounting/reports/reconciliation
GET /api/agency/accounting/exports/{type}
```

---

## 7. E5 — Flotte avancée (chauffeurs, maintenance, rapports)

> Base existante : `/agency/fleet`, drivers, map, rentals. À enrichir.

### 7.1 Fonctionnalités chauffeurs

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E5.1 | App / espace chauffeur | Mission du jour, passagers, check-list départ |
| E5.2 | Affectation | Chauffeur ↔ véhicule ↔ trajet ↔ date |
| E5.3 | Check-in départ | Kilométrage, carburant, état véhicule |
| E5.4 | Incidents | Panne, accident, retard (géoloc + photo) |
| E5.5 | Documents RH | Permis, validité, formations, sanctions |

### 7.2 Maintenance

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E5.6 | Planning maintenance | Préventive (km / jours) + curative |
| E5.7 | Ordres de travail | Atelier interne / prestataire |
| E5.8 | Coûts | Pièces, main-d’œuvre, immobilisation |
| E5.9 | Immobilisation | Véhicule indisponible → impact offres / capacités |

### 7.3 Carburant & coûts d’exploitation

| ID | Fonctionnalité | Description |
|----|----------------|-------------|
| E5.10 | Plein / conso | Saisie litres + coût ; conso L/100 km |
| E5.11 | Coût au km | Agrégation maintenance + fuel + péages |
| E5.12 | Alertes | Vidange due, assurance expirée, contrôle technique |

### 7.4 Rapports flotte

- Disponibilité flotte (%)
- Ponctualité / retards
- Coût par véhicule / corridor
- Taux d’occupation sièges vs capacité
- Classement chauffeurs (sécurité, retards, incidents)

### 7.5 APIs (indicatif)

```
GET/POST /api/agency/fleet/assignments
POST     /api/agency/fleet/departures/checklist
POST     /api/agency/fleet/incidents
GET/POST /api/agency/fleet/maintenance/work-orders
GET/POST /api/agency/fleet/fuel-logs
GET      /api/agency/fleet/reports/*
```

---

## 8. E6 — Modules additionnels recommandés

Issues de l’expérience **urbain, inter-commune, inter-urbain, inter-province**.

### 8.1 Exploitation & réseau

| Module | Pourquoi |
|--------|----------|
| **Horaires & grilles tarifaires versionnées** | Changements saisonniers (fêtes, pluies) sans casser l’historique |
| **Correspondances** | Billetterie multi-segments (commune → gare → province) |
| **Overbooking contrôlé** | Liste d’attente + upgrade automatique |
| **Manifeste & no-show** | Libération sièges T−X minutes |
| **Bagages** | Franchise kg + surplus tarifé (critique inter-province) |
| **Colis / fret léger** | Même bus, tracking simple (très demandé en RDC) |

### 8.2 Sécurité & conformité

| Module | Pourquoi |
|--------|----------|
| **Liste noire / fraude** | Tél / pièces déjà utilisés en fraude |
| **Limitation âge / mineurs** | Accompagnement obligatoire |
| **Assurance voyageur** | Option à l’achat + attestation |
| **Contrôle route** | QR agent + historique (déjà partiel ONT) |
| **RGPD / consentement SMS** | Opt-in marketing vs transactionnel |

### 8.3 Expérience client

| Module | Pourquoi |
|--------|----------|
| **Suivi bus en direct** (opt-in) | ETA pour familles qui attendent |
| **Remboursement / report** | Politique claire (maladie, panne, force majeure) |
| **Notation trajet** | Qualité service → score chauffeur / véhicule |
| **Chat / WhatsApp support** | Canal dominant terrain |
| **Multilingue** | FR + Swahili / Lingala (Lualaba / RDC) |

### 8.4 Distribution

| Module | Pourquoi |
|--------|----------|
| **Réseau de revendeurs** | Sous-agents commissionnés (kiosques) |
| **API B2B** | Hôtels / mines / entreprises réservent au volume |
| **QR dynamique** | Anti-copie (rotation courte validité) |

### 8.5 Analytics & pilotage

| Module | Pourquoi |
|--------|----------|
| **Heatmap corridors** | Demande vs offre |
| **Prévision remplissage** | Aide à ouvrir un bus supplémentaire |
| **Dashboard DG** | CA, ponctualité, NPS, cash risk |

---

## 9. Impacts transverses (tous les épiques)

À prévoir côté backend dès le design :

| Sujet | Attendu |
|-------|---------|
| **RBAC** | Nouveaux rôles : `TRAVELER`, `SELLER_*`, `DRIVER`, `ACCOUNTANT`, `FLEET_MANAGER` |
| **Multi-tenant** | Isolation stricte `agencyId` |
| **Audit log** | Qui a vendu / remisé / collecté / clôturé |
| **Idempotence** | Paiements, sync POS, handovers |
| **Webhooks** | Paiement confirmé, billet émis, handover validé |
| **Observabilité** | Métriques ventes, erreurs sync, latence OTP |
| **Offline-first POS** | Contrat sync documenté (conflits, horloge) |

---

## 10. Priorisation proposée (MVP → suite)

### Vague 1 — Fondations client & terrain
1. E1.1–E1.4 Espace membre + historique billets  
2. E3.1–E3.7 POS vente + cash collect (online d’abord)  
3. E2.2–E2.3 Remises corridor + codes promo (simples)

### Vague 2 — Fidélité & argent
4. E1.5–E1.7 Wallet + multi-jours + précommandes  
5. E2.1 / E2.4 Fidélité points + surprises  
6. E4.1–E4.4 Comptabilité journal + clôture + rapprochement

### Vague 3 — Flotte & excellence ops
7. E5 Maintenance + check-list chauffeur + fuel  
8. E3.3 Offline POS  
9. E6 sélectif : bagages, colis, manifeste no-show, exports comptables

---

## 11. Livrables attendus de l’équipe backend

Pour chaque épique retenue :

1. **Spec OpenAPI** (ou pages ApiPlatform)  
2. **Modèle de données** (entités + relations)  
3. **Règles métier** écrites (edge cases)  
4. **Seeds / fixtures** de démo  
5. **Estimation** (jours/homme) + dépendances  
6. **Critères d’acceptation** testables par le front

---

## 12. Questions ouvertes (atelier produit / backend)

1. Le wallet voyageur est-il **central MBIYO** ou **par agence** ?  
2. Les vendeurs POS sont-ils employés agence ou **agents indépendants** commissionnés ?  
3. Faut-il un **mode offline** dès la Vague 1 ou Vague 3 ?  
4. La fidélité est-elle **inter-agences** (marque MBIYO) ou silotée par transporteur ?  
5. Qui porte le risque cash (agence vs vendeur) en cas d’écart non justifié ?  
6. Les chauffeurs ont-ils besoin d’une **app native** ou d’une PWA suffit ?  
7. Export comptable : format cible (SYSCOHADA / Excel simple) ?

---

## 13. Annexes — glossaire

| Terme | Définition |
|-------|------------|
| **Wallet** | Solde prépayé voyageur |
| **Précommande** | Intention d’achat / réservation avant conversion en billet daté |
| **POS** | Point of Sale — interface vendeur terrain |
| **Cash collect / handover** | Remise d’espèces vendeur → caisse |
| **Corridor** | Couple origine–destination |
| **Clôture Z** | Arrêté de caisse journalier |
| **No-show** | Passager non présenté à l’embarquement |

---

*Document rédigé pour cadrage backend MBIYO Lualaba Mobilité — à amender après atelier de priorisation.*
