# Correction — usage interne, ne pas commit

## Exercice 1 — Security Misconfiguration (cookie de session)

**Code à recevoir** : pas de payload. Le livrable est la configuration corrigée de `config/packages/framework.yaml`, plus la capture/lecture des attributs du cookie dans les DevTools avant/après.

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

---

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

---

## Exercice 3 — Broken Access Control

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

---

## Exercice 4 — Reflected XSS

**Code à recevoir** : une URL du type `?q=x onfocus=alert(1) autofocus=x` (ou équivalent), avec la popup qui se déclenche sans clic.

**Fix** — `templates/front/common/_header.html.twig`, attribut `value` du champ de recherche :
```diff
- <input class="form-control me-2" type="search" name="q" value={{ app.request.query.get('q') }} placeholder="Search topics..." aria-label="Search">
+ <input class="form-control me-2" type="search" name="q" value="{{ app.request.query.get('q') }}" placeholder="Search topics..." aria-label="Search">
```
Il suffit d'ajouter les guillemets autour de la valeur ; l'autoescape Twig fait déjà le reste.

---

## Exercice 5 — DOM-based XSS

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

---

## Exercice 6 — Content Security Policy

**Code à recevoir** : pas de payload. Le livrable est une politique CSP fonctionnelle (preuve : en-tête `Content-Security-Policy` visible dans les réponses HTTP), plus la démonstration que les payloads des exercices 2, 4 et 5 sont neutralisés sans avoir touché au code vulnérable.

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
- Sans `'unsafe-inline'` dans `script-src`, les balises `<script>` injectées (exercice 2) et les gestionnaires d'évènements inline (`onerror=`, `onfocus=`, exercices 4 et 5) sont bloqués par le navigateur, qui affiche une erreur *Refused to execute inline script/event handler because it violates the following Content Security Policy directive* dans la console — **sans que le code vulnérable n'ait changé**. C'est le point pédagogique central : la CSP est une mesure de défense en profondeur, pas un correctif
- **Régression attendue** : l'input `#share-link` de `templates/front/category/show.html.twig` a un `onclick="this.select()"` inline, qui est lui aussi bloqué par une politique stricte. Le stagiaire doit le remarquer et le corriger en déplaçant la logique dans `assets/scripts/app.ts` (`document.getElementById('share-link')?.addEventListener('click', ...)`) plutôt qu'en ajoutant `'unsafe-inline'` (ce qui annulerait toute la protection obtenue à l'étape précédente)
- Une politique qui autorise `'unsafe-inline'` "pour que ça marche plus simplement" doit être considérée comme un échec de l'exercice : ça ne bloque plus rien des exercices précédents

---

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

---

## Exercice 8 — Integrity of JWT

**Code à recevoir** : pas de code à proprement parler — le payload décodé du jeton (copier/coller depuis jwt.io ou la console), la démonstration qu'un jeton altéré est rejeté, et une explication écrite qui distingue clairement intégrité et confidentialité.

**Payload actuel** (exemple réel obtenu via `POST /api/login_check`) :
```json
{
    "iat": 1789564279,
    "exp": 1789567879,
    "roles": ["ROLE_USER"],
    "username": "carter.davis1@example.com"
}
```

**Point à faire émerger en premier — le titre de l'exercice est un piège volontaire.** Demandez au stagiaire de modifier une valeur du payload décodé (ex. `"roles": ["ROLE_ADMIN"]`), de reconstruire un jeton avec cette modification (signature invalide ou absente), et de le présenter à `GET /api/user/me`. La requête est rejetée (`401 Invalid JWT Token`) : la signature RS256 ne correspond plus au contenu modifié. **L'intégrité est donc bien assurée** — personne ne peut altérer un jeton sans posséder la clé privée de l'application. Un stagiaire qui conclut qu'il faut "mieux signer" ou "vérifier la signature" n'a pas compris le problème : c'est déjà fait, et ça fonctionne. Le vrai problème est la **confidentialité** : un JWT est encodé en base64, pas chiffré, donc son contenu est lisible par quiconque le détient, sans avoir besoin de la clé.

**Vulnérabilités attendues dans la réponse** :
1. **`username` contient l'adresse email en clair.** C'est une donnée personnelle réutilisable ailleurs (autres services, phishing ciblé, credential stuffing). Un jeton intercepté — log applicatif, historique navigateur, proxy, ticket de support avec un jeton collé dedans — révèle directement l'identité réelle d'un utilisateur qui, partout ailleurs sur le site, n'est connu que par son pseudo
2. **`roles` est exposé sans nécessité.** Moins critique (ça ne révèle qu'un nom de rôle interne, pas une donnée personnelle), mais c'est une information sur le modèle d'autorisation de l'application qui n'a pas besoin d'être publique. Note pour le formateur, à ne pas exiger du stagiaire : dans cette application, ce claim n'est de toute façon *pas utilisé* pour autoriser quoi que ce soit à l'exécution — `JWTAuthenticator::loadUser()` recharge l'utilisateur depuis la base à chaque requête via `app_user_provider` (voir `vendor/lexik/jwt-authentication-bundle/Security/Authenticator/JWTAuthenticator.php`), donc les rôles réellement appliqués viennent de la BDD, pas du jeton. Le stagiaire n'a pas besoin de le savoir : il suffit qu'il identifie qu'exposer `roles` reste une fuite d'information évitable

**Piège classique à anticiper — un champ "url".** Un réflexe naturel est de remplacer `username: "email@..."` par quelque chose qui *ressemble* à un identifiant plus neutre, par exemple une URL du type `/api/user/me`. **C'est un mauvais correctif, ne pas le valider tel quel** : `/api/user/me` est strictement identique pour tous les utilisateurs, ce n'est pas un identifiant, juste une route relative. Or le claim d'identité du jeton (`username`, ou plus généralement ce que configure `lexik_jwt_authentication.user_id_claim`) doit rester unique par utilisateur : c'est cette valeur que `JWTAuthenticator` transmet au `UserProvider` pour retrouver *quel* utilisateur s'authentifie. Une valeur identique pour tout le monde ne protège rien, elle casse purement et simplement l'authentification (impossible de savoir qui est qui). Si le stagiaire propose cette piste, faites-le buter dessus : est-ce que ça fonctionnerait encore pour distinguer deux comptes différents ? Une URL *paramétrée* par utilisateur (ex. `/api/users/{id}`) contournerait ce problème précis, mais n'apporterait rien de plus qu'exposer directement l'`id` — c'est l'identifiant en dessous qui doit être non sensible, pas son emballage

**Correctif attendu** : remplacer l'email par un identifiant non sensible et unique par utilisateur — typiquement l'`id` interne numérique (ou un UUID si l'on veut aussi éviter l'énumération séquentielle). Concrètement :
- Le formulaire de connexion (`/api/login_check`) continue d'envoyer l'email et le mot de passe : le client connaît déjà son propre email, ce n'est pas ce qui pose problème
- Ce qui doit changer, c'est ce qui est *réémis* dans le jeton après authentification : personnaliser la génération du payload (écouter l'évènement `lexik_jwt_authentication.on_jwt_created` pour substituer l'`id` à l'email dans `username`) et adapter le provider chargé de recharger l'utilisateur à partir de cet identifiant plutôt que de l'email

**Aller plus loin (optionnel, à valoriser si le stagiaire y pense sans l'exiger)** : proposer de retirer `roles` du payload par principe de minimisation, même si non exploitable aujourd'hui dans cette application précise.
