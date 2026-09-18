
# Project context


This project serves as a training exercise for a web security course, the trainees are Symfony developers, experienced from 11 years to 1, that want to learn more about security in their project, how it's work and best practices.

It's contains bad practices on purpose, so the trainees will have to fixes them.

The project is named "Reddit-Ish", based on "Reddit". A forum-like project, where user posts topic, post/edit comments, search for topic etc. 


## 1. Project stack


- Symfony 7.4
- PHP 8.2
- Node 22


### 1.1. Docker


The app has docker, with a Makefile to help.


There is five containers :
- `caddy-1` : local server
- `phpmyadmin` : db access
- `node-1` : for node, npm commands
- `php-1` : for php, using symfony and php commands
- `mariadb-1` : db using mariadb


### 1.2. Code convention


- title, label, route path are written in French
- code is written in English, variables, route name, function and class
- Use translation file for this, only in `messages.fr.yaml`
- Every route called by the API should start with /api/


## 2. Existing features


Every "features" include a bad practices on purposes, which exists in OWASP, it also refers to an exercise, written in directory `exercises/readme.md`.
Each time a "feature" is developed, an exercise has to be written, without giving too much information on what has to be fixed.
You can look at the previous exercises for inspiration, all exercises have to be written in english.
When an exercise is added into `exercise/readme.md`, update the `exercise/correction.md` for the expected fix for each exercise, also the summary of each file.


### 2.1 Security Misconfiguration — Session cookie


Symfony sets secure defaults for the session cookie (`Secure` when the request is served over HTTPS, `HttpOnly`, `SameSite=Lax`) as soon as sessions are enabled, with no extra configuration needed. This exercise breaks those defaults so trainees have to rediscover them by comparing the `Set-Cookie` header in DevTools against what the framework does out of the box.

- [x] `config/packages/framework.yaml` explicitly overrides the defaults with `cookie_secure: false` and `cookie_samesite: null`, on purpose. `cookie_httponly: true` is deliberately left untouched, so the `HttpOnly` mitigation referenced in exercise 2.2 (Persistent XSS correction, about `document.cookie` being empty) still holds

The files impacted are `config/packages/framework.yaml` only, no PHP/Twig code involved.


### 2.2 Persistent XSS


- [x] Usages of Twig filter `|raw` for `review.content` on purpose

The files impacted are `TopicController.php`, specially `show` function and `front/topic/show.html.twig`


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


### 2.5 Broken Access Control


Any user can modify the topic of anyone, no checking on the owner are made.

- [x] Available `Edit button` only for the current user, but no check are made on the page itself

The files impacted are `TopicController.php`, specially `edit` function, and `front/topic/show.html.twig`


### 2.6 Content Security Policy


Unlike the previous features, this one is a missing hardening measure rather than a bad practice: no CSP header is configured anywhere in the app.

- [x] No `Content-Security-Policy` header on purpose, and no code was written for this exercise

Trainees have to research where a CSP actually belongs (not `security.yaml`, despite the name — that file only handles Symfony's authentication/authorization firewall) and implement it themselves (e.g. NelmioSecurityBundle config, a header set in the `Caddyfile`, or a custom Symfony listener), then verify it mitigates the payloads from exercises 2.2, 2.3 and 2.4 (the three XSS flavours) without the underlying bugs being fixed. Do not implement this in the app; the exercise is precisely to have them find and add the solution.


### 2.7 Cross-Site Request Forgery (CSRF)


`CommentController::delete` — `GET /commentaires/{id}/supprimer` (`app_comment_delete`). The comment-submission form goes through a Symfony `FormType`, which embeds and verifies a CSRF token automatically; this route was hand-built as a bare `<a href>` link instead, so it never goes through that mechanism at all.

- [x] Route deliberately kept as `methods: ['GET']` with no CSRF token check, so it can be triggered from a bare link/image tag on any external page while a victim is logged in, on purpose

The files impacted are `CommentController.php` (`delete` function) and `templates/front/topic/show.html.twig` (the delete link).

Note: the route also has no login check and no ownership check at all — anyone who knows/guesses a comment ID can delete it, logged in or not. That's a separate Broken Access Control issue, deliberately left unfixed by this exercise (its fix only covers CSRF) and reserved for a later exercise, see §3.1.


### 2.8 Integrity of JWT (Sensitive Data Exposure via JWT payload)


The app exposes an API under `/api` (API Platform + LexikJWTAuthenticationBundle), with `POST /api/login_check`, `GET /api/topic` (public) and `GET /api/user/me` (authenticated). This one is also a missing-hardening-style exercise rather than an injected bug: nothing was written to make the JWT payload leak data on purpose, it's simply what the bundle does out of the box when left unconfigured.

- [x] `User::getUserIdentifier()` (`src/Entity/User.php`) returns the email, and the `app_user_provider` in `security.yaml` is keyed on `property: email` — so LexikJWTAuthenticationBundle's default payload puts the user's email in clear in the `username` claim, plus their `roles`, with nothing else changed or added

The files impacted are `config/packages/lexik_jwt_authentication.yaml`, `config/packages/security.yaml` and `src/Entity/User.php` (all at their default/unmodified state — no PHP written for this exercise beyond what API Platform + JWT scaffolding already needed). The fix (personalizing the JWT payload via `lexik_jwt_authentication.on_jwt_created` and a second `id`-keyed provider for the `api` firewall) is left entirely to the trainee.


### 2.9 Login Throttling (Identification and Authentication Failures)


Two authentication entry points exist: `/connexion` (`main` firewall, `form_login`) and `POST /api/login_check` (`api_login` firewall, `json_login`). Like §2.6 (CSP), this is a missing hardening measure, not a regression: Symfony never enables `login_throttling` unless a firewall explicitly configures it, so neither route has any brute-force protection.

- [x] No `login_throttling` configured on any firewall in `security.yaml`, on purpose, and `symfony/rate-limiter` is deliberately not installed (`composer.json`/`composer.lock`) so trainees hit Symfony's own explicit error message (*"Login throttling requires the Rate Limiter component..."*) and have to add the dependency themselves

The file impacted is `config/packages/security.yaml` only (unmodified, missing the `login_throttling` block on both `main` and `api_login`). No PHP/Twig code involved. The fix is expected to also touch `composer.json` (`composer require symfony/rate-limiter`).

Note: a follow-up exercise using the generic `symfony/rate-limiter` component directly (for `GET /api/topic`, which isn't an authentication route and so isn't covered by `login_throttling` at all) was planned separately and is now implemented, see §2.10.


### 2.10 Generic Rate Limiter — public form + public API abuse


Follow-up to §2.9 (Login Throttling), deliberately split into its own exercise so trainees exercise the two different shapes `symfony/rate-limiter` takes in a real app. Two targets, added/used for different reasons:
- `POST /inscription` (`SecurityController::register`, new registration form) — a single, precise action in a classic (non-API) controller. `login_throttling` doesn't apply here (it's not an authentication route), and `/connexion` was already claimed by §2.9, so this route was added specifically to give this exercise its own unclaimed public form
- `GET /api/topic` — a public, unauthenticated read endpoint; already existed since §2.8 (JWT). Represents protecting a route (or route family) rather than one controller action

