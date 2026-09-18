# Correction — usage interne, ne pas commit

<a id="sommaire"></a>
## Sommaire

- [Exercice 1 — Security Misconfiguration (cookie de session)](#exercice-1)
- [Exercice 2 — Persistent XSS](#exercice-2)
- [Exercice 3 — Reflected XSS](#exercice-3)
- [Exercice 4 — DOM-based XSS](#exercice-4)
- [Exercice 5 — Broken Access Control](#exercice-5)
- [Exercice 6 — En-têtes HTTP de sécurité](#exercice-6)
- [Exercice 7 — Cross-Site Request Forgery (CSRF)](#exercice-7)
- [Exercice 8 — Integrity of JWT](#exercice-8)
- [Exercice 9 — Login Throttling](#exercice-9)
- [Exercice 10 — Rate Limiter](#exercice-10)
- [Exercice 11 — Account Enumeration](#exercice-11)
- [Exercice 12.1 — Durcissement de `php.ini`](#exercice-12-1)
- [Exercice 12.2 — Upload de fichier](#exercice-12-2)
- [Exercice 12.3 — Images à URL prédictibles](#exercice-12-3)
- [Exercice 13 — Audit des dépendances](#exercice-13)
- [Exercice 14 — Politique de mot de passe](#exercice-14)

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

**Livrable attendu** : le correctif de `templates/front/topic/show.html.twig` sur la branche du stagiaire (question 4), et la réponse à la question 5 (type de XSS) dans `exercises/reponses.md`. Les questions 1 à 3 sont de la démonstration (un commentaire contenant un `<script>`, puis une version qui exécute une action réelle, ex. `fetch()` qui soumet un commentaire au nom de la victime) — rien à en attendre dans `reponses.md`, à vérifier en live/à l'oral.

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

**Et si on a vraiment besoin de `|raw` ?** (à mentionner en discussion, ne fait pas partie du correctif attendu du stagiaire ici — Reddit-Ish n'a pas d'éditeur riche sur les commentaires). Un cas légitime existe bel et bien : un champ alimenté par un éditeur WYSIWYG (CKEditor, TipTap, etc.) produit du vrai HTML (`<b>`, `<a href>`, `<ul>`...) qu'on veut afficher tel quel — retirer `|raw` casserait l'affichage.

La bonne réponse est un **sanitizer HTML en liste blanche** : un parseur qui ne conserve que les balises/attributs explicitement autorisés et supprime tout le reste, y compris `<script>`, les attributs `on*=`, ou les liens `javascript:`.

Avec le composant natif `symfony/html-sanitizer` :
```bash
composer require symfony/html-sanitizer
```
```yaml
# config/packages/html_sanitizer.yaml
framework:
    html_sanitizer:
        enabled: true
        sanitizers:
            comment_sanitizer:
                allow_safe_elements: true
                allow_elements:
                    a: ['href']
                allowed_link_schemes: ['https', 'mailto']
```
```php
// dans le contrôleur, au moment de la soumission — pas au moment de l'affichage
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

public function addComment(
    Request $request,
    Topic $topic,
    EntityManagerInterface $entityManager,
    #[Autowire(service: 'html_sanitizer.sanitizer.comment_sanitizer')] HtmlSanitizerInterface $commentSanitizer,
): Response {
    // ...
    $comment->setContent($commentSanitizer->sanitize($form->get('content')->getData()));
    // ...
}
```
Le sanitizer nettoie le HTML **avant qu'il n'atteigne la base de données**, pas au moment de l'affichage : `<script>`, les gestionnaires d'évènements, les liens `javascript:`, etc. sont retirés, tandis que `<b>`, `<i>`, `<a href="https://...">` etc. survivent. Le template peut alors garder `{{ comment.content|raw }}` en toute sécurité, puisque ce qui est stocké n'est plus jamais dangereux — ce qui protège aussi les autres consommateurs du même contenu (par exemple `GET /api/topic`, qui n'a aucun autoescape Twig et exposerait le HTML stocké tel quel dans le JSON).

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-3"></a>
## Exercice 3 — Reflected XSS

**Livrable attendu** : le correctif de `templates/front/common/_header.html.twig` sur la branche du stagiaire (question 8), et la réponse à la question 9 (type de faille XSS) dans `exercises/reponses.md`. Les questions 1 à 7 sont de la démonstration (URL du type `?q=x onfocus=alert(1) autofocus=x` ou équivalent, popup qui se déclenche sans clic) — rien à en attendre dans `reponses.md`, à vérifier en live/à l'oral.

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

**Livrable attendu** : le correctif de `assets/scripts/app.ts` sur la branche du stagiaire (question 9), et la réponse à la question 10 (ce correctif peut-il être fait côté serveur ?) dans `exercises/reponses.md`. Les questions 1 à 8 sont de la démonstration (URL du type `/categorie/1#ref=<img src=x onerror=alert(document.domain)>` ou tout autre gadget HTML avec handler d'évènement — `<script>` ne fonctionne pas via `innerHTML`, c'est un point de vérification attendu) — rien à en attendre dans `reponses.md`, à vérifier en live/à l'oral.

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

**Livrable attendu** : le correctif (Voter + contrôleur) sur la branche du stagiaire (question 6). Pas de question conceptuelle isolée dans cet exercice : rien à attendre dans `exercises/reponses.md` au-delà de la démonstration orale des questions 1 à 5 (URL de `/sujets/{id}/modifier` avec l'ID d'un sujet dont le stagiaire n'est pas l'auteur, formulaire soumis avec succès).

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
## Exercice 6 — En-têtes HTTP de sécurité

**Livrable attendu** : les trois en-têtes de sécurité sur la branche du stagiaire — la politique CSP (question 3), `Strict-Transport-Security` (question 8) et `X-Frame-Options` (question 9), preuve : les trois en-têtes visibles dans les réponses HTTP —, plus la démonstration que les payloads des exercices 2, 3 et 4 sont neutralisés (question 4) et qu'une régression a été trouvée et corrigée (question 5), sans avoir touché au code vulnérable. Vont dans `exercises/reponses.md` : la réponse à la question 7 (la CSP remplace-t-elle ou complète-t-elle les correctifs précédents ?), et les explications conceptuelles des questions 8 (ce que force HSTS, contre quoi, condition de prise en compte, piège du `max-age`) et 9 (l'attaque de clickjacking bloquée).

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

---

### Questions 8-9 — HSTS et X-Frame-Options

Les deux en-têtes s'ajoutent au même endroit que la CSP (selon l'option choisie). En Caddy :
```
# Caddyfile — dans le bloc du site
header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:"
header Strict-Transport-Security "max-age=31536000; includeSubDomains"
header X-Frame-Options "DENY"
```
En NelmioSecurityBundle :
```yaml
nelmio_security:
    forced_ssl:                     # émet l'en-tête HSTS
        hsts_max_age: 31536000
        hsts_subdomains: true
    clickjacking:
        paths:
            '^/.*': DENY            # émet X-Frame-Options: DENY
```

**Question 8 — HSTS (`Strict-Transport-Security`)** :
- Ce qu'il force : une fois l'en-tête reçu, le navigateur **refuse toute connexion en HTTP clair** vers ce domaine pendant `max-age` et passe automatiquement en HTTPS, sans même émettre la requête HTTP initiale
- Contre quoi il protège : le **SSL stripping / downgrade** — un attaquant en position d'homme du milieu qui intercepte la première requête HTTP (avant redirection) pour la maintenir en clair. HSTS supprime cette fenêtre
- Condition de prise en compte : l'en-tête n'est **honoré que s'il est reçu via une connexion HTTPS déjà valide** (un navigateur ignore un HSTS reçu en HTTP). Sur ce projet, tout passe déjà par `https://localhost:8443`, donc c'est le cas
- Le piège du `max-age` long : la directive est **mémorisée par le navigateur** pour toute sa durée. Si on pose `max-age=31536000` (1 an) et qu'on doit ensuite repasser en HTTP (ou servir un sous-domaine sans TLS, avec `includeSubDomains`), les visiteurs restent bloqués côté navigateur, sans moyen d'action côté serveur. Bonne pratique : **commencer avec un `max-age` court** (quelques minutes/heures), vérifier que tout fonctionne, puis l'augmenter progressivement. À ne surtout pas confondre avec la liste `preload` (encore plus difficile à révoquer)

**Question 9 — X-Frame-Options** :
- Démonstration : une page externe avec `<iframe src="https://localhost:8443/"></iframe>` affiche aujourd'hui le site sans problème (aucun en-tête ne l'en empêche)
- Après ajout de `X-Frame-Options: DENY` (ou `SAMEORIGIN`), le navigateur **refuse de rendre le site dans l'iframe** et affiche une erreur : l'iframe reste vide
- L'attaque bloquée est le **clickjacking** : superposer le site (invisible/transparent) au-dessus d'un leurre pour piéger la victime en lui faisant cliquer, à son insu, sur une action réelle de l'application (dans laquelle elle est authentifiée)
- Note : l'équivalent moderne est la directive CSP `frame-ancestors 'none'` (ou `'self'`), qui a l'avantage d'être plus fine et de remplacer `X-Frame-Options` sur les navigateurs récents. Un stagiaire qui la propose *en plus* ou *à la place* a raison — à valoriser

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-7"></a>
## Exercice 7 — Cross-Site Request Forgery (CSRF)

**Livrable attendu** : le correctif de `CommentController::delete()` + `show.html.twig` sur la branche du stagiaire (question 8), et la réponse à la question 10 (catégorisation OWASP) dans `exercises/reponses.md`. Les questions 1 à 7 et 9 sont de la démonstration — Envoyer un lien vers une page HTML piège (peut être un simple fichier ouvert en local, pas besoin de l'héberger) qui déclenche la suppression du commentaire ciblé dès son chargement, par exemple une balise `<img src="https://localhost:8443/commentaires/{id}/supprimer">` ou un formulaire caché qui s'auto-soumet en `GET` vers cette URL, le commentaire doit disparaître alors que le stagiaire n'a jamais cliqué sur le bouton **Supprimer** de l'interface, tant qu'il est connecté à Reddit-Ish dans le même navigateur (vérifier aussi qu'il confirme l'absence d'effet une fois déconnecté), puis la même page piège rejouée après correctif (question 9) — rien à en attendre dans `reponses.md` au-delà de la question 10.

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

**Livrable attendu** : le correctif (listener + provider) sur la branche du stagiaire (question 6), et les réponses aux questions 7 (le rôle doit-il apparaître dans le jeton ?) et 8 (quelle propriété a été corrigée) dans `exercises/reponses.md`. Les questions 1 à 5 sont de l'analyse/démonstration — le payload décodé du jeton (copier/coller depuis jwt.io ou la console), la démonstration qu'un jeton altéré est rejeté, et la comparaison avec `PHPSESSID` — rien à en attendre dans `reponses.md` au-delà de ça, à vérifier en live/à l'oral. Le correctif lui-même (listener + config) est détaillé étape par étape plus bas.

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

**Réponses attendues aux questions 6 à 8** :
- **Quel identifiant proposer (question 6)** : l'`id` numérique interne (ou un UUID si on veut en plus éviter l'énumération séquentielle d'utilisateurs via un JWT volé — à valoriser si le stagiaire y pense, pas à exiger)
- **Le rôle doit-il apparaître dans le jeton ? (question 7)** Non : `JWTAuthenticator::loadUser()` recharge l'utilisateur (et donc ses rôles à jour) depuis la base à chaque requête via le provider — le claim `roles` n'est jamais consulté pour l'autorisation. L'y laisser n'apporterait rien et exposerait une information par principe de minimisation
- **Quelle propriété a réellement été corrigée ? (question 8)** La **confidentialité** des données exposées par le jeton (minimisation de ce qui est lisible par un tiers qui l'intercepte) — pas son intégrité, qui était déjà garantie par la signature RS256 avant même de commencer l'exercice

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-9"></a>
## Exercice 9 — Login Throttling

**Livrable attendu** : le correctif (`login_throttling` + `composer.json`) sur la branche du stagiaire (question 6), et les réponses aux questions 11 (configuration utilisée), 12 (protection contre un attaquant multi-IP) et 13 (catégorisation OWASP) dans `exercises/reponses.md`. Les questions 1 à 5 et 7 à 10 sont de la démonstration/lecture de code (absence de blocage avant correctif, blocage effectif après, lecture du code source des limiteurs) — rien à en attendre dans `reponses.md` au-delà de ça, à vérifier en live/à l'oral. La question 9 (lecture du code source) est la plus importante pédagogiquement : à valider à l'oral avant de donner le fix.

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

**Réponses attendues aux questions 11 à 13** :
- **Configuration utilisée (question 11)** : `login_throttling` sur les firewalls `main` et `api_login`, avec `max_attempts`/`interval` au choix du stagiaire (5 tentatives / 15 minutes est un choix raisonnable, ni trop laxiste ni trop agressif pour un site grand public)
- **Protège-t-il contre un attaquant distribué sur beaucoup d'IP différentes ? (question 12)** Non. Les deux limiteurs (local et global) sont, l'un comme l'autre, ancrés sur l'IP de la requête. Un attaquant qui dispose d'un botnet ou d'un pool de proxys peut répartir ses tentatives sur suffisamment d'IP différentes pour qu'aucune ne dépasse jamais le seuil (local ou global) individuellement, alors que le volume total sur le compte visé reste énorme — c'est exactement le scénario de *credential stuffing distribué* / *password spraying* à grande échelle. `login_throttling` reste une protection nécessaire (elle stoppe l'attaquant "naïf", depuis une seule machine) mais pas suffisante seule ; des mesures complémentaires existent (CAPTCHA après quelques échecs, MFA, détection d'anomalie de vélocité/géolocalisation au niveau WAF ou reverse proxy, listes de réputation d'IP), hors périmètre de cet exercice
- **Catégorie OWASP (question 13)** : **A07:2021 – Identification and Authentication Failures** (anciennement A2:2017). À distinguer de l'exercice 1 (**A05:2021 – Security Misconfiguration**) : là, une protection existait par défaut et avait été désactivée ; ici, aucune protection native n'existe tant qu'on ne l'active pas explicitement — ce n'est pas une mauvaise configuration, c'est une mesure de durcissement manquante, comme la CSP de l'exercice 6

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-10"></a>
## Exercice 10 — Rate Limiter

**Livrable attendu** : uniquement du code sur la branche du stagiaire (questions 4 et 5) — pas de question conceptuelle dans cet exercice, rien à attendre dans `exercises/reponses.md`.

**État vulnérable** : un formulaire d'inscription public (`/inscription`) a été ajouté pour cet exercice — voir plus bas la note sur pourquoi il existe. Ni lui, ni `GET /api/topic` (déjà public depuis l'exercice 8) n'ont de limite de requêtes. C'est, comme pour la CSP et le Login Throttling, une mesure de durcissement absente plutôt qu'une régression : rien à "casser", `symfony/rate-limiter` n'est pas installé par défaut.

**Pourquoi deux cibles, et pourquoi `/inscription` a été créé pour l'occasion** : `login_throttling` (exercice 9) est une intégration Symfony toute faite, spécifique aux routes d'authentification d'un firewall. Ici, on veut que le stagiaire manipule directement le composant générique `symfony/rate-limiter`, dans ses deux usages réels :
- **protéger une action précise** d'une application classique (un formulaire) — c'est le cas d'usage le plus courant du composant, et celui documenté en premier dans la doc Symfony
- **protéger une route ou une famille de routes** d'une API — un cas où on ne veut pas injecter le limiteur dans chaque contrôleur un par un

Avant cet exercice, la seule route avec un formulaire public était `/connexion`, déjà mobilisée par l'exercice 9 : réutiliser la même route aurait mélangé les deux mécanismes. `/inscription` (email, pseudo, mot de passe + confirmation, `src/Controller/SecurityController::register()`) a donc été ajouté comme pur point d'ancrage pour cet exercice, sans aucune protection au départ.

**Note pour le formateur, à ne pas dévoiler au stagiaire ici** : `/inscription` réutilise le comportement par défaut de Symfony pour l'unicité d'email (`#[UniqueEntity]`), qui révèle explicitement si un email est déjà enregistré via un message d'erreur dédié sur le champ. C'est le point de départ de l'exercice 11 (énumération de comptes, cf. `CLAUDE.md` §2.11, plus bas dans ce document) — ne pas le corriger ni le mentionner dans le cadre de celui-ci.

---

### Fix — `/inscription` : limiteur injecté dans le contrôleur

**1. Installer le composant**, si ce n'est pas déjà fait à l'exercice 9 :
```bash
composer require symfony/rate-limiter
```

**2. `config/packages/rate_limiter.yaml`** (nouveau fichier, ou ajouté à `framework.yaml`) :
```yaml
framework:
    rate_limiter:
        registration:
            policy: sliding_window
            limit: 5
            interval: '15 minutes'
        public_api:
            policy: sliding_window
            limit: 60
            interval: '1 minute'
```
Nommer un limiteur `registration` (ou `public_api`) enregistre automatiquement un alias d'autowiring : `RateLimiterFactoryInterface $registrationLimiter` (respectivement `$publicApiLimiter`) — le nom du paramètre est dérivé du nom du limiteur, suffixé de `Limiter`.

**3. `src/Controller/SecurityController.php`**, méthode `register()` :
```diff
+use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
+use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

 #[Route(path: '/inscription', name: 'app_register')]
 public function register(
     Request $request,
     EntityManagerInterface $entityManager,
     UserPasswordHasherInterface $passwordHasher,
+    RateLimiterFactoryInterface $registrationLimiter,
 ): Response {
+    if ($request->isMethod('POST') && !$registrationLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
+        throw new TooManyRequestsHttpException();
+    }
+
     $user = new User();
     $form = $this->createForm(RegistrationType::class, $user);
```
`consume(1)` sur `POST` (la soumission réelle) suffit : inutile de consommer un jeton sur le simple `GET` d'affichage du formulaire, qui n'a pas d'effet de bord. Si le stagiaire consomme sur chaque requête (GET compris), ce n'est pas faux, juste plus strict — à ne pas pénaliser.

---

### Fix — `GET /api/topic` : listener sur `kernel.request`

Une route d'API ne peut pas recevoir un `RateLimiterFactory` injecté "à la main" à chaque contrôleur qu'on veut protéger sans dupliquer la logique partout ; on passe par un listener qui s'applique à toute une famille de routes.

**1. Nouveau fichier `src/EventListener/ApiRateLimitListener.php`** :
```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: 'kernel.request')]
class ApiRateLimitListener
{
    public function __construct(private RateLimiterFactoryInterface $publicApiLimiter)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/topic')) {
            return;
        }

        if (!$this->publicApiLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }
}
```
Avec l'attribut `#[AsEventListener]`, aucun enregistrement manuel dans `services.yaml` n'est nécessaire (l'autoconfiguration de Symfony s'en charge).

**2. Vérifier.** Après avoir dépassé la limite sur l'une ou l'autre route : `429 Too Many Requests`. Une utilisation normale (quelques requêtes espacées) continue de fonctionner sur les deux.

**Point à faire remarquer au stagiaire** : ce sont deux implémentations différentes du même composant sous-jacent (`symfony/rate-limiter`), choisies selon la forme de ce qu'on protège — une action précise avec un contrôleur unique (`/inscription`) contre une famille de routes traversée par plusieurs contrôleurs potentiels (`/api/*`). Aucune des deux n'est "la bonne" dans l'absolu ; c'est la forme de la cible qui dicte laquelle utiliser.

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-11"></a>
## Exercice 11 — Account Enumeration

**Livrable attendu** : le correctif (`User.php` + `SecurityController::register()`) sur la branche du stagiaire (question 7), et la réponse à la question 9 (catégorisation OWASP + lien avec un exercice précédent) dans `exercises/reponses.md`. Les questions 1 à 6 et 8 sont de la démonstration/diagnostic (comparaison des deux réponses du formulaire, lecture de `User.php`) — rien à en attendre dans `reponses.md` au-delà de la question 9, à vérifier en live/à l'oral.

**État vulnérable** (déjà en place, rien n'a été cassé exprès pour cet exercice) : `src/Entity/User.php` porte l'attribut `#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.')]`, comportement par défaut dès qu'on veut garantir l'unicité d'un champ avec le composant Validator de Symfony. `SecurityController::register()` (utilisé par §2.10) ne fait rien de spécial autour de ça — c'est le formulaire tel qu'il a été laissé après l'exercice 10.

**Ce que ça produit concrètement** :
- Inscription avec une adresse neuve → le compte est créé, flash `flash.account_created`, redirection vers `/connexion`
- Inscription avec une adresse déjà utilisée (ex. `carter.davis1@example.com`) → le formulaire est réaffiché avec une erreur *spécifique au champ email* ("Cette adresse e-mail est déjà utilisée."), aucune redirection

Les deux réponses sont trivialement distinguables (contenu de la page, présence/absence de redirection), y compris par un script automatisé qui n'a besoin de rien d'autre que le code HTTP et un `grep` sur le corps de la réponse. C'est un oracle d'énumération de comptes classique (CWE-203).

---

### Étape par étape (à dérouler avec le stagiaire)

**1-2. Comparer les deux réponses.** Faire inscrire le stagiaire une première fois avec une adresse neuve (succès + redirection), puis une seconde fois avec une adresse déjà connue (formulaire réaffiché, erreur dédiée sur le champ email). Lui faire décrire lui-même, avec ses mots, ce qui diffère.

**3-4. L'impact.** Faire formuler explicitement : n'importe qui, sans être connecté et sans connaître de mot de passe, peut soumettre une adresse e-mail à ce formulaire et savoir en une requête si elle correspond à un compte existant sur Reddit-Ish. À l'échelle (une liste de milliers d'adresses, par exemple issues d'une fuite tierce), ça permet de confirmer quelles personnes d'une liste ont un compte sur ce site précis — une information exploitable pour du phishing ciblé, ou pour croiser des identités avec d'autres fuites, même sans jamais obtenir un seul mot de passe.

**5. Lien avec le rate limiter (§2.10).** Non, le rate limiter ne supprime pas l'oracle, il ne fait que ralentir sa exploitation. Un attaquant qui respecte la limite (par exemple 5 requêtes/15 minutes, cf. exercice 10) peut toujours énumérer la totalité d'une liste, juste plus lentement — exactement le même enseignement "défense en profondeur ≠ correctif" que la CSP de l'exercice 6.

**6. L'origine technique.** L'attribut `#[UniqueEntity(fields: ['email'])]` sur `User` (`src/Entity/User.php`). Le Validator de Symfony exécute une requête de vérification d'unicité *avant* la soumission en base, et attache l'erreur au champ concerné si une correspondance existe déjà — c'est ce comportement, pas une erreur de code, qui produit la différence observée.

---

### Fix — réponse uniforme, unicité toujours garantie en base

**Principe** : la fuite ne vient pas de la contrainte d'unicité elle-même, mais du fait que le formulaire *répond différemment* selon que l'e-mail existe ou non. On retire donc la validation applicative qui produit ce message spécifique, et on fait en sorte que le contrôleur affiche toujours le même message neutre : si l'e-mail est libre, le compte est créé et l'e-mail de confirmation part ; s'il est déjà pris, on ne fait rien du tout — mais le visiteur voit exactement la même chose.

**1. `src/Entity/User.php`** — retirer `#[UniqueEntity]` (la validation applicative qui fuite) :
```diff
-use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
 use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

 #[ORM\Entity(repositoryClass: UserRepository::class)]
 #[ORM\Table(name: '`user`')]
 #[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
-#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.')]
 #[ApiResource(
```
`#[ORM\UniqueConstraint]` (la contrainte SQL) reste intacte : c'est elle qui garantit toujours qu'aucune ligne dupliquée ne puisse exister en base.

**2. `src/Controller/SecurityController.php`**, méthode `register()` — ne créer le compte et n'envoyer l'e-mail que si l'adresse est libre, et afficher le même message dans tous les cas :
```diff
     if ($form->isSubmitted() && $form->isValid()) {
-        $user->setPassword(...)
-            ->setRoles([])
-            ->setCreatedAt(new \DateTime())
-            ->setActivationCode(bin2hex(random_bytes(16)));
-
-        $entityManager->persist($user);
-        $entityManager->flush();
-
-        // ... génération de l'URL + envoi de l'e-mail ...
-
-        $this->addFlash('success', 'flash.account_created');
+        if (null === $userRepository->findOneBy(['email' => $user->getEmail()])) {
+            $user->setPassword(...)
+                ->setRoles([])
+                ->setCreatedAt(new \DateTime())
+                ->setActivationCode(bin2hex(random_bytes(16)));
+
+            $entityManager->persist($user);
+            $entityManager->flush();
+
+            // ... génération de l'URL + envoi de l'e-mail ...
+        }
+
+        $this->addFlash('success', 'flash.account_created');

         return $this->redirectToRoute('app_login');
     }
```
(+ injecter `UserRepository $userRepository` dans la signature de `register()`)

**3. `translations/messages.fr.yaml`** — le message ne doit plus affirmer que le compte a été créé, puisqu'il s'affiche aussi quand rien n'a été fait :
```diff
-    account_created: Votre compte a été créé, afin de finaliser votre inscription, validez le mail !
+    account_created: Si cette adresse e-mail est disponible, un message vous a été envoyé pour finaliser votre inscription.
```

**4. Vérifier.** Rejouer les étapes 1 et 2 du questionnaire : dans les deux cas, même message, même redirection vers `/connexion`, même code HTTP. Puis ouvrir Mailpit (http://localhost:8025/) : un e-mail de confirmation est bien parti pour l'adresse neuve, et **aucun** pour l'adresse déjà utilisée. Côté base, `SELECT COUNT(*) FROM user WHERE email = '...'` reste à 1 pour l'adresse existante.

**Point à faire ressortir avec le stagiaire** : c'est le principe du "ne jamais confirmer ni infirmer", le même que celui utilisé par les formulaires de réinitialisation de mot de passe bien conçus. L'information "ce compte existe" n'est plus donnée à l'écran ; elle n'est envoyée qu'au propriétaire réel de la boîte mail, qui lui sait déjà s'il a un compte.

**Réponse attendue à la question 9** :
- **Catégorie OWASP** : **A07:2021 – Identification and Authentication Failures** — même catégorie que l'exercice 9 (Login Throttling), CWE-203 (Observable Discrepancy) plus précisément
- **Lien avec un exercice précédent** : l'exercice 10 (Rate Limiter). Le rate limiter posé sur `/inscription` ralentit l'exploitation de cet oracle mais ne le supprime pas — un attaquant patient qui respecte la limite énumère quand même la totalité d'une liste d'adresses, juste plus lentement. C'est la même leçon "défense en profondeur ≠ correctif du bug" que l'exercice 6 (CSP), qui ne corrige aucune des trois XSS mais en limite l'impact

[⬆ Retour au sommaire](#sommaire)


---

<a id="exercice-12-1"></a>
## Exercice 12.1 — Durcissement de `php.ini`

**Livrable attendu** : le correctif dans `docker/` sur la branche du stagiaire (question 6), et les réponses aux questions 2 (intérêt de la version pour un attaquant), 4 (pourquoi l'absence de trace est un problème distinct) et 8 (catégorisation OWASP) dans `exercises/reponses.md`. Les questions 1, 3, 5 et 7 sont de l'observation/diagnostic — à vérifier en live/à l'oral.

**État vulnérable** (déjà en place, rien n'a été cassé exprès) : l'image PHP du projet part de `php:8.2-fpm` sans fichier `php.ini` fourni. Les seules directives définies dans `docker/Dockerfile` (lignes 14-16) sont `memory_limit`, `post_max_size` et `upload_max_filesize` — aucune directive de sécurité. Valeurs effectives relevées dans le conteneur :

```
expose_php               1       → en-tête "x-powered-by: PHP/8.2.33" sur chaque réponse
display_errors           1
log_errors               0       → erreurs affichées au visiteur, tracées nulle part
allow_url_fopen          1
allow_url_include        0       (déjà à la bonne valeur, valeur par défaut de PHP)
session.use_strict_mode  0
disable_functions        (vide)
open_basedir             (vide)
```

---

### Étape par étape (à dérouler avec le stagiaire)

**1. Récupérer la version de PHP depuis l'extérieur.** Un simple `curl -I https://localhost:8443/` (ou l'onglet Réseau des DevTools) suffit : `expose_php = 1` fait ajouter par PHP l'en-tête `x-powered-by: PHP/8.2.33` sur **toutes** les réponses. Aucun accès au conteneur n'est nécessaire, aucune erreur à provoquer. C'est le point d'entrée de l'exercice, et il doit être trouvé en moins d'une minute.

**2. L'intérêt pour un attaquant.** Connaître la version exacte permet de chercher directement les CVE correspondantes et de ne tenter que les exploits qui ont une chance de fonctionner, au lieu de tout essayer à l'aveugle (ce qui est bruyant et détectable). C'est de la reconnaissance : ça ne donne aucun accès en soi, mais ça transforme une attaque opportuniste en attaque ciblée. Même logique pour la bannière du serveur web, les en-têtes `Server:`, les numéros de version exposés dans les assets, etc.

**3. Erreur non gérée.** `display_errors = 1` renvoie au visiteur le message d'erreur PHP brut : **chemin absolu des fichiers sur le serveur** (`/var/www/html/src/...`), nom de la classe et de la méthode, numéro de ligne, et selon le cas une requête SQL ou une valeur de paramètre. Nuance à souligner : en `APP_ENV=dev`, c'est la page d'erreur de Symfony qui s'affiche la plupart du temps, car le framework installe son propre gestionnaire d'erreurs. `display_errors` reprend la main dès que la panne survient **avant ou en dehors** de ce gestionnaire (erreur de parsing dans un fichier PHP, dépassement de `memory_limit`, erreur pendant le boot du kernel) — c'est-à-dire précisément dans les situations non maîtrisées. C'est pour ça que la directive doit être à `off` côté serveur même quand le framework gère déjà le cas nominal : c'est la ceinture en plus des bretelles, et elle ne dépend pas de la bonne configuration de l'application.

**4. Rien n'est tracé.** `log_errors = 0` : une fois la réponse envoyée, il ne reste **aucune trace** de l'erreur côté serveur. Problème distinct du précédent, et c'est le point que le stagiaire doit formuler : `display_errors` est un problème de *confidentialité* (on en dit trop au visiteur), `log_errors` est un problème de *détection* (on ne sait rien de ce qui se passe). Les deux directives sont indépendantes, et la configuration actuelle est exactement l'inverse de ce qu'il faut : tout pour l'attaquant, rien pour l'exploitant. Sans journal, une campagne de scan ou une exploitation en cours est totalement invisible, et le post-mortem après incident est impossible.

**5. Audit complet.** Attendu que le stagiaire aille au-delà des deux directives déjà croisées, en s'appuyant sur `php -i` (ou `phpinfo()`, ou `php -r 'var_dump(ini_get(...));'`) dans le conteneur :
- `allow_url_fopen = 1` : permet à `file_get_contents()`, `fopen()`, etc. d'ouvrir une URL distante. Combiné à une entrée utilisateur non filtrée, c'est le vecteur SSRF / RFI
- `allow_url_include = 0` : déjà correct (valeur par défaut de PHP), à laisser tel quel — le stagiaire doit vérifier, pas modifier au hasard
- `session.use_strict_mode = 0` : PHP accepte un identifiant de session qu'il n'a pas généré lui-même, ce qui rend la fixation de session possible (l'attaquant impose un `PHPSESSID` connu de lui à la victime, puis réutilise la session une fois celle-ci authentifiée). À rapprocher explicitement de l'exercice 1 : la même problématique de cookie de session, mais cette fois côté serveur PHP et non côté framework
- `disable_functions` (vide) et `open_basedir` (vide) : aucune restriction sur les fonctions d'exécution système ni sur les répertoires accessibles. Ce sont des mesures de **limitation de dégâts** : elles ne corrigent aucune faille, elles réduisent ce qu'un attaquant peut faire une fois qu'il exécute du code. Attention à ne pas les durcir aveuglément : `disable_functions` peut casser des besoins métier légitimes (génération de PDF, manipulation d'images via un binaire externe), et `open_basedir` casse l'application si le chemin des sessions, du cache ou des uploads sort du périmètre déclaré

**6. Le correctif.** Point de vigilance central de l'exercice : **`expose_php` n'est pas modifiable depuis l'application**. C'est une directive `PHP_INI_PERDIR` : ni `ini_set()`, ni un appel dans `public/index.php`, ni une config Symfony ne peuvent la changer — elle est lue au démarrage du process. Un stagiaire qui tente un `ini_set('expose_php', 0)` et constate que l'en-tête est toujours là a trouvé le bon enseignement : cette catégorie de durcissement appartient à l'infrastructure, pas au code applicatif. Le correctif passe donc par un fichier `.ini` ajouté dans `/usr/local/etc/php/conf.d/` au build de l'image, puis un `make up-build` (un simple `make up` ne suffit pas, l'image doit être reconstruite).

Fichier `docker/security.ini` (nouveau) :
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /proc/self/fd/2
allow_url_fopen = Off
allow_url_include = Off
session.use_strict_mode = On
session.cookie_httponly = On
session.cookie_secure = On
```
`docker/Dockerfile`, stage `base` :
```diff
 RUN echo "memory_limit=1G" > /usr/local/etc/php/conf.d/memory_limit.ini
 RUN echo "post_max_size=256M" > /usr/local/etc/php/conf.d/post_max_size.ini
 RUN echo "upload_max_filesize=256M" > /usr/local/etc/php/conf.d/upload_max_filesize.ini
+
+COPY docker/security.ini /usr/local/etc/php/conf.d/
```
`error_log = /proc/self/fd/2` envoie les erreurs sur la sortie d'erreur du conteneur, donc dans `docker compose logs php` — la façon normale de journaliser dans un environnement conteneurisé (écrire dans un fichier à l'intérieur du conteneur, c'est écrire dans quelque chose qui disparaît au prochain `docker compose down`). Un stagiaire qui configure un chemin de fichier classique n'a pas tort sur le principe, mais il faut lui faire remarquer ce point.

Remarques sur les valeurs acceptables :
- `disable_functions` et `open_basedir` : **ne pas les exiger**. Les mentionner comme mesures de limitation de dégâts, et valoriser le stagiaire qui les propose *en expliquant le risque de régression*. Un stagiaire qui remplit `disable_functions` avec une liste copiée d'un blog sans savoir ce qu'elle casse doit être repris
- Durcir `display_errors` en `dev` rend le débogage moins confortable mais ne casse rien : Symfony continue d'afficher sa propre page d'erreur détaillée tant que `APP_DEBUG=1`. C'est un bon moment pour faire constater que les deux mécanismes sont bien indépendants

**7. Revalider.** `curl -I https://localhost:8443/` : plus d'en-tête `x-powered-by`. Reprovoquer une erreur : plus de chemin serveur dans la réponse HTTP, et l'erreur apparaît désormais dans `docker compose logs php`. Naviguer sur le site, se connecter, poster un commentaire : tout doit fonctionner à l'identique.

**8. Catégorie OWASP** : **A05:2021 – Security Misconfiguration** (A02 dans le classement 2025 présenté en cours). Même catégorie que l'exercice 1, et c'est volontaire : l'exercice 1 portait sur une protection du framework désactivée, celui-ci sur la couche en dessous — le serveur applicatif lui-même, que le framework ne configure pas et ne peut pas configurer à sa place.

[⬆ Retour au sommaire](#sommaire)


---

<a id="exercice-12-2"></a>
## Exercice 12.2 — Upload de fichier

**Livrable attendu** : le correctif (`UploaderService` et/ou `TopicType`, + éventuellement `Caddyfile`/`.htaccess` selon l'approche) sur la branche du stagiaire (question 5), et les réponses aux questions 4 (impact) et 7 (qualification + chaîne de failles) dans `exercises/reponses.md`. Les questions 1 à 3 et 6 sont de la démonstration — à vérifier en live/à l'oral.

**État vulnérable** (déjà en place) : `App\Service\UploaderService::upload()` déplace le fichier reçu directement dans `public/uploads/topic/` — un répertoire servi par le serveur web et exécutable par PHP-FPM — en ne conservant que l'extension d'origine (`getClientOriginalExtension()`), **sans aucune vérification** de type MIME ni liste blanche d'extension. Le `FileType` de `TopicType` n'a pas non plus de contrainte `Image`/`File`. `Caddyfile` route tout `*.php` vers FPM (`php_fastcgi php:9000`), y compris sous `/uploads/`.

Vérifié pendant le développement : un fichier `.php` déposé sous `public/uploads/topic/` est bien exécuté (`curl` sur son URL renvoie la sortie du script, `php_sapi_name()` = `fpm-fcgi`).

---

### Étape par étape (à dérouler avec le stagiaire)

**1. Ce que le formulaire vérifie.** Rien côté serveur : `mapped => false` sur le champ, aucune contrainte `Image`, et `UploaderService` fait un `move()` brut. Le fichier finit dans `public/uploads/topic/`, servi statiquement par Caddy. C'est la conjonction des deux (pas de filtrage **et** répertoire exécutable) qui rend la suite possible.

**2. Déposer un non-image.** Le champ `accept` HTML ne contraint que le sélecteur de fichiers du navigateur, pas la requête. Un `curl -F "topic[picture]=@shell.php;type=image/png"` (ou l'interception d'une requête légitime dont on remplace le corps) fait passer un `.php` sans problème. Note : à cause du renommage de l'exercice 12.3, le fichier est stocké sous `image-N.php` (l'extension `.php` est conservée) — le vecteur reste donc valide même avec ce renommage en place.

**3. Exécution de code.** Payload minimal de démonstration :
```php
<?php echo 'PWNED:' . shell_exec($_GET['c']);
```
uploadé, puis appelé : `https://localhost:8443/uploads/topic/image-1.php?c=id`. Le serveur renvoie la sortie de `id` — preuve d'exécution de commandes arbitraires. Un `<?php phpinfo();` suffit aussi à prouver le point sans exécuter de commande système.

**4. Impact.** Exécution de code arbitraire (RCE) dans le contexte du process PHP-FPM : lecture de `.env` (secret applicatif, `DATABASE_URL`, DSN mailer), accès complet à la base via les identifiants ainsi récupérés, lecture/écriture de n'importe quel fichier accessible à l'utilisateur FPM, dépôt d'un webshell persistant, pivot vers les autres conteneurs du réseau Docker. C'est la faille la plus grave du parcours : elle ne se contente pas d'exposer une donnée, elle donne la main sur le serveur.

**5. Le correctif — défense en profondeur.** Aucune ligne unique ne suffit ; on attend au moins deux couches, et l'idée structurelle de la diapo 36 (séparer répertoire d'upload et répertoire exécutable) doit apparaître.

*Couche 1 — filtrer ce qui entre* (`TopicType`, contrainte `Image` sur le champ) :
```php
use Symfony\Component\Validator\Constraints\Image;

->add('picture', FileType::class, [
    // ...
    'constraints' => [
        new Image(
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Merci de fournir une image valide (jpeg, png, webp).',
        ),
    ],
])
```
La contrainte `Image` vérifie le type MIME réel (via `getMimeType()`, basé sur le contenu, pas sur l'extension ni l'en-tête `Content-Type` fourni par le client) et refuse un `.php` renommé en `.png`.

*Couche 2 — ne jamais faire confiance à l'extension cliente* (`UploaderService`) : dériver l'extension du type réel plutôt que de `getClientOriginalExtension()`.
```diff
-        $filename = 'image-' . $count . '.' . $file->getClientOriginalExtension();
+        $filename = 'image-' . $count . '.' . $file->guessExtension();
```
(si l'exercice 12.3 a déjà été fait, la ligne de départ utilise `bin2hex(random_bytes(16))` au lieu de `$count` — le principe est identique : remplacer `getClientOriginalExtension()` par `guessExtension()`.)
(`guessExtension()` déduit l'extension du MIME détecté ; combiné à la couche 1, un fichier non-image n'atteint jamais ce point.)

*Couche 3 — structurelle, la plus importante selon la diapo* : que même un fichier malveillant passé malgré tout ne soit pas exécutable. Deux façons :
- **Sortir le dossier d'upload de la racine web** (`var/uploads/` au lieu de `public/uploads/`) et servir les images via un contrôleur qui lit le fichier et renvoie une `BinaryFileResponse` — Caddy ne voit alors plus jamais ces fichiers, donc ne peut plus les exécuter. C'est la réponse « propre » attendue.
- **Ou** interdire l'exécution PHP sous `/uploads/` au niveau de Caddy (ne pas router ce chemin vers `php_fastcgi`). Acceptable, mais plus fragile qu'écarter physiquement les fichiers du web root.

*Lien avec 12.1* : `disable_functions` (neutralise `shell_exec`, `system`, `exec`…) et `open_basedir` réduisent ce qu'un webshell peut faire une fois exécuté — mais **ne corrigent pas** la faille : le code s'exécute toujours, il est juste moins capable. À présenter comme limitation de dégâts, exactement comme la CSP vis-à-vis des XSS.

Un stagiaire qui ne pose que la couche 1 a le réflexe le plus courant mais pas le plus solide (un bug de détection MIME, un `.phar`, une future extension exécutable réintroduisent la faille) ; l'objectif pédagogique est qu'il formule que la mesure qui *tient dans le temps* est de rendre le répertoire non exécutable.

**6. Revalider.** Après correctif : l'upload d'un `.php` est refusé par la validation (couche 1) ; et si on force un dépôt malgré tout, l'URL du fichier ne déclenche plus d'exécution (couche 3). Une vraie image se publie et s'affiche normalement.

**7. Qualification.** La faille finale est une **RCE (Remote Code Execution)**. Elle n'est pas monolithique : elle résulte d'une **chaîne** — absence de filtrage à l'upload (*Unrestricted File Upload*, CWE-434) **+** stockage dans un répertoire exécutable par le serveur web. Retirer l'un des deux maillons casse la chaîne. C'est le point central : la gravité vient de la combinaison, pas d'un bug isolé. Catégorie OWASP : rattachée à **A05:2021 – Security Misconfiguration** pour le volet répertoire exécutable, la partie upload relevant du contrôle d'entrée (A04 – Insecure Design / validation).

[⬆ Retour au sommaire](#sommaire)


<a id="exercice-12-3"></a>
## Exercice 12.3 — Images à URL prédictibles

**Livrable attendu** : le correctif de `src/Service/UploaderService.php` sur la branche du stagiaire (question 5), et la réponse à la question 4 (en quoi c'est un problème) dans `exercises/reponses.md`. Exercice court. Les questions 1 à 3 et 6 sont de la démonstration — à vérifier en live/à l'oral.

**État vulnérable** (déjà en place) : `UploaderService::upload()` renomme chaque image de façon séquentielle et devinable — `image-1.<ext>`, `image-2.<ext>`, etc. — en comptant les fichiers déjà présents dans le dossier cible :
```php
$count = count(glob($targetDir . '/image-*')) + 1;
$filename = 'image-' . $count . '.' . $file->getClientOriginalExtension();
```
Le chemin public stocké et servi est donc `/uploads/topic/image-1.png`, `/uploads/topic/image-2.png`… Il suffit d'incrémenter le numéro dans l'URL pour parcourir **toutes** les images uploadées, sans jamais passer par la page du sujet correspondant.

---

### Étape par étape (à dérouler avec le stagiaire)

**1-2. Constater le pattern.** Deux sujets créés avec image donnent `/uploads/topic/image-1.png` et `/uploads/topic/image-2.png`. Le nommage est purement séquentiel : rien d'aléatoire, rien lié à l'utilisateur ou au sujet.

**3. Énumérer.** En changeant juste le numéro dans l'URL, on récupère n'importe quelle image, y compris celle d'un sujet qu'on n'a pas ouvert. C'est un accès direct à la ressource (IDOR sur le nom de fichier), la même logique que l'énumération de comptes de l'exercice 11, appliquée à des fichiers.

**4. Le problème.** Le contrôle d'accès (ou l'absence de publication) au niveau du sujet ne protège pas le fichier : l'image est servie directement par le serveur web depuis `public/`, sans passer par Symfony. Un tiers qui devine l'URL récupère le contenu même si le sujet est privé, supprimé, ou pas encore publié. Le nommage prédictible transforme « il faut connaître l'URL » (obscurité) en « il suffit de compter » (énumération triviale).

---

### Fix — nom non devinable via `bin2hex(random_bytes(16))`

`src/Service/UploaderService.php` :
```diff
     public function upload(UploadedFile $file, string $directory): string
     {
         $targetDir = $this->uploadDir . '/' . $directory;

-        $count = count(glob($targetDir . '/image-*')) + 1;
-        $filename = 'image-' . $count . '.' . $file->getClientOriginalExtension();
+        $filename = 'image-' . bin2hex(random_bytes(16)) . '.' . $file->getClientOriginalExtension();

         $file->move($targetDir, $filename);

         return '/uploads/' . $directory . '/' . $filename;
     }
```
`random_bytes(16)` tire 16 octets sur le générateur cryptographique du système, que `bin2hex()` transforme en 32 caractères hexadécimaux (`image-9f3c1a...e04b.png`). L'URL n'est plus ni séquentielle ni devinable : l'énumération disparaît. Le chemin reste stocké en base et servi normalement, donc l'affichage d'une image légitime est inchangé.

**Pourquoi pas `uniqid()` ?** C'est la proposition la plus fréquente en salle, et elle est *bonne dans l'intention* : elle casse effectivement le nommage séquentiel, ce qui est le vrai sujet de l'exercice. Un stagiaire qui la propose a compris le problème — il faut le valider. Mais elle est **insuffisante comme réflexe à emporter**, pour deux raisons :

1. **`uniqid()` n'est pas aléatoire, il est temporel.** Sa valeur est l'horodatage courant en microsecondes, converti en hexa — rien de plus. Deux uploads consécutifs donnent deux valeurs quasi identiques, et il suffit d'uploader soi-même une image pour connaître l'instant de référence. L'espace à explorer autour se compte alors en quelques millions de valeurs : c'est du brute force à la portée d'un script, pas un secret.
2. **C'est l'habitude qui est dangereuse.** Ici l'enjeu est modeste (des images), mais le même `uniqid()` se retrouve régulièrement sur des jetons de réinitialisation de mot de passe, des codes d'activation, des identifiants de session — où la prédictibilité devient une prise de compte. Autant prendre tout de suite le bon réflexe : **dès qu'une valeur doit être non devinable, c'est `random_bytes()`**, jamais `uniqid()`, `rand()`, `mt_rand()` ni `md5(time())`.

Le coût est identique (une ligne, une fonction native), donc il n'y a aucune raison de choisir la version faible. À signaler : le projet faisait la même erreur sur `SecurityController::register()` pour l'`activationCode` — corrigé de la même façon.

**Point à faire ressortir** : même avec un nom parfaitement aléatoire, l'image reste servie en accès public par le serveur web — quiconque a l'URL (partagée, dans un log, dans un en-tête `Referer`, dans le HTML de la page) y accède toujours. Un nom imprévisible n'est pas un contrôle d'accès, c'est de la sécurité par l'obscurité. Pour une vraie confidentialité (image réservée à certains utilisateurs), il faudrait servir le fichier via un contrôleur Symfony qui applique un contrôle d'accès, et sortir le dossier de `public/`. Hors périmètre de cet exercice court, mais bonne piste de discussion — le renommage corrige l'énumération, pas l'exposition publique.

[⬆ Retour au sommaire](#sommaire)


---

<a id="exercice-13"></a>
## Exercice 13 — Audit des dépendances

**Livrable attendu** : le `composer.lock` mis à jour sur la branche du stagiaire (question 3), et les réponses aux questions 4 (paquets abandonnés), 5 (automatisation) et 6 (la sécurité dans le temps) dans `exercises/reponses.md`. Les questions 1-2 sont de la lecture de rapport — à vérifier en live/à l'oral.

**État vulnérable** : rien n'a été introduit exprès. Les dépendances du projet, figées dans `composer.lock`, ont simplement pris de l'âge : au moment d'écrire ces lignes, `composer audit` remonte **plusieurs advisories réelles** sur des paquets Symfony (`symfony/http-foundation`, `symfony/routing`, `symfony/security-http`) et sur `twig/twig`, plus deux **paquets abandonnés** (`sebastian/*`, tirés par PHPUnit). Le nombre exact évoluera avec le temps — c'est justement le propos de l'exercice.

---

### Étape par étape (à dérouler avec le stagiaire)

**1. Lancer l'audit.** `composer audit` (dans le conteneur `php`), livré avec Composer, sans rien à installer. Il compare les versions figées dans `composer.lock` à la base publique d'avis de sécurité PHP et liste les advisories, leur sévérité, les versions affectées et corrigées. `symfony console check:security` (Symfony CLI) fait l'équivalent — accepter les deux.

**2. Lire un rapport.** Pour une advisory donnée, le stagiaire doit savoir extraire : le **paquet** concerné, la ligne **Affected versions** (les plages vulnérables), et en déduire la **première version corrigée** (la borne haute de la dernière plage affectée). Exemple type : une faille corrigée en `7.4.x` alors que le projet est figé sur une version antérieure de la même branche.

**3. Corriger.** Ici, toutes les failles remontées sont dans des paquets Symfony/Twig déjà présents dans des branches compatibles avec les contraintes du `composer.json` (`7.4.*`, `^3.0`…) : un simple `composer update` (éventuellement ciblé, `composer update symfony/* twig/twig`) suffit à passer aux versions corrigées, **sans changer une ligne de code applicatif**. Relancer `composer audit` : les advisories corrigées disparaissent. Ce qui a changé : uniquement les numéros de version (et les hash) dans `composer.lock` — d'où l'importance de committer ce fichier, c'est lui qui fige ce qui tourne réellement.
   - Point à souligner : si une faille n'était corrigée que dans une version majeure supérieure interdite par le `composer.json` (ex. la faille est fixée en `8.0` alors qu'on est contraint en `7.4.*`), `composer update` ne suffirait pas — il faudrait relever la contrainte, ce qui devient une vraie montée de version, avec ses risques de régression. Ce n'est pas le cas ici, mais c'est le scénario réaliste à mentionner.

**4. Paquets abandonnés.** Non, un paquet « abandonné » n'est **pas** une vulnérabilité : c'est un paquet dont le mainteneur a annoncé qu'il ne le maintiendrait plus. Ce n'est pas une faille aujourd'hui, mais un **risque futur** (aucun correctif ne viendra si une faille est découverte). Ici les deux paquets signalés (`sebastian/*`) sont des dépendances *de développement* tirées par PHPUnit, jamais déployées en production : l'impact est nul, rien à faire dans l'immédiat. Le bon réflexe est de distinguer « faille à corriger maintenant » de « dette à surveiller ». Ne pas exiger d'action sur ces paquets.

**5. Automatiser.** L'audit ne doit pas dépendre de la mémoire d'un développeur. Réponses acceptables : ajouter `composer audit` comme **étape de CI** qui fait échouer le build si une faille est trouvée (le code de sortie est non nul quand il y a des advisories) ; un **hook** de pré-déploiement ; ou un outil de veille continue type **Dependabot** / **Renovate** qui ouvre automatiquement des PR de montée de version. L'idée clé attendue : déplacer la vérification *en amont de la mise en production*, de façon systématique.

**6. Prendre du recul.** Le point pédagogique final : **la sécurité n'est pas un état figé**. Ce projet n'a pas changé d'une ligne, et pourtant il est devenu vulnérable, simplement parce que des failles ont été *découvertes* dans du code qu'on exécute sans l'avoir écrit. Un audit passé au vert aujourd'hui ne garantit rien pour dans six mois. La sécurité d'un projet est un processus continu (cf. la diapo « la sécurité, un cycle »), pas une case cochée une fois pour toutes — c'est exactement pour ça que l'audit doit être automatisé (question 5) plutôt que joué ponctuellement.

**Catégorie OWASP** : **A03:2025 – Chaîne d'approvisionnement** (Software Supply Chain Failures), qui élargit l'ancien *A06:2021 – Vulnerable and Outdated Components*.

[⬆ Retour au sommaire](#sommaire)

---

<a id="exercice-14"></a>
## Exercice 14 — Politique de mot de passe

**Livrable attendu** : le correctif sur la branche du stagiaire (question 5 — `src/Entity/User.php` **et** `src/Form/RegistrationType.php`), et les réponses aux questions 3 (ce que disent CNIL/NIST), 4 (les autres portes d'entrée) et 7 (prise de recul) dans `exercises/reponses.md`. Les questions 1, 2 et 6 sont de l'observation — à vérifier en live.

**État vulnérable** (déjà en place) : le champ `plainPassword` de `RegistrationType` ne porte qu'un `NotBlank()`. N'importe quel mot de passe d'un seul caractère est accepté :
```php
->add('plainPassword', RepeatedType::class, [
    'type' => PasswordType::class,
    'mapped' => false,
    // ...
    'constraints' => [
        new NotBlank(),
    ],
])
```

**Exercice volontairement « bateau »** : la mesure est évidente et connue de tous. L'intérêt pédagogique n'est pas *quelle* règle, mais **où on la pose** (questions 4-5) et **quelles règles sont réellement efficaces** (question 3) — deux points sur lesquels l'intuition de la majorité des stagiaires est fausse.

---

### Étape par étape (à dérouler avec le stagiaire)

**1. Constater.** Un mot de passe `a` passe. Le compte est créé, l'e-mail d'activation part, et la connexion fonctionne. Aucune règle de qualité n'existe.

**2. Lire le code.** Le stagiaire doit trouver `RegistrationType.php` et le `new NotBlank()` isolé. Le point à faire préciser : **le stockage, lui, est correct** — `SecurityController::register()` passe par `UserPasswordHasherInterface::hashPassword()`, donc le mot de passe est haché, jamais stocké en clair. Il faut que le stagiaire sépare nettement les deux sujets : *bien stocker* un mot de passe faible ne le rend pas fort ; *bien choisir* un mot de passe stocké en clair ne protège de rien. Les deux sont nécessaires, aucun ne remplace l'autre.

**3. Définir la politique — le contre-pied attendu.** La réponse spontanée est presque toujours « 8 caractères, une majuscule, un chiffre, un caractère spécial ». **C'est la réponse d'il y a quinze ans, et elle est aujourd'hui déconseillée** par le NIST (SP 800-63B) comme par la CNIL (délibération n° 2022-100). Raison : ces règles de composition produisent `Bonjour2026!` — formellement conforme, et présent dans tous les dictionnaires d'attaque. Elles font porter la charge à l'utilisateur sans augmenter l'entropie réelle, et le poussent vers des variantes prévisibles (`P@ssw0rd`, incrémentation d'un chiffre à chaque changement obligatoire).

Ce qui est réellement efficace, par ordre de gain :

| Mesure | Effet réel |
|---|---|
| **Longueur minimale** (12, idéalement 14+) | Le seul facteur d'entropie qui compte vraiment |
| **Rejet des mots de passe compromis** | Bloque le *credential stuffing*, qui est l'attaque réellement pratiquée |
| **Rate limiting / throttling sur le login** | Rend le brute force en ligne inopérant, quel que soit le mot de passe |
| **MFA** | Seule mesure qui protège encore si le mot de passe est connu |
| Règles de composition | Marginal, souvent contre-productif |

Corollaires à mentionner : pas d'expiration périodique forcée (déconseillée, elle dégrade la qualité des mots de passe choisis), et il faut **autoriser le coller** et positionner `autocomplete="new-password"` pour ne pas casser les gestionnaires de mots de passe — l'ergonomie va ici dans le même sens que la sécurité.

**4. Les autres portes d'entrée — le cœur de l'exercice.** Poser la règle dans `RegistrationType` ne protège **que ce formulaire**. Or un mot de passe peut entrer dans l'application par :
- l'**API** (API Platform est installé et expose déjà `User`) ;
- les **fixtures** et les commandes de console ;
- un **futur formulaire** de réinitialisation ou de changement de mot de passe — celui qu'on ajoutera dans six mois en oubliant d'y recopier les contraintes.

D'où la règle générale : **une contrainte métier appartient au modèle, pas à un formulaire.** Le formulaire est une porte ; l'entité est la pièce. C'est la formulation à faire ressortir.

**5. Le correctif.**

*a — Ajouter une propriété non persistée sur l'entité.* `src/Entity/User.php` :
```diff
+use Symfony\Component\Validator\Constraints as Assert;
+
 class User implements UserInterface, PasswordAuthenticatedUserInterface
 {
     // ...

+    /**
+     * Mot de passe en clair, jamais persisté : sert uniquement de support
+     * aux contraintes de validation avant hachage.
+     */
+    #[Assert\NotBlank(groups: ['registration'])]
+    #[Assert\Length(
+        min: 12,
+        max: 4096,
+        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
+    )]
+    #[Assert\PasswordStrength(
+        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
+        message: 'Ce mot de passe est trop faible, choisissez-en un moins prévisible.',
+    )]
+    #[Assert\NotCompromisedPassword(
+        message: 'Ce mot de passe figure dans une fuite de données connue, choisissez-en un autre.',
+    )]
+    private ?string $plainPassword = null;
+
+    public function getPlainPassword(): ?string
+    {
+        return $this->plainPassword;
+    }
+
+    public function setPlainPassword(?string $plainPassword): static
+    {
+        $this->plainPassword = $plainPassword;
+
+        return $this;
+    }
+
+    public function eraseCredentials(): void
+    {
+        $this->plainPassword = null;
+    }
```
Aucun `#[ORM\Column]` : la propriété n'existe qu'en mémoire, rien à migrer. Aucun `#[Groups]` non plus : elle ne doit jamais être sérialisée par API Platform.

*b — Brancher le formulaire sur l'entité.* `src/Form/RegistrationType.php` — le champ devient mappé et perd ses contraintes locales, qui feraient doublon :
```diff
             ->add('plainPassword', RepeatedType::class, [
                 'type' => PasswordType::class,
-                'mapped' => false,
                 'first_options' => [
                     'label' => 'register.password_label',
-                    'attr' => ['class' => 'form-control'],
+                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                 ],
                 'second_options' => [
                     'label' => 'register.password_confirm_label',
-                    'attr' => ['class' => 'form-control'],
+                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                 ],
                 'invalid_message' => 'Les mots de passe ne correspondent pas.',
-                'constraints' => [
-                    new NotBlank(),
-                ],
             ])
```
et déclarer le groupe de validation :
```diff
         $resolver->setDefaults([
             'data_class' => User::class,
             'translation_domain' => 'messages',
+            'validation_groups' => ['Default', 'registration'],
         ]);
```

*c — Adapter le contrôleur.* `src/Controller/SecurityController.php` — lire le mot de passe sur l'entité, et l'effacer une fois haché :
```diff
-            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()))
+            $user->setPassword($passwordHasher->hashPassword($user, $user->getPlainPassword()))
                 ->setRoles([])
                 ->setCreatedAt(new \DateTime())
                 ->setActivationCode(bin2hex(random_bytes(16)));
+
+            $user->eraseCredentials();
```

**Ce que font les contraintes** :
- `Length(min: 12)` — le facteur d'entropie. Le `max: 4096` n'est pas décoratif : sans borne haute, un mot de passe de plusieurs mégaoctets soumis à bcrypt/argon devient un déni de service applicatif.
- `PasswordStrength` — mesure l'entropie réelle (logique zxcvbn) au lieu de cocher des classes de caractères : `Bonjour2026!` est rejeté, `girafe-tabouret-orage` est accepté. C'est exactement le renversement de la question 3, rendu concret.
- `NotCompromisedPassword` — interroge l'API Have I Been Pwned. **Point de discussion à ne pas manquer** : la question « on envoie le mot de passe des utilisateurs à un tiers ?! » tombe systématiquement. Non : la contrainte utilise le *k-anonymity*, seuls les **5 premiers caractères du SHA-1** partent sur le réseau, l'API renvoie tous les hash correspondant à ce préfixe, et la comparaison finale se fait localement. Le mot de passe ne quitte jamais le serveur. À noter aussi qu'elle échoue « ouverte » si l'API est injoignable, et qu'elle suppose un accès réseau sortant — en environnement fermé, on la remplace par une liste locale.

**Note formateur — le piège du `NotBlank`** : c'est la seule contrainte qui pose problème sur l'entité. `Length`, `PasswordStrength` et `NotCompromisedPassword` ignorent nativement `null` et la chaîne vide, donc elles ne gênent jamais la validation d'un `User` existant dont `plainPassword` est vide (édition de profil, validation déclenchée par API Platform…). `NotBlank`, lui, ferait échouer toute validation d'un utilisateur déjà en base. D'où le groupe `registration`, activé uniquement par le formulaire d'inscription. Un stagiaire qui pose un `NotBlank` sans groupe aura un correctif qui « marche » à l'inscription et casse ailleurs : très bon moment pour faire toucher du doigt les groupes de validation.

**6. Revalider.** `a` → refusé (longueur). `motdepassemotdepasse` → 20 caractères, passe la longueur, mais rejeté par `NotCompromisedPassword`. `Azertyuiop123456` → rejeté (compromis et/ou score trop faible). `girafe-tabouret-orage` → accepté. Faire lire les messages d'erreur : ils doivent **énoncer la règle**, pas se contenter de « mot de passe invalide » — un message opaque conduit l'utilisateur à tâtonner vers le mot de passe le plus faible qui passe.

**7. Prendre du recul.** Non, une politique stricte ne suffit pas. Elle réduit la probabilité qu'un mot de passe soit deviné, mais :
- contre le brute force en ligne, ce sont le **login throttling** (exercice 9) et le **rate limiter** (exercice 10) qui agissent ;
- contre l'énumération de comptes qui précède l'attaque, c'est l'exercice 11 ;
- et si le mot de passe est déjà connu de l'attaquant (fuite, phishing, keylogger), **aucune politique ne protège plus** : seule la **MFA** le fait.

Le message de fin : la politique de mot de passe est une couche parmi d'autres, et c'est probablement celle sur laquelle on a le moins de levier réel — puisqu'elle dépend in fine d'un choix humain. Bonne illustration de la défense en profondeur.

**Catégorie OWASP** : **A07:2021 – Identification and Authentication Failures** (exigences de mot de passe faibles ou absentes, et absence de vérification contre les mots de passe compromis).

[⬆ Retour au sommaire](#sommaire)
