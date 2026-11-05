# Garmin Connector pentru Claude

Server MCP remote, scris în Laravel, care îi dă lui Claude acces de citire la datele tale din Garmin Connect prin Personal API. E gândit pentru un singur utilizator și nu folosește bază de date.

## Cum funcționează

- **Garmin:** cheia personală stă în `.env`. Serverul o schimbă pe un token de 24 de ore, păstrat în cache-ul pe fișier.
- **Claude:** se autentifică prin OAuth cu PKCE. Codurile și tokenurile sunt criptate cu `APP_KEY`, deci nu trebuie salvate. Pe disc se păstrează doar lista celor deja folosite.
- **Login:** Claude deschide o pagină care îți cere parola, a cărei amprentă (hash) stă în `.env`.

## Ai nevoie de

- Un VPS cu Docker și Docker Compose.
- Un domeniu care indică spre VPS, cu porturile 80 și 443 libere.
- O [cheie Garmin Personal API](https://connect.garmin.com/app/settings/personalApiToken). Cheia se afișează o singură dată și expiră după cel mult un an.

## Instalare

```sh
cp .env.example .env
docker compose build
docker compose run --rm app php artisan key:generate --show
docker compose run --rm -it app php artisan mcp:hash-password
```

Completează în `.env`:

| Variabilă | Valoare |
|---|---|
| `APP_KEY` | rezultatul `key:generate` |
| `APP_URL` | `https://domeniul-tau`, fără `/` la final |
| `SERVER_NAME` | `domeniul-tau` |
| `GARMIN_PERSONAL_API_KEY` | cheia Garmin |
| `MCP_OWNER_PASSWORD_HASH` | linia afișată de `mcp:hash-password`, cu tot cu ghilimelele simple |

Pornește serverul și verifică legătura cu Garmin:

```sh
docker compose up -d
docker compose exec app php artisan garmin:check
```

## Adăugare în Claude

1. În Claude, deschide **Settings → Connectors → Add custom connector**.
2. Introdu URL-ul `https://domeniul-tau/mcp`.
3. Conectează-te și introdu parola când ți se cere.

Pentru Claude Code:

```sh
claude mcp add --transport http garmin https://domeniul-tau/mcp
```

## Întreținere

- **Cheie Garmin expirată:** creează alta, actualizeaz-o în `.env`, apoi rulează `docker compose up -d`.
- **Deconectare de pe toate dispozitivele:** generează un `APP_KEY` nou și repornește.
- **Schimbarea parolei:** rulează din nou `mcp:hash-password`. Clienții deja conectați rămân conectați până schimbi și `APP_KEY`.
- **În spatele unui reverse proxy:** setează `SERVER_NAME=:80`, publică doar acel port și completează `TRUSTED_PROXIES`.

## Dezvoltare

Nu ai nevoie de PHP local:

```sh
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 vendor/bin/phpunit
```