- [x] Neither route has any rate limiting, on purpose, and `symfony/rate-limiter` is not installed (same missing-hardening pattern as §2.6/§2.9) — nothing to break, just nothing built yet

The fix is expected to be two different shapes: a `RateLimiterFactoryInterface` injected directly into `SecurityController::register()` for `/inscription` (per-action limiter), and a `kernel.request` event listener/subscriber for `/api/topic` (route-family limiter, since you can't sensibly inject a limiter into every controller that might sit under `/api`). Both were built and verified working during development (temporarily, then reverted) — the actual `config/packages/rate_limiter.yaml`, controller diff, and listener code are documented in full in `exercises/correction.md`.

Note: `/inscription` itself relies on Symfony's default `#[UniqueEntity]` behavior for email uniqueness, which reveals via a distinct field-level error whether an email is already registered. That's deliberately left as-is — it's the target for a separate exercise, see §2.11. Don't fix it as part of §2.10.


### 2.11 Account/email enumeration on `/inscription` (Identification and Authentication Failures)


Follow-up to §2.10 (Rate Limiter): the registration form added for that exercise uses Symfony's default `#[UniqueEntity(fields: ['email'])]` behavior on `User` (`src/Entity/User.php`), which produces a distinct, field-level validation error ("Cette adresse e-mail est déjà utilisée.") when the submitted email already belongs to an account. Submitting a known email (e.g. `carter.davis1@example.com`) vs. a random one gets a visibly different response — a textbook account-enumeration oracle (CWE-203, mapped under OWASP A07:2021).

- [x] `#[UniqueEntity(fields: ['email'])]` left on `User` at its default/unmodified state, on purpose — nothing to break, it's simply what Symfony's validator does out of the box on a unique-email registration form

The file impacted is `src/Entity/User.php` only (unmodified, `#[UniqueEntity]` attribute still present). No new PHP was written for this exercise beyond what §2.10 already needed for `/inscription` to exist.

Deliberate pedagogical link to §2.10: rate-limiting `/inscription` (that exercise's fix) slows enumeration down but doesn't remove the oracle — a patient attacker respecting the limit still enumerates every account eventually. Same "defense in depth vs. actual fix" lesson already taught by the CSP exercise (§2.6), from a different angle. Also same OWASP category as §2.9 (Login Throttling), a second angle on A07:2021 worth calling out in the correction.

The fix is expected to give a uniform response (message, HTTP code, redirect) regardless of whether the email exists, while still preventing a duplicate row at the database level (the `UNIQUE` constraint on `user.email` must stay). The actual controller/entity diff is documented in full in `exercises/correction.md`.


## 3. Reserved for later (not yet an exercise)


These exist in the app for live demos during class, but have no entry in `exercises/readme.md` or `exercises/correction.md` yet — don't write one unless asked.


### 3.1 Comment delete route — missing login/ownership check (Broken Access Control)


`CommentController::delete` — even after the CSRF fix from exercise 2.7 (`POST` + token), the route still has **no login check and no ownership check at all**: any authenticated user can delete any comment by ID, not just their own. The delete link in `templates/front/topic/show.html.twig` is shown only when `app.user == comment.author` (client-side only, same superficial pattern as the topic Edit button from exercise 2.5).

Planned follow-up (not implemented, not scheduled yet): add an ownership/login check as its own exercise, once exercise 2.7 (CSRF) has been covered.

