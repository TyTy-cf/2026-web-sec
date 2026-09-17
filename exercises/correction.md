# Correction — usage interne, ne pas commit

<a id="sommaire"></a>
## Sommaire

- [Exercice 1 — Security Misconfiguration (cookie de session)](#exercice-1)
- [Exercice 2 — Persistent XSS](#exercice-2)
- [Exercice 3 — Reflected XSS](#exercice-3)
- [Exercice 4 — DOM-based XSS](#exercice-4)
- [Exercice 5 — Broken Access Control](#exercice-5)
- [Exercice 6 — Content Security Policy](#exercice-6)
- [Exercice 7 — Cross-Site Request Forgery (CSRF)](#exercice-7)
- [Exercice 8 — Integrity of JWT](#exercice-8)
- [Exercice 9 — Login Throttling](#exercice-9)

---

<a id="exercice-1"></a>
## Exercice 1 — Security Misconfiguration (cookie de session)

**Livrable attendu** (cf. la section "Rendu" globale en tête du `readme.md`) : le correctif de `config/packages/framework.yaml` sur la branche du stagiaire (question 4), et la réponse à la question 5 (catégorisation OWASP) dans `exercises/reponses.md`. Les questions 1 à 3 sont de l'observation/diagnostic (DevTools, doc Symfony) : rien à en attendre dans `reponses.md`, seulement vérifier à l'oral ou en live que le stagiaire a bien compris l'écart avant de le corriger.

**État vulnérable** (déjà en place, à connaître pour pouvoir le reproduire) :
```yaml
framework:
    secret: '%env(APP_SECRET)%'
    session:
        cookie_secure: false
        cookie_httponly: true
        cookie_samesite: null

    #esi: true
    #fragments: true
```

**Ce que Symfony fait par défaut** (dès `session: true`, sans rien configurer d'autre) : `cookie_secure: auto` (donc `true` puisque le site tourne en HTTPS), `cookie_httponly: true`, `cookie_samesite: 'lax'`.

**Fix** — revenir à la configuration par défaut, en supprimant simplement le bloc `session` explicite :
```diff
 framework:
     secret: '%env(APP_SECRET)%'
-    session:
-        cookie_secure: false
-        cookie_httponly: true
-        cookie_samesite: null
+    session: true

     #esi: true
     #fragments: true
```
Une configuration explicite équivalente (`cookie_secure: auto` (ou `true`), `cookie_httponly: true`, `cookie_samesite: 'lax'`) est tout aussi acceptable ; l'essentiel est que le stagiaire comprenne que les valeurs par défaut de Symfony sont déjà correctes et qu'il ne faut pas les redéfinir sans raison.

**Ce qu'il faut faire ressortir avec le stagiaire** :
- `cookie_secure: false` : le cookie de session peut être envoyé en clair sur une connexion non chiffrée. En local, tout passe par HTTPS (`Caddyfile` ne sert que `https://localhost:8443`), donc l'impact n'est pas démontrable tel quel sur ce projet — mais c'est bien ce que l'attribut `Secure` empêche en production (interception du cookie sur un réseau non maîtrisé, downgrade HTTP)
- `cookie_samesite: null` : retire l'attribut `SameSite` du cookie. C'est une des protections de base du navigateur contre le CSRF (un cookie `Lax`/`Strict` n'est pas envoyé, ou envoyé de façon restreinte, sur une requête déclenchée depuis un autre site) ; le retirer réactive ce vecteur pour toutes les routes de l'application, y compris celles qui n'ont pas de jeton CSRF (cf. la démo `app_comment_delete`)
- `cookie_httponly: true` est volontairement laissé intact : c'est ce qui explique pourquoi `document.cookie` ne renvoie rien lors de la démonstration de l'exercice 2 (XSS persistant) — ce n'est pas cassé ici, il ne faut pas que le stagiaire le "corrige" ou le commente par erreur
- Catégorie OWASP Top 10 : **A05:2021 – Security Misconfiguration** (des protections existent et sont actives par défaut dans le framework, mais ont été désactivées explicitement)

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-2"></a>
## Exercice 2 — Persistent XSS

**Code à recevoir** : un commentaire contenant un `<script>`, puis une version qui exécute une action (ex. `fetch()` qui soumet un commentaire au nom de la victime).

   ```html
   <script>
   
    window.addEventListener('load', () => {
        const inputToken = document.querySelector('[name="comment[_token]"]').value;
        if (inputToken) {
            fetch(window.location.href, {
           method: 'POST',
           headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
           body: new URLSearchParams({
             'comment[content]': 'posted automatically by XSS',
             'comment[submit]': '',
             'comment[_token]': inputToken,
           }),
         });
        }
   });
   </script>
   ```
On récupère le jeton CSRF directement depuis la page sur laquelle elle s'exécute. Étant donné qu'elle s'exécute *en tant que* navigateur d'un visiteur authentifié, elle a accès à tout ce que contient le DOM de ce visiteur, y compris le jeton. Notez que vous n'avez eu besoin de lire aucun cookie pour faire cela : le navigateur joint automatiquement la session de la victime. Essayez `document.cookie` dans la console sur cette page : le cookie de session est configuré en `HttpOnly` et n'apparaîtra pas. **Il s'agit d'une réelle mesure d'atténuation, mais elle n'empêche pas l'attaque ci-dessus** ; elle bloque seulement une méthode spécifique d'abus de session (l'exfiltration de cookies), pas les autres (agir directement en tant que la victime)



**Fix** — `templates/front/topic/show.html.twig` ligne 37 :
```diff
- <div>{{ comment.content|raw }}</div>
+ <div>{{ comment.content }}</div>
```
Laisser l'autoescape Twig faire son travail (retirer `|raw`).

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-3"></a>
## Exercice 3 — Reflected XSS

**Code à recevoir** : une URL du type `?q=x onfocus=alert(1) autofocus=x` (ou équivalent), avec la popup qui se déclenche sans clic.

**Fix** — `templates/front/common/_header.html.twig`, attribut `value` du champ de recherche :
```diff
- <input class="form-control me-2" type="search" name="q" value={{ app.request.query.get('q') }} placeholder="Search topics..." aria-label="Search">
+ <input class="form-control me-2" type="search" name="q" value="{{ app.request.query.get('q') }}" placeholder="Search topics..." aria-label="Search">
```
Il suffit d'ajouter les guillemets autour de la valeur ; l'autoescape Twig fait déjà le reste.

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-4"></a>
## Exercice 4 — DOM-based XSS

**Code à recevoir** : une URL du type `/categorie/1#ref=<img src=x onerror=alert(document.domain)>` (ou tout autre gadget HTML avec handler d'évènement — `<script>` ne fonctionne pas via `innerHTML`, c'est un point de vérification attendu).

**Fix** — `assets/scripts/app.ts` :
```diff
      if (ref) {
-         banner.innerHTML = `<strong>${ref}</strong> pense que cette catégorie va te plaire !`;
+         const strong = document.createElement('strong');
+         strong.textContent = ref;
+
+         banner.append(strong, ' pense que cette catégorie va te plaire !');
      }
```
On construit le DOM avec `createElement`/`textContent`/`append` au lieu de `innerHTML` : `ref` est alors toujours traité comme du texte, jamais parsé comme du HTML, quel que soit son contenu.

Point à vérifier avec le stagiaire : le correctif est **uniquement côté client** (rebuild du JS/TS), rien à changer côté Symfony/Twig — c'est la caractéristique du DOM-based XSS, la faille n'existe jamais côté serveur.

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-5"></a>
## Exercice 5 — Broken Access Control

**Code à recevoir** : pas de payload, juste la démonstration (URL de `/sujets/{id}/modifier` avec l'ID d'un sujet dont il n'est pas l'auteur, formulaire soumis avec succès).

**Fix attendu : un Voter**, pas un simple `if` dans le contrôleur.

Nouveau fichier `src/Security/Voter/TopicVoter.php` :
```php
<?php

namespace App\Security\Voter;

use App\Entity\Topic;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TopicVoter extends Voter
{
    public const EDIT = 'TOPIC_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EDIT && $subject instanceof Topic;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Topic $subject */
        return $subject->getAuthor() === $user;
    }
}
```

`src/Controller/TopicController.php`, méthode `edit()` :
```diff
  public function edit(
      Topic $topic,
      Request $request,
      EntityManagerInterface $entityManager,
  ): Response {
+     $this->denyAccessUnlessGranted(TopicVoter::EDIT, $topic);
+
      $form = $this->createForm(TopicType::class, $topic);
```
(+ `use App\Security\Voter\TopicVoter;` en haut du fichier)

`denyAccessUnlessGranted` lève une `AccessDeniedException` aussi bien pour un non-auteur que pour un visiteur anonyme (le Voter renvoie `false` si `$token->getUser()` n'est pas un `User`) ; le firewall `form_login` redirige automatiquement vers la page de login dans ce dernier cas. Avantage par rapport au `if` inline : la règle d'autorisation est centralisée, testable isolément, et réutilisable partout où on a besoin de vérifier "cet utilisateur peut-il éditer ce sujet ?" (autre contrôleur, template avec `is_granted()`, etc.).

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-6"></a>
## Exercice 6 — Content Security Policy

**Code à recevoir** : pas de payload. Le livrable est une politique CSP fonctionnelle (preuve : en-tête `Content-Security-Policy` visible dans les réponses HTTP), plus la démonstration que les payloads des exercices 2, 3 et 4 sont neutralisés sans avoir touché au code vulnérable.

**Point de départ attendu** : le stagiaire doit remarquer que `security.yaml` ne gère que l'authentification/autorisation (firewalls, providers, access_control), et chercher ailleurs. Deux réponses valables, au choix :

**Option A — NelmioSecurityBundle** (la plus idiomatique côté Symfony) :
```
composer require nelmio/security-bundle
```
```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    csp:
        enforce:
            default-src: ["'self'"]
            script-src: ["'self'"]
            style-src: ["'self'"]
            img-src: ["'self'", 'data:']
```

**Option B — en-tête posé par Caddy** (aucun code applicatif) :
```
# Caddyfile
https://localhost:8443 {
    tls internal
    root * /var/www/html/public
    header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:"
    file_server
    php_fastcgi php:9000
}
```

Une troisième réponse (un `EventSubscriber` sur `kernel.response` qui ajoute l'en-tête à la main) est acceptable mais plus lourde qu'utile ici — à mentionner si le stagiaire part dans cette direction, sans le pénaliser.

**Vérification à faire avec le stagiaire** :
- Sans `'unsafe-inline'` dans `script-src`, les balises `<script>` injectées (exercice 2) et les gestionnaires d'évènements inline (`onerror=`, `onfocus=`, exercices 3 et 4) sont bloqués par le navigateur, qui affiche une erreur *Refused to execute inline script/event handler because it violates the following Content Security Policy directive* dans la console — **sans que le code vulnérable n'ait changé**. C'est le point pédagogique central : la CSP est une mesure de défense en profondeur, pas un correctif
- **Régression attendue** : l'input `#share-link` de `templates/front/category/show.html.twig` a un `onclick="this.select()"` inline, qui est lui aussi bloqué par une politique stricte. Le stagiaire doit le remarquer et le corriger en déplaçant la logique dans `assets/scripts/app.ts` (`document.getElementById('share-link')?.addEventListener('click', ...)`) plutôt qu'en ajoutant `'unsafe-inline'` (ce qui annulerait toute la protection obtenue à l'étape précédente)
- Une politique qui autorise `'unsafe-inline'` "pour que ça marche plus simplement" doit être considérée comme un échec de l'exercice : ça ne bloque plus rien des exercices précédents

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-7"></a>
## Exercice 7 — Cross-Site Request Forgery (CSRF)

**Code à recevoir** : une page HTML piège (peut être un simple fichier ouvert en local, pas besoin de l'héberger) qui déclenche la suppression du commentaire ciblé dès son chargement, par exemple une balise `<img src="https://localhost:8443/commentaires/{id}/supprimer">` ou un formulaire caché qui s'auto-soumet en `GET` vers cette URL. Le commentaire doit disparaître alors que le stagiaire n'a jamais cliqué sur le bouton **Supprimer** de l'interface, tant qu'il est connecté à Reddit-Ish dans le même navigateur. Vérifier aussi qu'il confirme l'absence d'effet une fois déconnecté.

**Pourquoi la protection habituelle de Symfony n'a pas suffi ici** : le formulaire de publication de commentaire passe par un `FormType`, qui embarque et vérifie un jeton CSRF automatiquement à chaque soumission. La route de suppression, elle, a été écrite à la main (un simple `<a href>` déclenchant un `GET`, sans passer par un objet `Form`) : rien ne la protège par défaut, il faut relire et vérifier le jeton manuellement.

**Fix** — `src/Controller/CommentController.php`, méthode `delete()` : passer la route en `POST` et vérifier un jeton CSRF.
```php
<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommentController extends AbstractController
{

    #[Route('/commentaires/{id}/supprimer', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        string                 $id,
        Request                $request,
        EntityManagerInterface $entityManager,
        CommentRepository      $commentRepository
    ): Response
    {
        if (null === $comment = $commentRepository->findOneBy(['id' => $id])) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete-comment-' . $comment->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic = $comment->getTopic();

        $entityManager->remove($comment);
        $entityManager->flush();

        return $this->redirectToRoute('app_topic_show', ['id' => $topic->getId()]);
    }

}
```

**Fix** — `templates/front/topic/show.html.twig` : le lien `<a href>` devient un formulaire `POST` avec un jeton caché (un lien GET ne peut pas porter de jeton CSRF).
```diff
                 {% if app.user and app.user == comment.author %}
-                    <a href="{{ path('app_comment_delete', { id: comment.id }) }}" class="btn btn-sm btn-outline-danger">{{ 'common.delete'|trans }}</a>
+                    <form method="post" action="{{ path('app_comment_delete', { id: comment.id }) }}" class="d-inline">
+                        <input type="hidden" name="_token" value="{{ csrf_token('delete-comment-' ~ comment.id) }}">
+                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ 'common.delete'|trans }}</button>
+                    </form>
                 {% endif %}
```

Point à faire ressortir : le token est lié à une action *et* à une ressource précise (`'delete-comment-' . $id`), pas juste à la session globale — ça empêche de réutiliser le token d'un formulaire pour en falsifier un autre.

**Catégorie OWASP** : CSRF avait sa propre entrée dans l'OWASP Top 10 2013 (A8). Depuis les éditions 2017 et 2021, elle est fusionnée dans **A01:2021 – Broken Access Control** : un attaquant qui parvient à forcer une action au nom d'une victime exploite, du point de vue du Top 10, une absence de contrôle sur qui/quoi est autorisé à déclencher cette action.

**Hors périmètre de cet exercice** : le contrôle d'accès (vérifier que l'utilisateur est bien connecté, et qu'il est bien l'auteur du commentaire) reste volontairement absent. Ce correctif rend l'action impossible à déclencher *à l'insu* de la victime, mais n'importe quel utilisateur connecté qui construit lui-même la requête `POST` avec un jeton valide (le sien, récupéré sur n'importe quelle page du site) peut toujours supprimer le commentaire d'un autre s'il devine/observe l'ID. Ce sera un exercice séparé (cf. `CLAUDE.md` §3.1) — ne pas le corriger ici, et ne pas pénaliser un stagiaire qui ne l'a pas fait.

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-8"></a>
## Exercice 8 — Integrity of JWT

**Code à recevoir** : pas de code côté "attaque" — le payload décodé du jeton (copier/coller depuis jwt.io ou la console), la démonstration qu'un jeton altéré est rejeté, et une explication écrite qui distingue clairement intégrité et confidentialité. Côté "correctif", du vrai code cette fois (listener + config), détaillé étape par étape plus bas.

**État vulnérable** (déjà en place) — payload actuel, exemple réel obtenu via `POST /api/login_check` :
```json
{
    "iat": 1789564279,
    "exp": 1789567879,
    "roles": ["ROLE_USER"],
    "username": "carter.davis1@example.com"
}
```
`username` est peuplé automatiquement par le bundle Lexik à partir de `User::getUserIdentifier()` (`src/Entity/User.php:108`), qui renvoie l'email — c'est le même champ que celui déclaré comme `property: email` dans le provider `app_user_provider` de `security.yaml`.

---

### Étape par étape (à dérouler avec le stagiaire)

**1. Décoder.** Coller le jeton sur jwt.io (ou `echo '<payload_base64url>' | base64 -d`). Header : `{"alg":"RS256","typ":"JWT"}`. Payload : le JSON ci-dessus.

**2. Lister ce qui est exposé.** `username` (= l'email) et `roles`. Rien d'autre.

**3. Tester ce que la signature protège — le titre de l'exercice est un piège volontaire.** Faire modifier une valeur du payload décodé (ex. `"roles": ["ROLE_ADMIN"]`), reconstruire un jeton avec cette modification (signature invalide ou absente côté RS256, puisqu'on ne possède pas la clé privée), et le présenter à `GET /api/user/me`. La requête est rejetée (`401 Invalid JWT Token`).
   **Conclusion à faire écrire noir sur blanc : l'intégrité est bien assurée** — personne ne peut altérer un jeton sans posséder la clé privée de l'application. Un stagiaire qui conclut qu'il faut "mieux signer" ou "vérifier la signature" n'a pas compris le problème : c'est déjà fait, et ça fonctionne. Le vrai problème est la **confidentialité** : un JWT est encodé en base64, pas chiffré, donc son contenu est lisible par quiconque le détient, sans avoir besoin de la clé ni de forger quoi que ce soit.

**4. Évaluer l'impact.**
   - **`username` (l'email) est le point critique.** C'est une donnée personnelle réutilisable ailleurs (autres services, phishing ciblé, credential stuffing). Un jeton intercepté — log applicatif, historique navigateur, proxy, ticket de support avec un jeton collé dedans — révèle directement l'identité réelle d'un utilisateur qui, partout ailleurs sur le site, n'est connu que par son pseudo
   - **`roles` est exposé sans nécessité mais moins critique** : ça ne révèle qu'un nom de rôle interne, pas une donnée personnelle. Note pour le formateur, à ne pas exiger du stagiaire : dans cette application, ce claim n'est de toute façon *pas utilisé* pour autoriser quoi que ce soit à l'exécution — `JWTAuthenticator::loadUser()` recharge l'utilisateur depuis la base à chaque requête via `app_user_provider` (voir `vendor/lexik/jwt-authentication-bundle/Security/Authenticator/JWTAuthenticator.php`), donc les rôles réellement appliqués viennent de la BDD, pas du jeton. Le stagiaire n'a pas besoin de le savoir : il suffit qu'il identifie qu'exposer `roles` reste une fuite d'information évitable, par minimisation

**5. Comparer avec la session `PHPSESSID` du site principal.** C'est l'étape la plus souvent bâclée — la voici détaillée, avec ce qu'il faut faire dire au stagiaire.

Faites-lui ouvrir DevTools → Application/Storage → Cookies sur `/`, copier la valeur de `PHPSESSID`, et poser la question frontalement : *"Si vous interceptiez uniquement cette chaîne, qu'apprenez-vous sur l'utilisateur ?"* Réponse attendue : rien. C'est une chaîne aléatoire opaque, sans structure lisible, contrairement au JWT décodable en un clic.

**Pourquoi** : avec la configuration de session par défaut de Symfony (utilisée ici), le cookie ne contient qu'un **identifiant de session** — une clé de recherche. Les données réelles (dont le jeton d'authentification interne de Symfony, une instance sérialisée de `Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken` — souvent encore désignée par son ancien nom `UserAuthenticatorToken` dans la doc/les discussions — qui embarque l'utilisateur, ses rôles, etc.) vivent **côté serveur** (fichiers dans `var/sessions/`, ou une table SQL/Redis selon la configuration). Le navigateur ne voit et n'envoie jamais que la clé.

Le JWT, lui, ne s'appuie sur *aucun* stockage serveur (`stateless: true` sur les firewalls `api_login`/`api` dans `security.yaml`). Il n'y a rien à "retrouver" côté serveur à partir du jeton : le jeton *est* la donnée. Pour que ça fonctionne sans état serveur, il faut nécessairement que le client porte lui-même le contenu — d'où le payload en clair.

**Ce que ça met en évidence, à faire formuler explicitement par le stagiaire :**

| | `PHPSESSID` (session Symfony) | JWT |
|---|---|---|
| Ce que le client détient | une **référence** opaque (juste une clé de recherche) | la **donnée elle-même** (self-contained) |
| Où vivent les données réelles | côté serveur, jamais transmises telles quelles | dans le jeton, transmises à chaque requête |
| Voler la valeur permet de... | usurper la session (l'utiliser comme clé), **pas** de lire des données personnelles à partir de la valeur seule | usurper l'accès (si le JWT est encore valide) **et** lire directement tout le payload, y compris une fois le jeton expiré ou révoqué côté client |
| Confidentialité assurée par... | l'architecture (rien de lisible n'est envoyé) | rien du tout par défaut — un JWT (JWS) est signé, pas chiffré ; il faudrait un JWE pour chiffrer le payload |

Le point pédagogique n'est donc pas de comparer deux formats équivalents (l'un n'est effectivement pas un JSON sérialisé comme l'autre, et c'est précisément pour ça que l'exercice les met en regard) : c'est de faire réaliser que "cookie de session" et "JWT" répondent tous les deux à la question *"comment l'application se souvient-elle de qui je suis entre deux requêtes ?"*, mais avec deux modèles de confiance opposés — l'un stateful et opaque (confidentialité gratuite, par construction), l'autre stateless et auto-porteur (aucune confidentialité gratuite, il faut la construire soi-même en choisissant avec soin ce qu'on met dans le payload). C'est exactement pour ça qu'un JWT mal pensé peut fuiter des données personnelles alors qu'un cookie de session mal configuré (exercice 1) ne peut, par nature, fuiter que l'accès — jamais directement des données.

---

### Fix — remplacer l'email par l'id interne dans le jeton

**Principe** : le formulaire de connexion (`POST /api/login_check`) continue d'envoyer l'email et le mot de passe — le client connaît déjà son propre email, ce n'est pas ce qui pose problème. Ce qui doit changer, c'est ce qui est *réémis* dans le jeton une fois l'authentification réussie : on substitue l'`id` numérique à l'email au moment de la génération du payload, puis on adapte le provider qui recharge l'utilisateur à chaque requête `/api/*` pour qu'il cherche par `id` et non plus par `email`.

**1. Nouveau fichier `src/EventListener/JWTCreatedListener.php`** — intercepte la création du jeton et réécrit le claim d'identité :
```php
<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['username'] = (string) $user->getId();
        unset($payload['roles']);

        $event->setData($payload);
    }
}
```
(le `unset($payload['roles'])` couvre la minimisation "aller plus loin" mentionnée plus bas — à ne pas exiger si le stagiaire ne l'a corrigé que pour `username`)

**2. `config/packages/security.yaml`** — un second provider, dédié à l'API, qui recharge l'utilisateur par `id` plutôt que par `email` :
```diff
     providers:
         # used to reload user from session & other features (e.g. switch_user)
         app_user_provider:
             entity:
                 class: App\Entity\User
                 property: email
+        # used by the api/jwt firewall, which now identifies users by id, not email
+        app_api_user_provider:
+            entity:
+                class: App\Entity\User
+                property: id
```
```diff
         api:
             pattern: ^/api
             stateless: true
+            provider: app_api_user_provider
             jwt: ~
```
`app_api_user_provider` est un second provider `entity` standard (le composant Security en supporte autant qu'on veut), simplement pointé sur la clé primaire au lieu de l'email. Aucune classe custom n'est nécessaire : `JWTAuthenticator` appelle `$provider->loadUserByIdentifier($payload['username'])`, qui vaut désormais l'`id`.

**3. Vérifier.** Se reconnecter via `/api/login_check`, redécoder le nouveau jeton sur jwt.io :
```json
{ "iat": 1789564279, "exp": 1789567879, "username": "42" }
```
Appeler `GET /api/user/me` avec ce nouveau jeton : toujours `200 OK`, réponse inchangée. La fonctionnalité est intacte, seul le contenu exposé a changé.

**Réponses attendues aux questions du `Correctif`** :
- **Quel identifiant proposer** : l'`id` numérique interne (ou un UUID si on veut en plus éviter l'énumération séquentielle d'utilisateurs via un JWT volé — à valoriser si le stagiaire y pense, pas à exiger)
- **Le rôle doit-il apparaître dans le jeton ?** Non : `JWTAuthenticator::loadUser()` recharge l'utilisateur (et donc ses rôles à jour) depuis la base à chaque requête via le provider — le claim `roles` n'est jamais consulté pour l'autorisation. L'y laisser n'apporterait rien et exposerait une information par principe de minimisation
- **Quelle propriété a réellement été corrigée ?** La **confidentialité** des données exposées par le jeton (minimisation de ce qui est lisible par un tiers qui l'intercepte) — pas son intégrité, qui était déjà garantie par la signature RS256 avant même de commencer l'exercice

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-9"></a>
## Exercice 9 — Login Throttling

**Code à recevoir** : pas de payload d'attaque — la démonstration qu'un brute-force n'est pas limité avant correctif, puis la configuration `security.yaml` + `composer.json` mis à jour, puis la démonstration que le blocage fonctionne sur les deux routes.

**État vulnérable** : il n'y a rien à "casser" pour cet exercice, contrairement à la majorité des précédents. Symfony n'active `login_throttling` sur aucun firewall à moins qu'on le configure explicitement — c'est l'état par défaut du framework, jamais modifié dans ce projet. Comme pour la CSP (exercice 6), c'est une mesure de durcissement absente, pas une régression introduite. Le composer `symfony/rate-limiter` n'est **pas installé** (vérifiable : `composer show symfony/rate-limiter` ne renvoie rien) — c'est volontaire, le stagiaire devra l'installer lui-même en suivant l'erreur explicite que Symfony renvoie s'il configure `login_throttling` sans ce paquet (`vendor/symfony/security-bundle/.../LoginThrottlingFactory.php` lève : *"Login throttling requires the Rate Limiter component. Try running 'composer require symfony/rate-limiter'."*).

---

### Étape par étape (à dérouler avec le stagiaire)

**1-2. Brute-force sans protection.** Sur `/connexion` comme sur `POST /api/login_check`, un nombre arbitraire de tentatives échouées ne change rien : toujours le même formulaire réaffiché avec la même erreur "Identifiants invalides", ou le même `401` JSON. Aucun ralentissement, aucun blocage, quel que soit le nombre d'essais.

**3. Le risque.** Sans limite, un attaquant peut faire tourner un dictionnaire de mots de passe (ou du credential stuffing avec des couples email/mot de passe fuités ailleurs) à la vitesse du réseau, sans aucun coût ajouté. Avec 10 000 mots de passe et une requête HTTP prenant ~50-100 ms, un script séquentiel simple en aurait fini en quelques minutes — en parallélisant, en quelques secondes.

**4. La fonctionnalité Symfony.** `login_throttling`, une clé de configuration disponible par firewall dans `security.yaml`, documentée dans *Security > Preventing Brute Force Login Attempts*. Le "piège au nom proche" mentionné dans la mission, c'est le composant `symfony/rate-limiter` générique (objet de l'exercice suivant) : les deux s'appuient sur la même brique technique de bas niveau (des `RateLimiterFactory`), mais `login_throttling` est une intégration prête à l'emploi spécifique aux routes d'authentification du firewall, pas quelque chose que le stagiaire doit câbler lui-même à ce stade.

**5. Les firewalls concernés.** `main` (le `form_login` qui gère `/connexion`) et `api_login` (le `json_login` qui gère `/api/login_check`) — ce sont deux firewalls distincts dans `security.yaml`, chacun avec son propre bloc `login_throttling` à ajouter séparément. Configurer uniquement `main` ne protégerait pas l'API, et inversement.

**9. Lecture du code (à faire vérifier avant même de donner le fix, c'est la partie la plus importante de l'exercice).** `DefaultLoginRateLimiter::getLimiters()` retourne **deux limiteurs** à chaque tentative :
```php
return [
    $this->globalFactory->create($this->hash($request->getClientIp())),                    // clé : IP seule
    $this->localFactory->create($this->hash($username.'-'.$request->getClientIp())),        // clé : username + IP
];
```
Et `LoginThrottlingFactory::createAuthenticator()` montre comment chacun est dimensionné par défaut :
```php
$limiterOptions['limit'] = $config['max_attempts'];       // limiteur local (username+IP)
...
$limiterOptions['limit'] = 5 * $config['max_attempts'];   // limiteur global (IP seule) : 5x plus large
```
- **Le limiteur local** (`username+IP`) bloque après `max_attempts` échecs pour *un compte donné, depuis une IP donnée*. C'est la protection contre le brute-force "en profondeur" : quelqu'un qui s'acharne sur un seul compte avec beaucoup de mots de passe
- **Le limiteur global** (`IP` seule, 5× plus permissif) bloque après `5 * max_attempts` échecs pour *toute IP donnée, tous comptes confondus*. C'est la protection contre le brute-force "en largeur" : quelqu'un qui teste un mot de passe commun (`password123`) sur des centaines de comptes différents depuis la même machine — chaque tentative individuelle ne déclencherait jamais le limiteur local (jamais deux essais sur le même compte), mais le volume total depuis cette IP, lui, finit par dépasser le seuil global

Point à faire remarquer : comme la clé du limiteur local est `username + IP` (et non `username` seul), un attaquant qui connaît juste l'email d'une victime et se trompe volontairement de mot de passe **ne bloque pas la victime** si celle-ci se connecte depuis une IP différente — chaque paire (compte, IP) a son propre compteur. Le limiteur global, lui, est bien par IP seule : si un attaquant et un utilisateur légitime partagent la même IP (réseau d'entreprise, NAT, wifi public), l'attaquant peut, en théorie, épuiser le budget global de cette IP et bloquer temporairement tout le monde derrière elle — un effet de bord à mentionner, sans en faire une exigence de correction (rester factuel : c'est un compromis connu du mécanisme, pas un bug à corriger dans le cadre de cet exercice).

**10. Blocage avant vérification du mot de passe.** `LoginThrottlingListener::checkPassport()` s'exécute sur `CheckPassportEvent`, *avant* que le mot de passe ne soit vérifié par l'authenticator. Une fois le seuil atteint, même le bon mot de passe est rejeté immédiatement (`TooManyLoginAttemptsAuthenticationException`), sans même être comparé — le compte reste inaccessible jusqu'à expiration de la fenêtre, qu'on présente ou non les bons identifiants entre-temps. C'est cohérent avec l'objectif (empêcher de *tester* des mots de passe), mais c'est un vrai effet secondaire pour l'utilisateur légitime qui se serait trompé plusieurs fois avant de retrouver son mot de passe : il doit attendre la fin de la fenêtre comme un attaquant l'aurait fait.

---

### Fix — activer `login_throttling` sur les deux firewalls

**1. Installer le composant Rate Limiter**, absent du projet :
```bash
composer require symfony/rate-limiter
```

**2. `config/packages/security.yaml`** :
```diff
     firewalls:
         dev:
             pattern: ^/(_profiler|_wdt|assets|build)/
             security: false

         api_login:
             pattern: ^/api/login_check
             stateless: true
             json_login:
                 check_path: /api/login_check
                 username_path: email
                 password_path: password
                 success_handler: lexik_jwt_authentication.handler.authentication_success
                 failure_handler: lexik_jwt_authentication.handler.authentication_failure
+            login_throttling:
+                max_attempts: 5
+                interval: '15 minutes'

         api:
             pattern: ^/api
             stateless: true
             jwt: ~

         main:
             lazy: true
             provider: app_user_provider
             form_login:
                 login_path: app_login
                 check_path: app_login
                 enable_csrf: true
+            login_throttling:
+                max_attempts: 5
+                interval: '15 minutes'
             remember_me:
                 secret: '%kernel.secret%'
                 lifetime: 604800 # 1 week
                 path: /
                 secure: true
                 httponly: true
                 samesite: lax
```
Avec ces valeurs : 5 échecs autorisés par compte+IP sur 15 minutes (limiteur local), et 25 échecs par IP tous comptes confondus sur la même fenêtre (limiteur global, calculé automatiquement à `5 * max_attempts`).

**3. Vérifier.** Après 5 tentatives échouées sur `/connexion` avec le même compte : la page de login réaffiche un message du type *"Too many failed login attempts, please try again in 15 minute(s)."* (traduction Symfony du domaine `security`, à adapter en français dans `translations/security.fr.yaml` si on veut rester cohérent avec la convention "tout en français côté utilisateur" du projet — à valoriser si le stagiaire y pense, sans l'exiger). Sur `POST /api/login_check`, un `429 Too Many Requests` (ou `401` selon la version exacte du `failure_handler` JWT en place) avec un message équivalent en JSON.

**Réponses attendues aux questions du `Correctif`** :
- **Configuration utilisée** : `login_throttling` sur les firewalls `main` et `api_login`, avec `max_attempts`/`interval` au choix du stagiaire (5 tentatives / 15 minutes est un choix raisonnable, ni trop laxiste ni trop agressif pour un site grand public)
- **Protège-t-il contre un attaquant distribué sur beaucoup d'IP différentes ?** Non. Les deux limiteurs (local et global) sont, l'un comme l'autre, ancrés sur l'IP de la requête. Un attaquant qui dispose d'un botnet ou d'un pool de proxys peut répartir ses tentatives sur suffisamment d'IP différentes pour qu'aucune ne dépasse jamais le seuil (local ou global) individuellement, alors que le volume total sur le compte visé reste énorme — c'est exactement le scénario de *credential stuffing distribué* / *password spraying* à grande échelle. `login_throttling` reste une protection nécessaire (elle stoppe l'attaquant "naïf", depuis une seule machine) mais pas suffisante seule ; des mesures complémentaires existent (CAPTCHA après quelques échecs, MFA, détection d'anomalie de vélocité/géolocalisation au niveau WAF ou reverse proxy, listes de réputation d'IP), hors périmètre de cet exercice
- **Catégorie OWASP** : **A07:2021 – Identification and Authentication Failures** (anciennement A2:2017). À distinguer de l'exercice 1 (**A05:2021 – Security Misconfiguration**) : là, une protection existait par défaut et avait été désactivée ; ici, aucune protection native n'existe tant qu'on ne l'active pas explicitement — ce n'est pas une mauvaise configuration, c'est une mesure de durcissement manquante, comme la CSP de l'exercice 6

[⬆ Retour au sommaire](#sommaire)
