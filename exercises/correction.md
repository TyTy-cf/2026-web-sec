# Correction — usage interne, ne pas commit

## Exercice 1 — Persistent XSS

**Code à recevoir** : un commentaire contenant un `<script>`, puis une version qui exécute une action (ex. `fetch()` qui soumet un commentaire au nom de la victime).

**Fix** — `templates/front/topic/show.html.twig` ligne 37 :
```diff
- <div>{{ comment.content|raw }}</div>
+ <div>{{ comment.content }}</div>
```
Laisser l'autoescape Twig faire son travail (retirer `|raw`).

---

## Exercice 2 — Broken Access Control

**Code à recevoir** : pas de payload, juste la démonstration (URL de `/topics/{id}/edit` avec l'ID d'un sujet dont il n'est pas l'auteur, formulaire soumis avec succès).

**Fix** — `src/Controller/TopicController.php`, méthode `edit()` :
```diff
  public function edit(
      Topic $topic,
      Request $request,
      EntityManagerInterface $entityManager,
  ): Response {
+     if ($this->getUser() !== $topic->getAuthor()) {
+         throw $this->createAccessDeniedException();
+     }
+
      $form = $this->createForm(TopicType::class, $topic);
```
`getUser()` renvoie `null` si non connecté, donc ce seul check couvre les deux exigences (connecté + propriétaire).

---

## Exercice 3 — Reflected XSS

**Code à recevoir** : une URL du type `?q=x onfocus=alert(1) autofocus=x` (ou équivalent), avec la popup qui se déclenche sans clic.

**Fix** — `templates/front/common/_header.html.twig`, attribut `value` du champ de recherche :
```diff
- <input class="form-control me-2" type="search" name="q" value={{ app.request.query.get('q') }} placeholder="Search topics..." aria-label="Search">
+ <input class="form-control me-2" type="search" name="q" value="{{ app.request.query.get('q') }}" placeholder="Search topics..." aria-label="Search">
```
Il suffit d'ajouter les guillemets autour de la valeur ; l'autoescape Twig fait déjà le reste.
