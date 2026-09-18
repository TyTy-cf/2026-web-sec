
# Le projet


- Application : https://localhost:8443/
- Connectez-vous sur https://localhost:8443/connexion avec n'importe quel compte prérempli, par exemple :
    - email : `carter.davis1@example.com`
    - mot de passe : `123`
      (tous les utilisateurs préremplis partagent ce même mot de passe)

- Il est recommandé de faire des branches pour les exercices, car  certains exercices nécessitent d'avoir un code vulnérable

## Rendu


- Poussez une branche nommée `votre-nom` contenant votre code
- Ajoutez un fichier `exercises/reponses.md` répondant aux questions de chaque exercice 


<a id="sommaire"></a>
## Sommaire


- [Exercice 1 — Security Misconfiguration (cookie de session)](#exercice-1)
- [Exercice 2 — Cross-Site Scripting (XSS)](#exercice-2)
- [Exercice 3 — Cross-Site Scripting (XSS)](#exercice-3)
- [Exercice 4 — Cross-Site Scripting (XSS)](#exercice-4)
- [Exercice 5 — Broken Access Control](#exercice-5)
- [Exercice 6 — Content Security Policy (CSP)](#exercice-6)
- [Exercice 7 — Cross-Site Request Forgery (CSRF)](#exercice-7)
- [Exercice 8 — Integrity of JWT](#exercice-8)
- [Exercice 9 — Login Throttling](#exercice-9)
- [Exercice 10 — Rate Limiter](#exercice-10)
- [Exercice 11 — Account Enumeration](#exercice-11)


<a id="exercice-1"></a>
# Exercice 1 — Security Misconfiguration (cookie de session)


## Pour commencer


- Le fichier `config/packages/framework.yaml` a été modifié pour ce sujet, il contient actuellement :
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
- Gardez ce bloc sous la main : si vous voulez rejouer l'exercice depuis le début, il suffit de le recopier tel quel
- Ce bloc a été modifié pour forcer des erreurs volontairement, dans le but de comprendre de quoi Symfony nous protège

## Questions


1. **Observer le cookie de session tel qu'il est posé aujourd'hui.** Connectez-vous à l'application, ouvrez les DevTools de votre navigateur (onglet Network), rechargez une page, et retrouvez la réponse qui pose le cookie de session. Listez tous les attributs présents sur ce cookie
2. **Observer la configuration par défaut de Symfony.** Allez voir ce que le framework configure par défaut pour le cookie de session dès qu'on active les sessions. Comparez le avec la config actuelle.
3. **Comprendre l'impact.** Pour chaque différence trouvée, cherchez : contre quel type d'attaque cet attribut protège-t-il normalement ? Qu'est-ce qu'un attaquant peut faire de plus si cet attribut est absent ou désactivé ? Vous pouvez tester en faisaint un `document.cookie` dans la console
4. **Restaurer une configuration saine.** Modifiez `config/packages/framework.yaml` pour retrouver les protections par défaut de Symfony (il existe plusieurs façons d'y arriver). Rechargez, reconnectez-vous si besoin, et regardez à nouveau l'en-tête de réponse qui pose le cookie dans les DevTools : qu'est-ce qui a changé ?
5. **Qualifier la faille.** À quelle catégorie de l'OWASP Top 10 cette faille correspond-elle ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-2"></a>
# Exercice 2 — Cross-Site Scripting (XSS)


## Pour commencer


- Une fois connecté, ouvrez n'importe quel sujet depuis la page d'accueil ; vous verrez le contenu du sujet, ses commentaires et un formulaire pour en publier un nouveau


## Questions


1. **Trouver le point d'injection.** Publiez un commentaire sur n'importe quel sujet. Essayez un contenu simple dans un premier temps, qui modifie la page du site, par exemple :
   ```html
   <script>document.title = 'XSS'</script>
   ```
   Rechargez la page du sujet. Le titre de l'onglet a-t-il changé ?

2. **Vérifier si le payload survit à votre propre session.** Ouvrez la même page de sujet dans une fenêtre de navigation privée (ou déconnectez-vous et reconnectez-vous avec un autre utilisateur). Le payload s'exécute-t-il toujours pour un visiteur qui ne l'a pas soumis lui-même ? Qu'est-ce que cela vous apprend sur l'endroit où elle réside entre deux requêtes ?

3. **Aller au-delà d'une simple popup : démontrer un impact réel.** Un simple `alert(1)` prouve l'exécution de code, mais ne démontre pas pourquoi cela est dangereux. Essayez d'illustrer une action effectuée *au nom de la victime* sans son consentement, par exemple un paylaod qui soumet un autre commentaire via `fetch()` lors du chargement de la page

4. **Mettre en place le correctif** : que faire pour éviter cette problématique ?

5. **Qualifier la faille.** Quel type de XSS vient-on de corriger ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-3"></a>
# Exercice 3 — Cross-Site Scripting (XSS)


## Pour commencer


- Une barre de recherche a été ajoutée dans l'en-tête du site, entre le lien "Reddit-Ish" et la partie connexion/déconnexion. Elle permet de rechercher un sujet par son titre
- Vous n'avez pas besoin d'être connecté pour utiliser cette fonctionnalité


## Questions


1. **Utiliser la fonctionnalité normalement.** Recherchez le titre (ou une partie du titre) d'un sujet existant et vérifiez que le ou les résultats s'affichent correctement
2. **Observer comment la recherche est affichée.** Après une recherche, votre terme de recherche reste affiché dans le champ de recherche de l'en-tête. Regardez le code source de la page (pas juste le rendu) autour de ce champ : comment votre terme y est-il inséré ?
3. **Essayer un payload évident.** Essayez de rechercher :
   ```html
   <script>document.title = 'XSS'</script>
   ```
   Que se passe-t-il ? Le titre de l'onglet change-t-il ? Regardez à nouveau le code source à l'endroit où votre terme de recherche apparaît : que sont devenus les caractères `<` et `>` ?
4. **Comprendre pourquoi ça ne marche pas, et trouver ce qui marche.** L'affichage échappe bien les caractères spéciaux... mais un caractère très commun, présent dans quasiment tous les payloads d'exemple, n'est lui jamais échappé nulle part. Repérez-le dans le code source du champ de recherche, et déduisez ce que cela permet d'injecter à cet endroit précis (indice : ce n'est plus une balise, mais un attribut HTML)
5. **Construire un payload qui s'exécute sans clic.** Une fois l'injection d'attribut trouvée, un simple `onclick` ne suffit pas à prouver l'impact puisqu'il faudrait que la victime clique dessus. Trouvez une combinaison d'attributs HTML permettant de déclencher du JavaScript automatiquement, dès le chargement de la page
6. **Tester si le payload survit en dehors du lien lui-même.** Ouvrez-le lien dans une autre fenêtre, sans rien resaisir. Le script s'exécute-t-il pour lui aussi ? Maintenant, effectuez une nouvelle recherche anodine, puis revenez à la page d'accueil sans repasser par ce lien précis : le payload est-il toujours là ? Comparez avec ce que vous aviez observé à l'exercice 2 : qu'est-ce qui diffère dans la façon dont chacun des deux payload persiste, ou non ?
7. **Imaginer un scénario d'attaque réel.** Un attaquant ne peut pas forcer une victime à taper quelque chose dans un champ de recherche. Comment pourrait-il malgré tout amener une victime à déclencher ce payload ?
8. **Mettre en place le correctif** pour éviter que cela ne se reproduise
9. **Qualifier la faille.** Quel type de faille XSS vient-on de corriger ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-4"></a>
# Exercice 4 — Cross-Site Scripting (XSS)


## Pour commencer


- Depuis la page d'accueil, cliquez sur le nom d'une catégorie (par exemple sous un sujet) pour arriver sur sa page de listing
- Connectez-vous, puis retournez sur cette page de catégorie : un encart **"Share this category with a friend"** apparaît, avec un lien à copier pour l'envoyer à quelqu'un


## Questions


1. **Utiliser la fonctionnalité normalement.** Copiez le lien de partage, ouvrez-le (par exemple dans une fenêtre privée) : la page vous accueille en mentionnant le nom de la personne qui l'a partagé
2. **Repérer où vit cette information dans l'URL.** Regardez attentivement l'URL du lien partagé. Quelle partie contient le nom de la personne ? Est-ce un paramètre classique (`?...`) ou autre chose ?
3. **Vérifier ce que voit le serveur.** Remplacez le nom dans l'URL par une valeur de test bien visible, rechargez la page, puis regardez le code source de la page (clic droit → *Afficher le code source*, ou une requête faite avec un outil en ligne de commande). Votre valeur de test apparaît-elle quelque part dans ce code source ? Que pouvez-vous en déduire sur qui traite réellement cette donnée : le serveur, ou autre chose ?
4. **Trouver où et comment l'information est affichée.** Cette fois, inspectez la page directement dans les outils de développement du navigateur (onglet *Éléments* / *Elements*, pas le code source). Retrouvez l'endroit où votre valeur de test a été insérée
5. **Essayer un premier payload évident.** Remplacez la valeur par :
   ```html
   <script>alert(document.domain)</script>
   ```
   Est-ce que ça s'exécute ? Si non, à votre avis pourquoi une balise `<script>` insérée de cette façon ne se déclenche-t-elle pas, contrairement à ce que vous aviez observé aux exercices précédents ?
6. **Trouver un vecteur qui fonctionne.** Sans utiliser de balise `<script>`, trouvez une balise HTML qui déclenche du JavaScript dès qu'elle est insérée dans la page (indice : un attribut de gestion d'évènement sur une balise qui échoue à charger une ressource, ou qui se déclenche automatiquement)
7. **Confirmer la nature de la faille.** En vous basant sur ce que vous avez observé à l'étape 3, comment qualifieriez-vous ce type de XSS ? En quoi est-il différent des exercices 2 et 3, alors que le résultat (exécution de JavaScript arbitraire) est similaire ?
8. **Imaginer un scénario d'attaque réel.** Comment un attaquant pourrait-il pousser une victime à cliquer sur un lien contenant ce payload ?
9. **Mettre en place le correctif** pour éviter que cela ne se reproduise
10. **Qualifier la faille.** Ce correctif peut-il être fait côté serveur (Symfony/Twig) ? Pourquoi ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-5"></a>
# Exercice 5 — Broken Access Control


## Pour commencer


- Connectez-vous avec ce compte pour voir le bouton `Edit`, sur le premier topic :
    - `isabella.young62@example.com` / `123`
      (tous les utilisateurs préremplis partagent ce même mot de passe)
- Un bouton **Edit** a été ajouté sur la page d'un sujet, visible uniquement par son auteur, qui mène vers un formulaire de modification du titre et du contenu

## Questions


1. **Utiliser la fonctionnalité normalement.** Avec le premier compte, ouvrez un sujet dont il est l'auteur, cliquez sur **Edit**, modifiez le titre ou le contenu, enregistrez. Vérifiez que la modification est bien prise en compte

2. **Accéder à une autre ressource.** Toujours connecté avec le premier compte, essayez d'atteindre le formulaire d'édition d'un sujet dont il n'est *pas* l'auteur (passer un autre ID) en construisant l'URL vous-même plutôt qu'en cliquant sur un lien de l'interface. Vous accéder bien à la ressource ?

3. **Confirmer l'impact.** Si le formulaire s'affiche, allez jusqu'au bout : soumettez une modification et vérifiez, avec le second compte, que le contenu de son sujet a bien changé

4. **Reproduire avec un autre couple compte/sujet** pour vous assurer que ce n'est pas un cas particulier lié à ce sujet précis

5. **Que se passe t'il avec un utilisateur non connecté ?** Essayez d'accéder au formulaire de modification sans être connecté

6. **Mettre en place le correctif.** Empêchez un utilisateur d'éditer un sujet qui ne lui appartient pas, et assurez-vous qu'il faut être connecté pour accéder au formulaire. Le bouton **Edit** déjà masqué pour les non-auteurs dans le gabarit ne compte pas comme un correctif : il ne fait que cacher le lien, pas protéger la ressource elle-même


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-6"></a>
# Exercice 6 — Content Security Policy (CSP)


## Pour commencer


- Aucune CSP n'est configurée sur l'application pour l'instant
- Gardez sous la main les charges utiles des exercices 2, 3 et 4 : vous allez les rejouer plus tard dans cet exercice (repartez de la branche `main`pour cet exercice, vous devez avoir une branche avec des failles)
- Vous n'avez pas besoin d'avoir corrigé les exercices précédents pour faire celui-ci


## Questions


1. **Comprendre l'objectif.** Qu'est-ce qu'une Content Security Policy, et en quoi peut-elle limiter l'impact d'une faille XSS, même sans corriger le bug qui permet l'injection ?
2. **Trouver où la configurer.** Cherchez comment on ajoute un en-tête `Content-Security-Policy` sur les réponses HTTP de cette stack (Symfony + Caddy). Il existe plusieurs façons de faire, à vous de choisir celle qui vous convient
3. **Mettre en place une politique.** Configurez une politique plutôt stricte (par exemple en n'autorisant les scripts et styles qu'en provenance du même site). Vérifiez, dans les outils de développement du navigateur (onglet réseau), que l'en-tête est bien présent sur les réponses
4. **Rejouer les anciennes charges utiles.** Reprenez les payloads des exercices 2 (commentaire), 3 (recherche) et 4 (partage de catégorie), sans corriger le code vulnérable sous-jacent. Que se passe-t-il maintenant ? Regardez la console du navigateur : un message apparaît-il ?
5. **Vérifier les dégâts collatéraux.** Une politique stricte peut casser des fonctionnalités légitimes du site qui reposaient sur du JavaScript inline. Explorez le site à la recherche d'une fonctionnalité qui ne marche plus. Trouvez pourquoi, et corrigez-la sans réintroduire de faille
6. **Prendre du recul.** La CSP a-t-elle corrigé une seule des failles des exercices précédents ? Si un attaquant trouve un moyen de contourner votre politique (par exemple parce qu'elle autorise une source qu'il contrôle), que se passe-t-il ?
7. **Qualifier l'apport de la CSP.** La CSP remplace-t-elle les correctifs des exercices précédents, ou les complète-t-elle ? Justifiez


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-7"></a>
# Exercice 7 — Cross-Site Request Forgery (CSRF)


## Pour commencer


- Ouvrez un sujet où vous avez posté un commentaire (ou postez-en un) : un bouton **Supprimer** apparaît sous vos propres commentaires
- Gardez sous la main l'identifiant (`id`) d'un commentaire que vous êtes prêt à voir disparaître pendant cet exercice


## Questions


1. **Utiliser la fonctionnalité normalement.** Avant de cliquer sur **Supprimer**, ouvrez l'onglet Réseau (Network) des DevTools. Cliquez, puis regardez précisément la requête envoyée : méthode HTTP, présence ou non d'un paramètre de type jeton
2. **Comparer avec le formulaire de publication d'un commentaire.** Regardez le code source de la page autour du formulaire qui permet de publier un nouveau commentaire (pas le bouton Supprimer). Quelle différence structurelle voyez-vous avec le lien **Supprimer** ?
3. **Construire une page piège.** Créez, en dehors du projet, un fichier HTML minimal (pas besoin de l'héberger, un simple fichier ouvert en local dans le navigateur suffit) qui déclenche la suppression d'un commentaire précis dès son chargement, sans aucun clic de la victime
4. **Déclencher l'attaque.** Toujours connecté à Reddit-Ish dans le même navigateur, ouvrez votre page piège dans un nouvel onglet. Que devient le commentaire ciblé ?
5. **Vérifier ce qui est réellement nécessaire pour que ça marche.** Déconnectez-vous de Reddit-Ish, puis rouvrez la page piège. Le commentaire est-il supprimé cette fois ? Qu'est-ce que cela vous apprend sur ce dont dépend l'attaque ?
6. **Identifier précisément ce qui manque.** En comparant avec l'étape 2, qu'est-ce qu'un vrai formulaire Symfony (`FormType`) aurait fourni automatiquement, et que cette route n'a pas ?
7. **Imaginer un scénario de diffusion réel.** Un attaquant ne peut pas ouvrir cette page à la place de la victime. Comment pourrait-il malgré tout l'amener à l'ouvrir pendant qu'elle est connectée ?
8. **Mettre en place le correctif** pour empêcher que cette suppression puisse être déclenchée depuis une page extérieure. Cette route ne passe pas par un formulaire Symfony (`FormType`) : vous ne pouvez donc pas compter sur sa protection CSRF automatique, il faut la mettre en œuvre vous-même
9. **Revalider l'attaque.** Repassez par votre page piège une fois le correctif en place : que se passe-t-il désormais ?
10. **Qualifier la faille.** Cette faille correspond à quelle catégorie de l'OWASP Top 10 aujourd'hui ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-8"></a>
# Exercice 8 — Integrity of JWT


## Pour commencer


- L'application expose une API en `/api`, documentée sur `/api/docs`
- Trois routes existent : 
  - `POST /api/login_check` (authentification > récupère le token)
  - `GET /api/topic` (public)
  - `GET /api/user/me` (nécessite d'être connecté)
- Connectez-vous via `/api/login_check` avec un des comptes préremplis. Soit par Postman/Insomnia ou via le bouton **Authorize** de l'interface `/api`) et récupérez le jeton renvoyé


## Questions


1. **Décoder le jeton.** Un JWT est composé de trois parties séparées par des points. Décodez-les, par exemple sur `jwt.io`. Qu'obtenez-vous ?
2. **Lister ce qui est exposé.** Pour chaque information trouvée dans le payload décodé, demandez-vous : est-ce une donnée que le jeton révèle, potentiellement dangereuse si elle tombe dans de mauvaises mains ?
3. **Tester ce que la signature protège réellement.** Modifiez une valeur dans le payload décodé (par exemple le rôle), regénérez un jeton avec cette modification, et présentez-le à `GET /api/user/me`. Que se passe-t-il ?
4. **Évaluer l'impact concret.** Reprenez la liste de l'étape 2. Laquelle de ces informations, si elle était interceptée, causerait un dommage allant au-delà de Reddit-Ish lui-même ?
5. **Comparer avec la session du site principal.** Le site utilise par ailleurs un cookie de session (`PHPSESSID`) pour l'authentification classique. Si vous interceptiez ce cookie et le lisiez tel quel, apprendriez-vous quoi que ce soit sur l'utilisateur ? Qu'est-ce qui différencie fondamentalement un identifiant de session d'un JWT ?
6. **Mettre en place le correctif.** Le jeton doit conserver un identifiant permettant de savoir quel utilisateur il représente : vous ne pouvez donc pas simplement supprimer ce qu'il contient. Quelle valeur proposez-vous pour remplacer ce qui y figure actuellement, qui ne soit ni une donnée personnelle, ni devinable, tout en restant propre à chaque utilisateur ? Implémentez ce correctif
7. **Reconsidérer le rôle.** Le rôle de l'utilisateur doit-il forcément apparaître dans le jeton pour que l'application fonctionne ? Justifiez
8. **Qualifier la faille.** Quelle propriété avez-vous réellement corrigée : l'intégrité du jeton, ou autre chose ? Une phrase claire est attendue ici


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-9"></a>
# Exercice 9 — Login Throttling


## Pour commencer


- Reddit-Ish propose deux points d'entrée d'authentification distincts : `/connexion` (formulaire classique, session) et `POST /api/login_check` (API, JWT — voir exercice 8)
- Rien ne limite aujourd'hui le nombre de tentatives de connexion, réussies ou non, sur l'une ou l'autre de ces routes
- Gardez sous la main un compte prérempli valide (par exemple `carter.davis1@example.com` / `123`) pour vérifier, après votre correctif, que la connexion légitime fonctionne toujours


## Questions


1. **Constater l'absence de protection.** Tentez plusieurs connexions successives avec un mauvais mot de passe sur `/connexion` (une vingtaine de tentatives, à la main ou via un petit script/`curl` en boucle). Que se passe-t-il après la 5e tentative ? La 10e ? La 20e ? Un ralentissement, un blocage, un message différent apparaissent-ils à un moment ?
2. **Refaire le même test sur l'API.** Bouclez sur `POST /api/login_check` avec un mauvais mot de passe (via `curl`, Postman, ou le bouton **Authorize** de `/api/docs`). Comparez avec ce que vous avez observé à l'étape 1
3. **Comprendre le risque.** Pourquoi l'absence de toute limite sur ces deux routes pose problème ? Quel(s) type(s) d'attaque cela facilite-t-il ? Combien de temps faudrait-il, en théorie, à un script pour tester 10 000 mots de passe sur un compte connu si rien ne le ralentit ?
4. **Chercher la fonctionnalité Symfony adaptée.** Sans écrire de code tout de suite, cherchez dans la documentation Symfony la protection native contre le brute-force sur les routes d'authentification
5. **Mettre en place la protection sur les deux routes.** Configurez la protection sur le ou les firewalls concernés. Choisissez un nombre de tentatives et une fenêtre de temps qui vous semblent raisonnables pour un site public
6. **Vérifier le blocage.** Répétez les étapes 1 et 2. Après combien de tentatives échouées êtes-vous bloqué ? Quel code HTTP et quel message obtenez-vous désormais ? Le comportement est-il le même sur les deux routes ?
7. **Vérifier qu'un utilisateur légitime n'est pas pénalisé inutilement.** Avec un compte valide, un mot de passe correct dès la première tentative est-il toujours accepté ? Et si vous vous trompez une ou deux fois avant de retaper le bon mot de passe (dans la limite du seuil choisi) ?
8. **Rejouer une tentative de connexion réussie juste après un blocage.** Une fois le seuil atteint pour un compte donné, tentez de vous reconnecter à ce même compte avec le **bon** mot de passe, toujours depuis la même machine. Est-ce accepté ? Qu'est-ce que ça vous apprend sur *à quel moment* le blocage agit par rapport à la vérification du mot de passe ?
9. **Documenter le correctif.** Quelle configuration avez-vous utilisée, et pour quel(s) firewall(s) ?
10. **Qualifier la faille.** Cette faille correspond à quelle catégorie de l'OWASP Top 10 ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-10"></a>
# Exercice 10 — Rate Limiter


## Pour commencer


- Un formulaire d'inscription existe sur la route `/inscription`
- Deux routes publiques n'ont aujourd'hui aucune limite de requêtes : `POST /inscription` et `GET /api/topic`
- Ces deux routes représentent deux cas différents : une action précise dans une application classique (le formulaire), et toute une famille de routes d'une API (`/api/topic`). Les deux se protègent avec le même composant, mais pas de la même façon


## Questions


1. **Constater l'absence de limite sur `/inscription`.** Soumettez le formulaire en boucle (script, `curl`, ou juste plusieurs clics rapides) avec des emails différents à chaque fois. Combien de comptes créez-vous avant qu'un blocage n'apparaisse ?
2. **Constater l'absence de limite sur l'API.** Bouclez sur `GET /api/topic`. Même constat ?
3. **Installer le composant Rate Limiter de Symfony** s'il n'est pas déjà présent (`composer show symfony/rate-limiter`)
4. **Protéger `/inscription`.** Limitez le nombre de soumissions par adresse IP
5. **Protéger `GET /api/topic`.** Cette fois, la protection doit s'appliquer à une route (ou une famille de routes), pas à une seule action précise dans un contrôleur, il faut trouver un moyen...
6. **Vérifier les deux protections.** Répétez les étapes 1 et 2. Quel code HTTP obtenez-vous une fois la limite dépassée ? Une utilisation normale (quelques requêtes) fonctionne-t-elle toujours ?


[⬆ Retour au sommaire](#sommaire)


<a id="exercice-11"></a>
# Exercice 11 — Account Enumeration


## Pour commencer


- Un formulaire d'inscription existe sur `/inscription` (voir exercice 10)
- Préparez deux adresses e-mail : une déjà utilisée par un compte prérempli (par exemple `carter.davis1@example.com`), et une dont vous êtes certain qu'elle n'a jamais servi sur ce site


## Questions


1. **Utiliser la fonctionnalité normalement.** Inscrivez-vous avec une adresse e-mail neuve, un pseudo et un mot de passe. Que se passe-t-il ?
2. **Réessayer avec une adresse déjà utilisée.** Remplissez à nouveau le formulaire, cette fois avec l'adresse e-mail d'un compte prérempli existant. Que se passe-t-il ? Comparez précisément le message obtenu avec celui de l'étape 1 : que change-t-il, à l'écran comme dans le code source de la page ?
3. **Comprendre ce que cette différence révèle.** Sans jamais vous connecter, rien qu'en observant la réponse du formulaire, comment pourriez-vous déterminer si une adresse e-mail donnée possède déjà un compte sur Reddit-Ish ?
4. **Mesurer l'ampleur du problème.** Imaginez que vous disposiez d'une liste de plusieurs milliers d'adresses e-mail (par exemple issue d'une fuite de données d'un autre site). Qu'apprendriez-vous en les soumettant une par une à ce formulaire ? En quoi est-ce dangereux pour les personnes concernées, même sans jamais obtenir leur mot de passe ?
5. **Faire le lien avec l'exercice précédent.** Le rate limiter mis en place à l'exercice 10 sur `/inscription` empêche-t-il ce scénario ? Justifiez votre réponse
6. **Trouver l'origine technique.** Regardez `src/Entity/User.php` : quel attribut de validation Symfony est responsable du message différent obtenu à l'étape 2 ?
7. **Mettre en place le correctif.** Modifiez le comportement du formulaire d'inscription pour qu'il réponde exactement de la même façon (message affiché, code HTTP, redirection), que l'adresse e-mail soit déjà utilisée ou non. Attention : la contrainte d'unicité sur `user.email` (voir le dictionnaire de données dans `readme.md`) doit être conservée en base — il ne s'agit pas de permettre la création de deux comptes avec le même e-mail
8. **Revalider.** Répétez les étapes 1 et 2 une fois le correctif en place. Les deux réponses sont-elles désormais indiscernables l'une de l'autre ?
9. **Qualifier la faille.** À quelle catégorie de l'OWASP Top 10 cette faille correspond-elle ? À quel autre exercice déjà réalisé peut-on la rattacher, et pour quelle raison ?


[⬆ Retour au sommaire](#sommaire)
