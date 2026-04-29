# Insighta Labs+ Stage 3

Stage 3 extends the existing Stage 2 backend with secure authentication, RBAC, export/create workflows, CLI tooling, and a web portal scaffold while preserving filtering, sorting, pagination, and natural-language search.

## Architecture

- `backend` (this Laravel app): API, auth, RBAC, rate limiting, logging, profile ingestion/search/export.
- `cli/`: globally installable Node.js CLI (`insighta`) using OAuth PKCE + local callback.
- `web/`: lightweight Express + EJS portal scaffold with CSRF middleware and cookie support.

## Authentication Flow (GitHub OAuth + PKCE)

1. Client generates PKCE values (`code_verifier`, `code_challenge`).
2. Client calls `GET /api/auth/github` with `code_challenge` and desired local `redirect_uri`.
3. Backend stores OAuth state and redirects to GitHub authorize URL.
4. GitHub redirects to backend callback `GET /api/auth/github/callback`.
5. Backend validates PKCE challenge, fetches GitHub user, creates/updates local user, issues tokens:
   - Access token: 3 minutes (`access` ability)
   - Refresh token: 5 minutes (`refresh` ability)
6. `POST /api/auth/refresh` rotates refresh token (old one invalidated).
7. `POST /api/auth/logout` invalidates refresh token.

## Token Lifecycle

- Access token is required for all `/api/profiles*` calls.
- Access tokens are short-lived and should be auto-refreshed by client.
- Refresh tokens are one-time rotation tokens (old invalidated on refresh).
- Logout deletes refresh token and ends session continuity.

## Role Enforcement

- Role field exists on `users` (`admin` or `analyst`).
- `analyst`: read-only access.
- `admin`: can create and delete profiles.
- RBAC is centralized via middleware (`role:*`) on route groups, not scattered in controller logic.

## API Requirements

- All profile endpoints require `X-API-Version: 1`, enforced by middleware.
- Standardized error format:
  - `{"status":"error","message":"..."}`
- Pagination response includes:
  - `page`, `limit`, `total`, `total_pages`, `links` (`self`, `next`, `prev`)

## Endpoints

- `GET /api/auth/github`
- `GET /api/auth/github/callback`
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `GET /api/profiles`
- `GET /api/profiles/search?q=...`
- `POST /api/profiles` (admin only)
- `DELETE /api/profiles/{profile}` (admin only)
- `GET /api/profiles/export?format=csv`

## Natural Language Search Approach

Natural-language search remains rule-based (`App\Services\QueryParser`) and deterministic:
- Regex/keyword matching for gender, age groups, age boundaries, and country mappings.
- Parsed filters are merged into the same `index` query pipeline, so filtering/sorting/pagination semantics remain consistent between normal list and NLP search.

## Rate Limiting and Logging

- `/api/auth/*`: `10 req/min` per IP.
- Other protected API routes: `60 req/min` per authenticated user.
- Request logging middleware records:
  - HTTP method
  - endpoint path
  - status code
  - response time (ms)

## CLI Usage

Install globally from `cli/`:

```bash
cd cli
npm install
npm link
```

Commands:
- `insighta login`
- `insighta logout`
- `insighta whoami`
- `insighta profiles list`
- `insighta profiles get <id>`
- `insighta profiles search "<query>"`
- `insighta profiles create "<name>"`
- `insighta profiles export --format csv`

Credentials are stored at `~/.insighta/credentials.json`.

## Web Portal

From `web/`:

```bash
npm install
npm run dev
```

Pages:
- `/login`
- `/dashboard`
- `/profiles`
- `/profiles/:id`
- `/search`
- `/account`

CSRF middleware is enabled and designed for cookie-backed auth integration.

## Environment Variables

Required backend variables:

- `GITHUB_CLIENT_ID`
- `GITHUB_CLIENT_SECRET`
- `GITHUB_CALLBACK_URL`
- `APP_URL`

Optional client variables:
- `INSIGHTA_API_BASE_URL` (CLI + web)

## CI

GitHub Actions workflow at `.github/workflows/ci.yml` runs:
- Backend tests
- CLI lint check
- Web build smoke check
