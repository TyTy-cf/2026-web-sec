
# Le projet


- Application : https://localhost:8443/
- Connectez-vous sur https://localhost:8443/connexion avec n'importe quel compte prérempli, par exemple :
    - email : `carter.davis1@example.com`
    - mot de passe : `123`
      (tous les utilisateurs préremplis partagent ce même mot de passe)

- Il est recommandé de faire des branches pour les exercices, car  certains exercices nécessitent d'avoir un code vulnérable

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

3. **Aller au-delà d'une simple popup : démontrer un impact réel.** Un simple `alert(1)` prouve l'exécution de code, mais ne démontre pas pourquoi cela est dangereux. Essayez d'illustrer une action effectuée *au nom de la victime* sans son consentement, par exemple une charge utile qui soumet silencieusement un autre commentaire via `fetch()` lors du chargement de la page


## Vous devez avoir fait


- Une balise `<script>` dans un commentaire et obtenu son exécution lors d'un chargement standard de page (sans astuce dans les outils de développement)
- Vérifier que cela se déclenche pour une session ou un utilisateur *différent*, prouvant ainsi qu'elle est stockée
- Un script effectuant une action au nom de la victime (pas uniquement un `alert()`)


## Correctif


- Mettez en place le correctif pour éviter que cela ne se reproduise
- Quel type de XSS vient-on de corriger ?


# Exercice 2 — Broken Access Control


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


# Exercice 3 — Cross-Site Scripting (XSS


## Pour commencer


- Une barre de recherche a été ajoutée dans l'en-tête du site, entre le lien "Reddit-Ish" et la partie connexion/déconnexion. Elle permet de rechercher un sujet par son titre
- Vous n'avez pas besoin d'être connecté pour utiliser cette fonctionnalité


## Mission


1. **Utiliser la fonctionnalité normalement.** Recherchez le titre (ou une partie du titre) d'un sujet existant et vérifiez que le ou les résultats s'affichent correctement
2. **Observer comment la recherche est affichée.** Après une recherche, votre terme de recherche reste affiché dans le champ de recherche de l'en-tête. Regardez le code source de la page (pas juste le rendu) autour de ce champ : comment votre terme y est-il inséré ?
3. **Essayer une première charge utile évidente.** Essayez de rechercher :
   ```html
   <script>document.title = 'XSS'</script>
   ```
   Que se passe-t-il ? Le titre de l'onglet change-t-il ? Regardez à nouveau le code source à l'endroit où votre terme de recherche apparaît : que sont devenus les caractères `<` et `>` ?
4. **Comprendre pourquoi ça ne marche pas, et trouver ce qui marche.** L'affichage échappe bien les caractères spéciaux... mais un caractère très commun, présent dans quasiment tous les payloads d'exemple, n'est lui jamais échappé nulle part. Repérez-le dans le code source du champ de recherche, et déduisez ce que cela permet d'injecter à cet endroit précis (indice : ce n'est plus une balise, mais un attribut HTML)
5. **Construire un payload qui s'exécute sans clic.** Une fois l'injection d'attribut trouvée, un simple `onclick` ne suffit pas à prouver l'impact puisqu'il faudrait que la victime clique dessus. Trouvez une combinaison d'attributs HTML permettant de déclencher du JavaScript automatiquement, dès le chargement de la page (par exemple en rendant le champ automatiquement focus)
6. **Confirmer qu'il est réfléchi et non stocké.** Envoyez le lien contenant votre charge utile à quelqu'un d'autre (ou ouvrez-le dans une autre fenêtre, sans rien resaisir). Le script s'exécute-t-il pour lui aussi ? Maintenant, effectuez une nouvelle recherche anodine, puis revenez à la page d'accueil sans repasser par ce lien précis : la charge utile est-elle toujours là ? Comparez avec ce que vous aviez observé à l'exercice 1
7. **Imaginer un scénario d'attaque réel.** Un attaquant ne peut pas forcer une victime à taper quelque chose dans un champ de recherche. Comment pourrait-il malgré tout amener une victime à déclencher cette charge utile ?


## Vous devez avoir fait


- Constaté que le `<script>` seul ne s'exécute pas, et compris pourquoi (les caractères `<` et `>` sont échappés)
- Identifié le caractère qui, lui, n'est jamais échappé, et ce qu'il permet à cet endroit du code
- Obtenu l'exécution de JavaScript, sans interaction de la victime, via le paramètre `q` de l'URL, sans qu'aucune balise `<script>` n'apparaisse dans votre charge utile finale
- Décrit comment cette charge utile pourrait concrètement être livrée à une victime (lien, redirection, etc.)


## Correctif


- Mettez en place le correctif pour éviter que cela ne se reproduise
- Quel type de faille XSS vient-on de corriger ?


# Exercice 4 — DOM-based XSS


## Pour commencer


- Depuis la page d'accueil, cliquez sur le nom d'une catégorie (par exemple sous un sujet) pour arriver sur sa page de listing
- Connectez-vous, puis retournez sur cette page de catégorie : un encart **"Share this category with a friend"** apparaît, avec un lien à copier pour l'envoyer à quelqu'un


## Mission


1. **Utiliser la fonctionnalité normalement.** Copiez le lien de partage, ouvrez-le (par exemple dans une fenêtre privée) : la page vous accueille en mentionnant le nom de la personne qui l'a partagé
2. **Repérer où vit cette information dans l'URL.** Regardez attentivement l'URL du lien partagé. Quelle partie contient le nom de la personne ? Est-ce un paramètre classique (`?...`) ou autre chose ?
3. **Vérifier ce que voit le serveur.** Remplacez le nom dans l'URL par une valeur de test bien visible, rechargez la page, puis regardez le code source de la page (clic droit → *Afficher le code source*, ou une requête faite avec un outil en ligne de commande). Votre valeur de test apparaît-elle quelque part dans ce code source ? Que pouvez-vous en déduire sur qui traite réellement cette donnée : le serveur, ou autre chose ?
4. **Trouver où et comment l'information est affichée.** Cette fois, inspectez la page directement dans les outils de développement du navigateur (onglet *Éléments* / *Elements*, pas le code source). Retrouvez l'endroit où votre valeur de test a été insérée
5. **Essayer une première charge utile évidente.** Remplacez la valeur par :
   ```html
   <script>alert(document.domain)</script>
   ```
   Est-ce que ça s'exécute ? Si non, à votre avis pourquoi une balise `<script>` insérée de cette façon ne se déclenche-t-elle pas, contrairement à ce que vous aviez observé aux exercices précédents ?
6. **Trouver un vecteur qui fonctionne.** Sans utiliser de balise `<script>`, trouvez une balise HTML qui déclenche du JavaScript dès qu'elle est insérée dans la page (indice : un attribut de gestion d'évènement sur une balise qui échoue à charger une ressource, ou qui se déclenche automatiquement)
7. **Confirmer la nature de la faille.** En vous basant sur ce que vous avez observé à l'étape 3, comment qualifieriez-vous ce type de XSS ? En quoi est-il différent des exercices 1 et 3, alors que le résultat (exécution de JavaScript arbitraire) est similaire ?
8. **Imaginer un scénario d'attaque réel.** Comment un attaquant pourrait-il pousser une victime à cliquer sur un lien contenant cette charge utile ?


