
# Project context


This project serves as a training exercise for a web security course.

It's contains bad practices on purpose, so the trainees will have to fixes them.

The project is named "Reddit-Ish", based on "Reddit". A forum-like project, where user posts topic, post/edit comments, search for topic etc. 


## 1. Project stack


- Symfony 7.4
- PHP 8.2
- Node 22


### 1.1. Docker


The app has docker, with a Makefile to help.


There is five containers :
- caddy-1 : local server
- phpmyadmin : db access
- node-1 : for node, npm commands
- php-1 : for php, using symfony and php commands
- mariadb-1 : db using mariadb


### 1.2. Code convention


- title, label, route path are written in French
- code is written in English, variables, route name, function and class
- Use translation file for this, only in `messages.fr.yaml`


## 2. Existing features


Every "features" include a bad practices on purposes, which exists in OWASP, it also refers to an exercise, written in directory `exercises/readme.md`.
Each time a "feature" is developed, an exercise has to be written, without giving too much information on what has to be fixed.
You can look at the previous exercises for inspiration, all exercises have to be written in english.
Update the `exercise/correction.md` for the expected fix for each exercise.


### 2.1 Persistent XSS


- [x] Usages of Twig filter `|raw` for `review.content` on purpose

The files impacted are `TopicController.php`, specially `show` function and `front/topic/show.html.twig`


### 2.2 Broken Access Control


Any user can modify the topic of anyone, no checking on the owner are made.

- [x] Available `Edit button` only for the current user, but no check are made on the page itself

The files impacted are `TopicController.php`, specially `edit` function, and `front/topic/show.html.twig`


### 2.3 Reflected XSS


A search bar was added in the header (centered, between the "home" link and the login/logout part), submitted with GET on a `q` parameter. The search input repopulates the last query for usability.

- [x] The repopulated value is output as an unquoted HTML attribute on purpose: `value={{ app.request.query.get('q') }}` (no surrounding quotes)

Twig's default autoescaping still HTML-encodes `<`, `>`, `&`, `"`, `'`, but not spaces, so with the attribute unquoted an attacker can break out of `value=` and inject new attributes (e.g. `?q=x onfocus=alert(1) autofocus=x`) even though the variable is technically "escaped". No `|raw` and no `{% autoescape false %}` anywhere — the bug is purely the missing quotes, which is the realistic version of this mistake.

The files impacted are `TopicController.php` (`search` function), `TopicRepository.php` (`search` method), `templates/front/common/_header.html.twig` (the vulnerable line) and `templates/front/topic/search.html.twig`


### 2.4 DOM-based XSS


A category page was added, listing the topics of a given category, reachable from the category name link on the home page. Logged-in users get a "Share this category with a friend" box containing a link with `#ref=<their nickname>` appended, so the visitor lands on a page that greets them by name.

- [x] `assets/scripts/app.ts` reads `location.hash` client-side and writes the `ref` value into `#ref-banner` via `innerHTML` without any sanitization, on purpose

The payload never touches the server: the `ref` value lives only in the URL fragment, which browsers never send in the HTTP request, so the server-rendered HTML (and any server-side fix) is always clean. The vulnerability is purely client-side.

The files impacted are `CategoryController.php`, `TopicRepository.php` (`findByCategory` method), `templates/front/category/show.html.twig`, `templates/front/home/index.html.twig` (category link) and `assets/scripts/app.ts` (the vulnerable line)


### 2.5 Content Security Policy


Unlike the previous features, this one is a missing hardening measure rather than a bad practice: no CSP header is configured anywhere in the app.

- [x] No `Content-Security-Policy` header on purpose, and no code was written for this exercise

Trainees have to research where a CSP actually belongs (not `security.yaml`, despite the name — that file only handles Symfony's authentication/authorization firewall) and implement it themselves (e.g. NelmioSecurityBundle config, a header set in the `Caddyfile`, or a custom Symfony listener), then verify it mitigates the payloads from exercises 2.1, 2.3 and 2.4 without the underlying bugs being fixed. Do not implement this in the app; the exercise is precisely to have them find and add the solution.

