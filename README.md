# Garmin Connector pentru Claude

Server MCP remote, scris în Laravel, care îi dă lui Claude acces de citire la datele tale din Garmin Connect prin Personal API. Poate servi mai mulți utilizatori, fiecare cu propriul cont Garmin și propriul endpoint (`/mcp/codrut`, `/mcp/vio`), și nu folosește bază de date.

## Cum funcționează

- **Utilizatori:** sunt listați în `MCP_USERS`. Fiecare are în `.env` cheia Garmin și amprenta (hash-ul) parolei.
- **Garmin:** serverul schimbă cheia personală a fiecărui utilizator pe un token de 24 de ore, păstrat în cache-ul pe fișier.
- **Claude:** se autentifică prin OAuth cu PKCE. Codurile și tokenurile sunt criptate cu `APP_KEY`, deci nu trebuie salvate. Pe disc se păstrează doar lista celor deja folosite.
- **Izolare:** tokenul primit de Claude e legat de un singur utilizator. Un token emis pentru `/mcp/vio` e respins pe `/mcp/codrut`.
- **Login:** Claude deschide o pagină care cere parola utilizatorului din URL.

## Ai nevoie de

- Un VPS cu Docker și Docker Compose.
- Un domeniu care indică spre VPS, cu porturile 80 și 443 libere.
- Câte o [cheie Garmin Personal API](https://connect.garmin.com/app/settings/personalApiToken) pentru fiecare utilizator, creată din contul lui Garmin. Cheia se afișează o singură dată și expiră după cel mult un an.

## Instalare

```sh
cp .env.example .env
docker compose build
docker compose run --rm app php artisan key:generate --show
docker compose run --rm -it app php artisan mcp:hash-password codrut
docker compose run --rm -it app php artisan mcp:hash-password vio
```

Completează în `.env`:

| Variabilă | Valoare |
|---|---|
| `APP_KEY` | rezultatul `key:generate` |
| `APP_URL` | `https://domeniul-tau`, fără `/` la final |
| `SERVER_NAME` | `domeniul-tau` |
| `MCP_USERS` | numele utilizatorilor, separate prin virgulă, de ex. `codrut,vio` (litere mici, cifre, `_`) |
| `GARMIN_API_KEY_CODRUT` | cheia Garmin a lui `codrut` (numele cu majuscule în variabilă) |
| `MCP_PASSWORD_HASH_CODRUT` | linia afișată de `mcp:hash-password codrut`, cu tot cu ghilimelele simple |

Repetă ultimele două rânduri pentru fiecare utilizator (`GARMIN_API_KEY_VIO`, `MCP_PASSWORD_HASH_VIO` etc.).

Pornește serverul și verifică legătura cu Garmin:

```sh
docker compose up -d
docker compose exec app php artisan garmin:check codrut
docker compose exec app php artisan garmin:check vio
```

## Adăugare în Claude

1. În Claude, deschide **Settings → Connectors → Add custom connector**.
2. Introdu URL-ul utilizatorului, de exemplu `https://domeniul-tau/mcp/codrut` sau `https://domeniul-tau/mcp/vio`.
3. Conectează-te și introdu parola acelui utilizator.

Pentru Claude Code:

```sh
claude mcp add --transport http garmin https://domeniul-tau/mcp/codrut
```

## Întreținere

- **Utilizator nou:** adaugă numele în `MCP_USERS`, completează `GARMIN_API_KEY_<NUME>` și `MCP_PASSWORD_HASH_<NUME>`, apoi rulează `docker compose up -d`.
- **Eliminarea unui utilizator:** scoate-l din `MCP_USERS` și repornește. Tokenurile lui nu mai sunt acceptate.
- **Cheie Garmin expirată:** creează alta, actualizeaz-o în `.env`, apoi rulează `docker compose up -d`.
- **Deconectare de pe toate dispozitivele:** generează un `APP_KEY` nou și repornește.
- **Schimbarea parolei:** rulează din nou `mcp:hash-password <nume>`. Clienții deja conectați rămân conectați până schimbi și `APP_KEY`.
- **În spatele unui reverse proxy:** setează `SERVER_NAME=:80`, publică doar acel port și completează `TRUSTED_PROXIES`.

## Dezvoltare

Nu ai nevoie de PHP local:

```sh
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 vendor/bin/phpunit
```
