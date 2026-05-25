# Daily Coach API — Symfony interview demo

A small "lifestyle-management assistant": you submit a daily **check-in**
(energy, focus goal, distraction risk), and the backend produces a structured
**recommendation** (priority, risk level, next action, reasoning).

The recommendation is produced behind an **AI client interface**, so it can be
generated either by OpenAI or by a deterministic local client — which keeps the
whole system testable and runnable with zero external services.

> **Why this exists.** It's a focused demo for a PHP/Symfony interview. I can't
> share code from previous employers, so I built something clean and small from
> scratch that I can fully explain. My recent work has been Angular / NestJS /
> TypeScript, so the goal here is to show backend architecture, clear
> boundaries, REST design, error handling and testing — applied with Symfony
> conventions.

> **On AI tooling.** I used an AI assistant as a pair-programming aid to move
> quickly through Symfony boilerplate, but I reviewed and adjusted the code
> myself. The focus is on clean structure, testability, and being able to
> explain every technical choice.

---

## Tech stack

| Area      | Choice                                              |
|-----------|-----------------------------------------------------|
| Backend   | Symfony 7.4, PHP 8.4 (8.2+ supported)               |
| Database  | SQLite + Doctrine ORM & Migrations                  |
| Validation| Symfony Validator (on request DTOs)                 |
| HTTP/AI   | Symfony HttpClient → OpenAI Chat Completions        |
| CORS      | nelmio/cors-bundle                                  |
| Tests     | PHPUnit 13 (one API test, one service test)         |
| Frontend  | Vue 3 + TypeScript + Vite (`/frontend`)             |

SQLite was chosen deliberately: the demo runs with no database server to set up.

---

## Architecture

The backend is layered so that each part has one job and dependencies point
inward (controllers → services → domain; nothing depends on OpenAI directly):

```
HTTP request
   │
   ▼
Controller (thin)        parse JSON, validate DTO, present JSON
   │  CreateCheckInRequest (validated)
   ▼
Service                  CheckInService / RecommendationService — business logic
   │
   ├─► ToolRegistry ──► AgentTool(s)        deterministic signals
   │                    (ScoreFocusRiskTool, SuggestNextActionTool)
   │
   └─► AiRecommendationClientInterface      the AI boundary
            ├─ OpenAiRecommendationClient   (real, used when OPENAI_API_KEY set)
            └─ FakeRecommendationClient      (deterministic; tests + fallback)
   │
   ▼
Doctrine entities (CheckIn, Recommendation) → SQLite
```

### How the AI is isolated

`RecommendationService` depends only on `AiRecommendationClientInterface`
(`src/Ai/`). It never imports OpenAI. Two implementations exist:

- **`OpenAiRecommendationClient`** — calls the API via Symfony HttpClient. The
  key is read **only** from the `OPENAI_API_KEY` env var (never hard-coded). The
  model is asked for strict JSON, and the reply is **validated and sanitised**
  (allowed enum values, length-clipped) before we trust it.
- **`FakeRecommendationClient`** — deterministic, offline, always valid.

`OpenAiRecommendationClient` normalises every failure (no key, transport error,
non-2xx, malformed JSON) to a single `AiClientException`. The service catches it
and **falls back to the fake client**, so the endpoint always returns something
useful. Net effect: **if `OPENAI_API_KEY` is missing or the call fails, the app
automatically uses the deterministic client** — no configuration switch needed.

### The tool architecture (and how it could become MCP)

Tools implement a deliberately MCP-shaped contract (`src/Agent/`):

```php
interface AgentToolInterface
{
    public function name(): string;
    public function execute(array $input): array; // JSON-in, JSON-out
}
```

`#[AutoconfigureTag]` on the interface means every tool is auto-collected into
the `ToolRegistry` — adding a capability is just adding a class. Today the tools
run in-process and give the AI concrete signals to ground its answer
(`ScoreFocusRiskTool`, `SuggestNextActionTool`). Because the contract is
`name() + execute(array): array`, swapping in a real **MCP server / OpenAI
tool-calls** later means implementing the same interface over a transport — the
services that consume tools wouldn't change.

---

## API endpoints

Base URL (local): `http://localhost:8000`

| Method | Path                             | Description                                        |
|--------|----------------------------------|----------------------------------------------------|
| POST   | `/api/checkins`                  | Create a check-in. `201` + resource, or `422`.     |
| GET    | `/api/checkins`                  | Recent check-ins (newest first).                   |
| GET    | `/api/checkins/today`            | Latest check-in for today, or clean `404`.         |
| POST   | `/api/recommendations/generate`  | Generate a recommendation for a `checkInId`.       |