## Vous devez avoir fait


- Identifié que le nom partagé transite par une partie de l'URL qui n'est jamais envoyée au serveur
- Constaté qu'une charge utile placée à cet endroit n'apparaît jamais dans le code source ni dans aucune requête réseau, alors qu'elle s'exécute bien dans le navigateur
- Obtenu l'exécution de JavaScript sans utiliser de balise `<script>`
- Expliqué pourquoi ce type de XSS ne peut pas être détecté ni corrigé côté serveur, contrairement aux exercices 1 et 3


## Correctif


- Mettez en place le correctif pour éviter que cela ne se reproduise
- Ce correctif peut-il être fait côté serveur (Symfony/Twig) ? Pourquoi ?


# Exercice 5 — Content Security Policy (CSP)


## Pour commencer


- Aucune CSP n'est configurée sur l'application pour l'instant
- Gardez sous la main les charges utiles des exercices 1, 3 et 4 : vous allez les rejouer plus tard dans cet exercice
- Vous n'avez pas besoin d'avoir corrigé les exercices précédents pour faire celui-ci


## Mission


1. **Comprendre l'objectif.** Qu'est-ce qu'une Content Security Policy, et en quoi peut-elle limiter l'impact d'une faille XSS, même sans corriger le bug qui permet l'injection ?
2. **Trouver où la configurer.** On pourrait penser que ça se passe dans `security.yaml`, vu son nom... regardez ce que ce fichier gère réellement dans une application Symfony. Cherchez comment on ajoute un en-tête `Content-Security-Policy` sur les réponses HTTP de cette stack (Symfony + Caddy). Il existe plusieurs façons de faire, à vous de choisir celle qui vous convient
3. **Mettre en place une politique.** Configurez une politique plutôt stricte (par exemple en n'autorisant les scripts et styles qu'en provenance du même site). Vérifiez, dans les outils de développement du navigateur (onglet réseau), que l'en-tête est bien présent sur les réponses
4. **Rejouer les anciennes charges utiles.** Reprenez les payloads des exercices 1 (commentaire), 3 (recherche) et 4 (partage de catégorie), sans corriger le code vulnérable sous-jacent. Que se passe-t-il maintenant ? Regardez la console du navigateur : un message apparaît-il ?
5. **Vérifier les dégâts collatéraux.** Une politique stricte peut casser des fonctionnalités légitimes du site qui reposaient sur du JavaScript inline. Explorez le site à la recherche d'une fonctionnalité qui ne marche plus. Trouvez pourquoi, et corrigez-la sans réintroduire de faille
6. **Prendre du recul.** La CSP a-t-elle corrigé une seule des failles des exercices précédents ? Si un attaquant trouve un moyen de contourner votre politique (par exemple parce qu'elle autorise une source qu'il contrôle), que se passe-t-il ?


## Vous devez avoir fait


- Expliqué pourquoi `security.yaml` n'est pas le bon endroit pour ça, et où vous avez effectivement placé la configuration
- Mis en place une CSP dont vous pouvez prouver la présence (en-tête visible dans les réponses HTTP)
- Constaté que les charges utiles des exercices 1, 3 et 4 sont neutralisées par votre politique, sans avoir touché au code vulnérable
- Trouvé et corrigé une fonctionnalité légitime cassée par votre politique


## Correctif


- Il n'y a pas de correctif à proprement parler ici, la CSP *est* le livrable de cet exercice
- La CSP remplace-t-elle les correctifs des exercices précédents, ou les complète-t-elle ? Justifiez
