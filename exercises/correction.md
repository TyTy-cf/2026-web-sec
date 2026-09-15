# Correction — usage interne, ne pas commit

## Exercice 1 — Persistent XSS

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

## Exercice 2 — Broken Access Control

**Code à recevoir** : pas de payload, juste la démonstration (URL de `/topics/{id}/edit` avec l'ID d'un sujet dont il n'est pas l'auteur, formulaire soumis avec succès).

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

## Exercice 3 — Reflected XSS

**Code à recevoir** : une URL du type `?q=x onfocus=alert(1) autofocus=x` (ou équivalent), avec la popup qui se déclenche sans clic.

**Fix** — `templates/front/common/_header.html.twig`, attribut `value` du champ de recherche :
```diff
- <input class="form-control me-2" type="search" name="q" value={{ app.request.query.get('q') }} placeholder="Search topics..." aria-label="Search">
+ <input class="form-control me-2" type="search" name="q" value="{{ app.request.query.get('q') }}" placeholder="Search topics..." aria-label="Search">
```
Il suffit d'ajouter les guillemets autour de la valeur ; l'autoescape Twig fait déjà le reste.
