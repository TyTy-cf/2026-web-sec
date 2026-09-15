
# Project context


This project serves as a training exercise for a web security course.

It's contains bad practices on purpose, so the trainees will have to fixes them.

The project is named "Reddit-Ish", based on "Reddit". A forum-like project, where user posts topic, post/edit comments, search for topic etc. 


## 1. Project stack


- Symfony 7.4
- PHP 8.2
- Node 22


### 1.1 Docker


The app has docker, with a Makefile to help.


There is five containers :
- caddy-1 : local server
- phpmyadmin : db access
- node-1 : for node, npm commands
- php-1 : for php, using symfony and php commands
- mariadb-1 : db using mariadb


## 2. Existing features


Every "features" include a bad practices on purposes, which exists in OWASP, it also refers to an exercise, written in directory `exercises/readme.md`.
Each time a "feature" is developed, an exercise has to be written, without giving too much information on what has to be fixed.
You can look at the previous exercises for inspiration, all exercises have to be written in english.


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


