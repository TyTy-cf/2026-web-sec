
# Le projet


- Application : https://localhost:8443/
- Connectez-vous sur https://localhost:8443/login avec n'importe quel compte prérempli, par exemple :
    - email : `carter.davis1@example.com`
    - mot de passe : `123`
      (tous les utilisateurs préremplis partagent ce même mot de passe)


# Exercice 1 — Cross-Site Scripting (XSS)


## Pour commencer


- Une fois connecté, ouvrez n'importe quel sujet depuis la page d'accueil ; vous verrez le contenu du sujet, ses commentaires et un formulaire pour en publier un nouveau


## Mission


1. **Trouver le point d'injection.** Publiez un commentaire sur n'importe quel sujet. Essayez un contenu qui serait dangereux s'il atteignait la page sans être échappé, par exemple :
   ```html
   <script>document.title = 'XSS'</script>
   ```
   Rechargez la page du sujet. Le titre de l'onglet a-t-il changé ?

2. **Confirmer qu'il est stocké et non réfléchi.** Ouvrez la même page de sujet dans une fenêtre de navigation privée (ou déconnectez-vous et reconnectez-vous avec un autre utilisateur prérempli). Si la charge utile s'exécute toujours pour un visiteur qui ne l'a jamais soumise lui-même, vous avez confirmé un **XSS stocké** : la charge utile réside dans la base de données et cible chaque futur visiteur de cette page.

3. **Aller au-delà d'une simple popup : démontrer un impact réel.** Un simple `alert(1)` prouve l'exécution de code, mais ne démontre pas pourquoi cela est dangereux. Essayez d'illustrer une action effectuée *au nom de la victime* sans son consentement, par exemple une charge utile qui soumet silencieusement un autre commentaire via `fetch()` lors du chargement de la page :
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


## Vous devez avoir fait


- Une balise `<script>` dans un commentaire et obtenu son exécution lors d'un chargement standard de page (sans astuce dans les outils de développement)
- Vérifier que cela se déclenche pour une session ou un utilisateur *différent*, prouvant ainsi qu'elle est stockée
- Un script effectuant une action au nom de la victime (pas uniquement un `alert()`)


## Correctif


- Mettez en place le correctif pour éviter que cela ne se reproduise
- Quel type de XSS vient-on de corriger ?


# Exercice 2 — Contrôle d'accès défaillant


## Pour commencer


- Connectez-vous avec ce compte pour voir le bouton `Edit` :
    - `isabella.young62@example.com` / `123`
      (tous les utilisateurs préremplis partagent ce même mot de passe)
- Un bouton **Edit** a été ajouté sur la page d'un sujet, visible uniquement par son auteur, qui mène vers un formulaire de modification du titre et du contenu

## Mission


1. **Utiliser la fonctionnalité normalement.** Avec le premier compte, ouvrez un sujet dont il est l'auteur, cliquez sur **Edit**, modifiez le titre ou le contenu, enregistrez. Vérifiez que la modification est bien prise en compte

2. **Accéder à une autre ressource.** Toujours connecté avec le premier compte, essayez d'atteindre le formulaire d'édition d'un sujet dont il n'est *pas* l'auteur (passer un autre ID) en construisant l'URL vous-même plutôt qu'en cliquant sur un lien de l'interface. Vous accéder bien à la ressource ?

3. **Confirmer l'impact.** Si le formulaire s'affiche, allez jusqu'au bout : soumettez une modification et vérifiez, avec le second compte, que le contenu de son sujet a bien changé

4. **Reproduire avec un autre couple compte/sujet** pour vous assurer que ce n'est pas un cas particulier lié à ce sujet précis

5. **Que se passe t'il avec un utilisateur non connecté ?** Essayez d'accéder au formulaire de modification sans être connecté


## Vous devez avoir fait


- Modifié avec succès un sujet dont vous n'êtes pas l'auteur, en accédant directement à l'URL du formulaire d'édition (sans passer par un lien affiché dans l'interface)
- Confirmé, en vous reconnectant avec le compte propriétaire du sujet, que la modification a bien été enregistrée
- Identifié précisément ce qui, dans la page ou dans la requête, aurait dû être vérifié pour empêcher cela


## Correctif


- Mettez en place le correctif pour empêcher un utilisateur d'éditer un sujet qui ne lui appartient pas. Le bouton **Edit** déjà masqué pour les non-auteurs dans le gabarit ne compte pas comme un correctif : il ne fait que cacher le lien, pas protéger la ressource elle-même
- Assurez-vous que l'on doit bien être connecté pour accéder au formulaire