### Examples

```bash
# Create a check-in
curl -X POST http://localhost:8000/api/checkins \
  -H 'Content-Type: application/json' \
  -d '{
        "energyLevel": 3,
        "focusGoal": "IE: Prepare for Zes interview",
        "distractionRisk": "IE: Scope creep",
        "notes": "IE: Need a small demo I can explain"
      }'

# Generate a recommendation
curl -X POST http://localhost:8000/api/recommendations/generate \
  -H 'Content-Type: application/json' \
  -d '{ "checkInId": 1 }'
```

### Error handling

`/api/*` always returns JSON, never an HTML error page
(`src/EventListener/ApiExceptionListener.php`):

- **400** — invalid / non-object JSON body
- **422** — validation failure, with a `violations` array (`field` + `message`)
- **404** — unknown `checkInId`, or no check-in today
- **500** — unexpected errors (detail shown only in `dev`)

AI failures do **not** surface as errors — they fall back to the local client.

---

## Running it locally

### Backend (requires PHP 8.2+ and Composer)

```bash
composer install

# Create the SQLite schema
php bin/console doctrine:migrations:migrate --no-interaction

# Serve it (either works)
symfony serve                                    # Symfony CLI, or:
php -S 127.0.0.1:8000 -t public public/index.php # built-in PHP server
```

The API is now at `http://localhost:8000`.

#### Enabling the real OpenAI client

Secrets never go in committed files. Create `.env.local` (gitignored):

```dotenv
OPENAI_API_KEY=sk-your-key-here
# optional overrides:
# OPENAI_MODEL=gpt-4o-mini
```

With a key set, `/api/recommendations/generate` calls OpenAI; without it, the
deterministic client is used automatically. Either way the response shape is the
same.

### Frontend (`/frontend`, requires Node 18+)

```bash
cd frontend
npm install
cp .env.example .env.local   # VITE_API_BASE_URL=http://localhost:8000
npm run dev                  # http://localhost:5173
```

Open http://localhost:5173, fill in the form, **Submit check-in**, then
**Generate recommendation**. CORS for `localhost` is already configured on the
backend, so run both at once.

### Tests

```bash
php vendor/bin/phpunit
```

Two meaningful tests (`tests/`):

1. **`CheckInControllerTest`** — full API test of `POST /api/checkins`: asserts
   `201` and that the response contains `energyLevel`, `focusGoal`,
   `createdAt`.
2. **`RecommendationServiceTest`** — drives `RecommendationService` with the
   `FakeRecommendationClient`: asserts `priority`, `riskLevel`, `nextAction`,
   `reasoning` are present and that **no real OpenAI key is required**.

---

## Key files

| File | Why it matters |
|------|----------------|
| `src/Controller/*` | Thin controllers: parse, validate, delegate, present. |
| `src/Service/RecommendationService.php` | Orchestration: tools → AI client → persist, with fallback. |
| `src/Ai/AiRecommendationClientInterface.php` | The seam that makes AI swappable and testable. |
| `src/Ai/OpenAiRecommendationClient.php` | Real client; env-only key, output validation. |
| `src/Ai/FakeRecommendationClient.php` | Deterministic client for tests + fallback. |
| `src/Agent/AgentToolInterface.php` + `Tool/*` | MCP-shaped tool contract and two tools. |
| `src/Dto/CreateCheckInRequest.php` | Validated request model (rules live here, not on the entity). |
| `src/EventListener/ApiExceptionListener.php` | Consistent JSON errors for `/api/*`. |
| `frontend/src/App.vue` + `api.ts` | Vue UI and a small typed fetch client. |

---

## What I'd improve with more time

- **Authentication & per-user data** — currently single-user by design.
- **API Platform or explicit OpenAPI** spec for documented, content-negotiated endpoints.
- **Pagination & filtering** on `GET /api/checkins`.
- **A real MCP / tool-calling client** implementing `AgentToolInterface` over a transport, and letting the model choose which tools to call.
- **More tests**: validation edge cases, the OpenAI client against a mocked HttpClient, and the fallback path.
- **CI** (GitLab CI as in the target stack) running PHPUnit + a linter (PHP-CS-Fixer / PHPStan) on every push.
- **Caching** of recommendations per check-in to avoid duplicate AI calls.
