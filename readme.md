# Web Security 09/2026


## Entités - Dictionnaire de données


### Vue d'ensemble des entités


| Entité     | Rôle fonctionnel                                                              |
|------------|-------------------------------------------------------------------------------|
| `user`     | Représente un utilisateur de la plateforme                                    |
| `category` | Thématique des topics avec système de hiérarchie                              |
| `topic`    | Publication principale créée par un utilisateur (équivalent d'un post Reddit) |
| `comment`  | Réponses à un topic, avec possibilité de répondre à un autre commentaire      |
| `favorite` | Mémorise les topics mis en favoris par un utilisateur                         |
| `vote`     | Enregistre le vote (positif ou négatif) d'un utilisateur sur un commentaire   |


### Table `user`


| Colonne          | Type              | Contrainte         | Description                                                                                  |
|------------------|-------------------|--------------------|----------------------------------------------------------------------------------------------|
| `id`             | `INT`             | PK, AUTO_INCREMENT | Identifiant unique de l'utilisateur                                                          |
| `email`          | `VARCHAR(180)`    | NOT NULL, UNIQUE   | Adresse e-mail de connexion, doit être unique                                                |
| `roles`          | `LONGTEXT` (JSON) | NOT NULL           | Tableau JSON des rôles (ex: `["ROLE_USER"]`, `["ROLE_ADMIN"]`) — contrôle les droits d'accès |
| `password`       | `VARCHAR(255)`    | NOT NULL           | Mot de passe hashé (ne jamais stocker en clair)                                              |
| `nickname`       | `VARCHAR(164)`    | NOT NULL           | Pseudo affiché publiquement sur la plateforme                                                |
| `activationCode` | `VARCHAR(255)`    | NULL autorisé      | Code d'activation du compde de l'utilisateur                                                 |
| `picture`        | `VARCHAR(255)`    | NULL autorisé      | Chemin ou URL vers la photo de profil (optionnel)                                            |
| `birth_at`       | `DATETIME`        | NULL autorisé      | Date de naissance de l'utilisateur (optionnel)                                               |


### Table `category`


| Colonne     | Type           | Contrainte                        | Description                                                                               |
|-------------|----------------|-----------------------------------|-------------------------------------------------------------------------------------------|
| `id`        | `INT`          | PK, AUTO_INCREMENT                | Identifiant unique de la catégorie                                                        |
| `name`      | `VARCHAR(164)` | NOT NULL                          | Nom affiché de la catégorie                                                               |
| `parent_id` | `INT`          | FK → `category.id`, NULL autorisé | Référence vers la catégorie parente (auto-jointure). `NULL` si c'est une catégorie racine |


### Table `topic`


| Colonne       | Type           | Contrainte                   | Description                                                       |
|---------------|----------------|------------------------------|-------------------------------------------------------------------|
| `id`          | `INT`          | PK, AUTO_INCREMENT           | Identifiant unique du topic                                       |
| `title`       | `VARCHAR(180)` | NOT NULL                     | Titre du topic, affiché dans les listes                           |
| `content`     | `LONGTEXT`     | NOT NULL                     | Corps du texte du topic                                           |
| `created_at`  | `DATETIME`     | NOT NULL                     | Date et heure de création                                         |
| `updated_at`  | `DATETIME`     | NULL autorisé                | Date et heure de dernière modification (`NULL` si jamais modifié) |
| `author_id`   | `INT`          | FK → `user.id`, NOT NULL     | Identifiant de l'utilisateur qui a créé le topic                  |
| `category_id` | `INT`          | FK → `category.id`, NOT NULL | Catégorie à laquelle appartient le topic                          |
| `picture`     | `VARCHAR(255)` | NULL autorisé                | Image optionnelle associée au topic (chemin ou URL)               |


### Table `comment`


| Colonne      | Type       | Contrainte                       | Description                                                                                      |
|--------------|------------|----------------------------------|--------------------------------------------------------------------------------------------------|
| `id`         | `INT`      | PK, AUTO_INCREMENT               | Identifiant unique du commentaire                                                                |
| `content`    | `LONGTEXT` | NOT NULL                         | Contenu textuel du commentaire                                                                   |
| `created_at` | `DATETIME` | NOT NULL                         | Date et heure de création                                                                        |
| `updated_at` | `DATETIME` | NULL autorisé                    | Date et heure de dernière modification (`NULL` si jamais modifié)                                |
| `author_id`  | `INT`      | FK → `user.id`, NOT NULL         | Utilisateur qui a posté le commentaire                                                           |
| `topic_id`   | `INT`      | FK → `topic.id`, NOT NULL        | Topic auquel appartient le commentaire                                                           |
| `parent_id`  | `INT`      | FK → `comment.id`, NULL autorisé | Commentaire parent en cas de réponse imbriquée. `NULL` si c'est un commentaire de premier niveau |


### Table `favorite`


| Colonne      | Type       | Contrainte                | Description                                     |
|--------------|------------|---------------------------|-------------------------------------------------|
| `id`         | `INT`      | PK, AUTO_INCREMENT        | Identifiant unique du favori                    |
| `created_at` | `DATETIME` | NOT NULL                  | Date et heure à laquelle le favori a été ajouté |
| `topic_id`   | `INT`      | FK → `topic.id`, NOT NULL | Topic mis en favori                             |
| `user_id`    | `INT`      | FK → `user.id`, NOT NULL  | Utilisateur ayant mis ce topic en favori        |

> Un même utilisateur ne devrait pas pouvoir mettre le même topic en favori deux fois (contrainte à gérer côté applicatif ou par un index UNIQUE composite sur `(user_id, topic_id)`).


### Table `vote`


| Colonne      | Type       | Contrainte                  | Description                                      |
|--------------|------------|-----------------------------|--------------------------------------------------|
| `id`         | `INT`      | PK, AUTO_INCREMENT          | Identifiant unique du vote                       |
| `value`      | `SMALLINT` | NOT NULL                    | Valeur du vote : `1` (upvote) ou `-1` (downvote) |
| `created_at` | `DATETIME` | NOT NULL                    | Date et heure du vote                            |
| `author_id`  | `INT`      | FK → `user.id`, NOT NULL    | Utilisateur ayant voté                           |
| `comment_id` | `INT`      | FK → `comment.id`, NOT NULL | Commentaire concerné par le vote                 |

> Un utilisateur ne devrait voter qu'une seule fois par commentaire (contrainte à gérer applicativement ou via un index UNIQUE composite sur `(author_id, comment_id)`).


## Lancement du projet


Tout le projet est dockerisé, la stack technique est la suivante :
- Symfony 7.4
- PHP 8.2
- Node 22
- MariaDB
- Bootstrap, Vue.js sont déjà installés

Un `Makefile` existe pour simplifier le lancement du projet :
- `make init` : construit les conteneurs Docker, créer et alimente la base de données
- `make db-reset` : si vous devez réinitialiser votre BDD rapidement
- `make db` : créer et alimente la base de données (utilisé via le init)
- `make up` : créer les conteneurs Docker
- `make up-build` : créer et construit les conteneurs Docker (utilisé via le init)
- `make down` : coupe les conteneurs Docker
- `make php` : entre le conteneur PHP (pour lancer les commandes Symfony)
- `make node` : entre le conteneur NODE (pour lancer le `npm run watch`)
- `make l-node` : affiche les logs du conteneur node


Vérifiez votre "id" de user : commande `id` puis modifiez les lignes 41, 42, 58 et 59 du Dockerfile, si nécessaire (1000 ou 1001)

Une fois le projet cloné, `make init` devrait suffir pour lancer le projet.


Vous pouvez accéder au PHPMyAdmin via l'URL : http://localhost:8080/index.php?route=/database/structure&db=fakeddit

Le projet intègre directement un server Caddy, une fois le conteneur Docker lancé, vous pouvez accéder à l'application via l'URL : https://localhost:8443/

Les e-mails envoyés par l'application (par exemple lors d'une inscription) sont interceptés par Mailpit, consultable via l'URL : http://localhost:8025/

Les mots de passe de tous les utilisateurs sont : `123`
