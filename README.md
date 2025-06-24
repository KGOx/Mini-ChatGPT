📋 Description
Mini-ChatGPT est une application web de chat conversationnel moderne inspirée de ChatGPT, développée dans le cadre d'un projet académique. Elle offre une expérience utilisateur fluide pour interagir avec différents modèles d'intelligence artificielle en temps réel, avec des fonctionnalités avancées comme le streaming des réponses et la personnalisation complète du comportement de l'IA.

✨ Fonctionnalités principales
🤖 Sélecteur de modèles d'IA : Support multi-fournisseurs (GPT-3.5, GPT-4, Gemini, Claude, etc.)

💬 Streaming temps réel : Réponses affichées progressivement via Server-Sent Events (SSE)

📚 Historique intelligent : Sauvegarde automatique avec génération de titres contextuels

⚙️ Instructions personnalisées : Customisation complète du comportement et du style de l'IA

📱 Interface responsive : Design adaptatif pour desktop et mobile

🧩 Architecture modulaire : Composants Vue.js réutilisables et maintenables

🧪 Tests complets : Couverture unitaire et fonctionnelle avec PHPUnit

🛠️ Stack technique
Backend
Laravel 11 - Framework PHP moderne

Laravel Jetstream - Authentification et gestion utilisateurs

SQLite - Base de données légère pour développement

OpenAI API - Intégration modèles d'IA

Frontend
Vue.js 3 avec Composition API - Framework JavaScript réactif

Inertia.js - Liaison seamless Laravel/Vue.js (SPA)

TailwindCSS 3 - Framework CSS utilitaire

Vite - Build tool moderne et performant

Outils
PHPUnit - Tests automatisés

Factories - Génération de données de test

Git/GitHub - Gestion de versions

🚀 Installation
Prérequis
PHP 8.2+

Composer

Node.js 18+

SQLite

Étapes d'installation
Cloner le projet

bash
git clone <url-du-repo>
cd mini-chatgpt
Installer les dépendances PHP

bash
composer install
Installer les dépendances JavaScript

bash
npm install
Configuration de l'environnement

bash
cp .env.example .env
php artisan key:generate
Configurer la base de données

bash
touch database/database.sqlite
Exécuter les migrations

bash
php artisan migrate
Configuration API (optionnel)

bash

# Ajouter votre clé OpenAI dans .env

OPENAI_API_KEY=your_api_key_here
Lancer l'application

bash

# Terminal 1 - Serveur Laravel

php artisan serve

# Terminal 2 - Build frontend

npm run dev
Accéder à l'application

URL : http://localhost:8000

Page principale : http://localhost:8000/ask

📖 Utilisation
Première utilisation
Créer un compte via l'interface d'inscription Jetstream

Se connecter et accéder à la page de chat

Sélectionner un modèle d'IA via le dropdown en haut à droite

Commencer une conversation en tapant votre message

Fonctionnalités avancées
Toggle streaming : Basculer entre mode streaming et classique

Instructions personnalisées : Cliquer sur l'icône paramètres pour customiser l'IA

Gestion conversations : Créer, sélectionner, supprimer via la sidebar

Historique intelligent : Les titres se génèrent automatiquement

🏗️ Architecture
Structure Backend
text
app/
├── Http/Controllers/
│ ├── AskController.php # Chat simple one-shot
│ ├── ConversationController.php # CRUD conversations
│ ├── MessageController.php # Messages + streaming SSE
│ └── ProfileController.php # Instructions personnalisées
├── Models/
│ ├── User.php # Utilisateurs + custom fields
│ ├── Conversation.php # Conversations avec relations
│ └── Message.php # Messages user/assistant
└── Services/
└── ChatService.php # Interface unifiée APIs IA
Composants Frontend
text
resources/js/Components/ComponentsAsk/
├── ChatSidebar.vue # Sidebar conversations
├── ChatHeader.vue # Header avec sélecteurs
├── MessagesList.vue # Affichage messages + Markdown
├── MessageInput.vue # Zone saisie
├── CustomInstructionsModal.vue # Modal instructions
├── ConversationItem.vue # Item sidebar
└── StreamingToggle.vue # Toggle streaming
Base de données
sql
users
├── id (PK)
├── custom_instructions
├── custom_response_style
├── enable_custom_instructions
└── custom_commands

conversations messages
├── id (PK) ├── id (PK)
├── user_id (FK) ├── conversation_id (FK)
├── title ├── user_id (FK, nullable)
└── model ├── role (user/assistant)
└── content
🧪 Tests
Lancer les tests
bash

# Tous les tests

php artisan test

# Tests spécifiques

php artisan test tests/Unit/Models/
php artisan test tests/Feature/

# Avec couverture

php artisan test --coverage
Coverage actuelle
23 tests au total (100% de réussite)

Tests unitaires : Models (Conversation, Message, User)

Tests fonctionnels : Controllers (Conversation, Profile)

Factories : Génération de données cohérentes

🔧 Fonctionnalités techniques
Streaming SSE
Implementation native des Server-Sent Events pour l'affichage temps réel :

Configuration serveur optimisée (ob_flush(), headers appropriés)

Gestion client avec ReadableStream natif

Latence optimisée (100ms) pour fluidité

Architecture modulaire
Composition API Vue.js 3 pour organisation logique

Props/Events pour communication composants

Inertia pour expérience SPA sans complexité

Sécurité et autorisations
Authentification Jetstream complète

Autorisation par utilisateur sur toutes les routes

Validation des données et protection CSRF

🚧 Limitations connues et améliorations futures
Fonctionnalités manquantes
Tests E2E avec Laravel Dusk

Outils LLM intégrés (calculatrice, recherche web)

Upload et analyse d'images

Génération d'images (DALL-E, Stable Diffusion)

Input/Output vocal

Gestion avancée du contexte (résumés automatiques)

Améliorations UX
Thèmes personnalisables (mode sombre, steampunk)

Workspace configurable

Raccourcis clavier

Export conversations (Markdown, PDF)

Mode collaboratif

🤖 Utilisation des outils IA
Ce projet a bénéficié de l'assistance d'outils IA modernes :

Perplexity AI (Claude Sonnet) : Recherche technique et architecture

ChatGPT o3 : Génération de code et documentation

V0 : Validation et vérification

🐛 Problèmes connus
Environnement de développement
SQLite : Commandes MySQL incompatibles (utiliser PRAGMA à la place)

Streaming : Nécessite configuration serveur appropriée pour production

Solutions documentées
Buffering SSE : Headers X-Accel-Buffering: no requis

Variables closures : Utiliser use() pour scope PHP

Types Boolean : Conversion explicite DB → Frontend

📄 Licence
Ce projet est sous licence MIT - voir le fichier LICENSE pour plus de détails.

👨‍💻 Développement
Structure de développement
bash

# Mode développement avec hot-reload

npm run dev

# Build production

npm run build

# Linter PHP

./vendor/bin/phpstan analyse

# Formatting

./vendor/bin/php-cs-fixer fix
Contribution
Les contributions sont les bienvenues ! Merci de :

Fork le projet

Créer une branche feature

Commit vos changements

Ouvrir une Pull Request

Mini-ChatGPT - Une expérience de chat IA moderne et personnalisable
