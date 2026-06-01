# 🚀 Intervention Manager SaaS

Solution SaaS de gestion d'interventions, tickets et maintenance destinée aux entreprises de services techniques.

L'application permet de :

* centraliser les demandes d'intervention
* suivre l'avancement des tickets
* gérer les techniciens et les rôles
* visualiser l'activité via un tableau de bord
* conserver l'historique complet des interventions

### 🎯 Objectif

Fournir une plateforme moderne et sécurisée permettant de remplacer les échanges par email et les fichiers Excel par un workflow structuré et traçable.

### 🚧 Statut du projet

Projet actuellement en développement actif.

Les fonctionnalités principales sont en cours d'implémentation et le périmètre évolue progressivement.


---

# 📸 Captures d'écran

## Dashboard

![Dashboard](screenshots/dashboard.png)

## Gestion des tickets

![Tickets](screenshots/tickets.png)

## Détail intervention

![Intervention](screenshots/intervention.png)

# 🌐 Démonstration

Projet actuellement en développement.
La démonstration publique sera disponible prochainement.

## Fonctionnalités principales

* 🔐 Authentification JWT sécurisée
* 👥 Gestion des utilisateurs et rôles
* 🎫 Création et suivi d’interventions
* 📊 Dashboard dynamique
* 📝 Historique et workflow des tickets
* 📎 Upload de documents
* 🌍 API REST Symfony
* ⚡ Frontend Angular responsive
* 🐳 Docker & CI/CD
* 🔎 Analyse qualité avec SonarQube et Semgrep

---

# 🛠️ Stack Technique

## Backend

* PHP 8.2
* Symfony 7
* API REST
* Doctrine ORM
* PostgreSQL
* JWT Authentication

## Frontend

* Angular 21
* TailwindCSS
* TypeScript
* Angular Signals

## DevOps

* Docker
* Docker Compose
* GitHub / Gitea
* CI/CD
* SonarQube
* Semgrep

---

# 🏗️ Architecture

```txt
Frontend Angular
        ↓
API Symfony REST
        ↓
PostgreSQL
```

---

# 📂 Structure du projet

```txt
intervention-manager/
│
├── backend/
│   ├── src/
│   ├── config/
│   ├── migrations/
│   └── tests/
│
├── frontend/
│   ├── src/
│   ├── components/
│   ├── services/
│   └── assets/
│
├── docker/
├── .github/
├── docker-compose.yml
└── README.md
```

---

# ⚙️ Installation

## 1. Cloner le repository

```bash
git clone https://github.com/dpieton-dev/intervention-manager.git
```

```bash
cd intervention-manager
```

---

# 🐳 Lancer avec Docker

```bash
docker compose up -d
```

---

# 🔧 Backend Symfony

## Installation

```bash
cd backend
composer install
```

## Variables d’environnement

Créer le fichier :

```txt
.env.local
```

Exemple :

```env
DATABASE_URL="postgresql://app:password@database:5432/intervention_manager"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
```

## Migrations

```bash
php bin/console doctrine:migrations:migrate
```

## Lancer Symfony

```bash
symfony serve
```

---

# 🎨 Frontend Angular

## Installation

```bash
cd frontend
npm install
```

## Lancer Angular

```bash
ng serve
```

Application disponible sur :

```txt
http://localhost:4200
```

---

# 🔐 Authentification

Le projet utilise JWT Authentication.

Endpoints principaux :

```txt
POST /api/login
GET /api/tickets
POST /api/tickets
PUT /api/tickets/{id}
DELETE /api/tickets/{id}
```

---

# 📊 Fonctionnalités prévues

* ✅ Dashboard statistiques
* ✅ Gestion des rôles
* ✅ Workflow des tickets
* ✅ Notifications
* ✅ Upload de documents
* 🔄 Temps réel WebSocket
* 🔄 Génération PDF
* 🔄 Logs & audit

---

# 🧪 Qualité & Sécurité

## Analyse qualité

* SonarQube
* PHPStan
* ESLint

## Sécurité

* Semgrep
* JWT
* Validation des données
* Gestion des rôles

---

# 🚀 CI/CD

Pipeline automatisée :

1. Push GitHub/Gitea
2. Analyse SonarQube
3. Analyse Semgrep
4. Build Docker
5. Déploiement environnement de test

---

# 🌍 Auteur

## Dominique Pieton

💻 GitHub : [https://github.com/dpieton-dev](https://github.com/dpieton-dev)

🌐 Portfolio : [https://portfolio-dominique.vercel.app/](https://portfolio-dominique.vercel.app/)

---

# 📄 Licence

Projet personnel à but démonstratif et professionnel.
