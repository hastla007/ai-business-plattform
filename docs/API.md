# API v1

Base-URL: `/api`. Browser requests use Sanctum session cookies and CSRF protection; API clients may use a Sanctum Bearer token. Except for health, authentication, node-agent, and `/me` routes, send `X-Organization-Id`. JSON errors use `{"message":"…"}` plus the HTTP status; validation errors may include an `errors` map.

## Error/status contract

| HTTP | Meaning | Typical codes |
|---|---|---|
| 400 | Required tenant header missing | `organization_required` |
| 401 | No valid session/token | `Unauthenticated` |
| 402 | SaaS plan expired or seat/cost limit reached | `plan_expired` |
| 403 | Role, membership, or policy denied | `organization_forbidden` |
| 404 | Tenant-scoped resource absent or intentionally hidden | — |
| 409 | State conflict, replay, or changed approval payload | — |
| 410 | Invitation expired or already used | — |
| 422 | Request validation or configured model/index incompatible | Laravel validation response |
| 429 | Rate or usage budget reached before model-provider invocation | HTTP exception message |

## Health and authentication

| Method / path | Access | Request / response |
|---|---|---|
| `GET /api/health/live` | Public | `200 {"status":"ok"}` when the process is running. |
| `GET /api/health/ready` | Public | `200 {"status":"ok","checks":{"database":"ok","redis":"ok"}}`; `503` if a dependency fails. |
| `GET /api/health` | Public | Backward-compatible alias for readiness. |
| `POST /api/v1/register` | Public when registration is enabled | `{"name":"Ada","email":"ada@example.test","password":"a-long-password","organization_name":"Acme"}`; `201` user/org. SaaS deployment creates its configured trial plan. |
| `POST /api/v1/login` | Public, throttled | `{"email":"ada@example.test","password":"…"}`; `200` session or API token plus organizations. |
| `POST /api/v1/invitations/register` | Public, throttled | `{"token":"…","name":"Sam","password":"a-long-password"}`; `201` joined user/org. |
| `GET /api/v1/me` | Authenticated | `200 {"user":…,"organizations":[…]}`. |
| `POST /api/v1/logout` | Authenticated + organization | `204`; revokes the current API token or browser session. |

## Organization, members, groups, invitations, and assistant access

| Method / path | Role | Request / response |
|---|---|---|
| `GET /api/v1/organization/settings` | Member | `200 {"settings":{…}}`. |
| `PUT /api/v1/organization/settings` | Owner/Admin | Partial JSON: deployment mode, privacy/fallback, retention, token/cost limits, `plan`, `license_status`, `plan_expires_at`, `plan_grace_days`. Returns updated settings; plan changes are audited and apply configured quotas. |
| `GET /api/v1/members` | Owner/Admin | `200` organization members. |
| `POST /api/v1/invitations` | Owner/Admin | `{"email":"sam@example.test","role":"member"}`; `201` one-time invitation token and expiry; `402` if seats are full. |
| `POST /api/v1/invitations/accept` | Authenticated + organization | `{"token":"…"}`; `201` membership. |
| `GET /api/v1/groups` | Member | `200` groups the organization may display. |
| `POST /api/v1/groups` | Owner/Admin | `{"name":"Support"}`; `201` group. |
| `PUT /api/v1/groups/{group}/members/{user}` | Owner/Admin | Adds a same-tenant user; `201`. |
| `DELETE /api/v1/groups/{group}/members/{user}` | Owner/Admin | Removes membership; `204`. |
| `GET /api/v1/assistants/{assistant}/access` | Owner/Admin | `200` assistant access rules. |
| `PUT /api/v1/assistants/{assistant}/access` | Owner/Admin | Replaces user/group ACLs; `200`; tenant-mismatched resources are hidden/denied. |

## Models and nodes

| Method / path | Role | Request / response |
|---|---|---|
| `GET /api/v1/model-profiles` | Owner/Admin | `200` organization-scoped profiles and node status. |
| `POST /api/v1/model-profiles` | Owner/Admin | `{"name":"Local","model":"chat-model","embedding_model":"embed-model","embedding_dimensions":1536,"base_url":"http://model:8080","is_active":true}`; `201`. Only 1536 dimensions are supported by the current vector index. |
| `PUT /api/v1/model-profiles/{profile}` | Owner/Admin | Partial profile update; `200`. Activating/changing embedding model queues re-indexing. |
| `GET /api/v1/nodes` | Owner/Admin | `200` nodes, health and capability details. |
| `POST /api/v1/nodes/enroll` | Owner/Admin | `{"name":"edge-1","privacy_class":"internal","max_parallel":1}`; `201` node and one-time token. |
| `POST /api/v1/nodes/heartbeat` | Node token | `{"version":"1.0","capabilities":{"models":["chat-model"],"embedding_models":["embed-model"],"embedding_dimensions":[1536]}}`; updates node status. |
| `GET /api/v1/nodes/tasks` | Node token | Polls node commands. |
| `POST /api/v1/nodes/tasks/{command}/result` | Node token | Returns a command result. |
| `DELETE /api/v1/nodes/{node}` | Owner/Admin | Disables a same-organization node; `204`. |

## Conversations, chat, agents, and approvals

| Method / path | Role | Request / response |
|---|---|---|
| `GET /api/v1/conversations` | Member | `200` caller-owned conversation list. |
| `GET /api/v1/conversations/{conversation}` | Owner of conversation | `200` messages and stored source metadata. |
| `DELETE /api/v1/conversations/{conversation}` | Owner of conversation | Deletes only the caller's same-tenant conversation; `204`. |
| `POST /api/v1/chat` | Member | `{"message":"Wie lautet der Ablauf?","conversation_id":null,"assistant_id":1}`; JSON response has `request_id`, `conversation_id`, final message, sources, model, node and usage. With `stream:true`, emits SSE `delta`, `done`, or `error`; disconnected streams do not persist a partial assistant response. |
| `GET /api/v1/agents` | Member | Lists active assistants allowed by the user's ACL. |
| `POST /api/v1/agents/{assistant}/runs` | Member with assistant access | `{"input":"Fasse den Prozess zusammen."}`; `202 {"run_id":…,"request_id":"…","status":"queued"}`. |
| `GET /api/v1/agent-runs/{run}` | Run creator | `200` status, output, and events/sources. |
| `POST /api/v1/agent-runs/{run}/actions` | Run creator or admin | `{"action":"create_ticket","parameters":{"title":"…","description":"…","priority":"normal"}}`; `201` pending approval. |
| `GET /api/v1/approvals` | Owner/Admin | Organization-scoped status, requester, action parameters, result and expiry. |
| `POST /api/v1/agent-runs/{run}/approve` | Owner/Admin | `{"approval_id":42,"decision":"approve"}` or `reject`; `409` on replay, expiry race, or payload tampering. |
| `GET /api/v1/tickets` | Owner/Admin | Lists tickets created by approved actions. |

## Documents, usage, and metrics

| Method / path | Role | Request / response |
|---|---|---|
| `GET /api/v1/documents` | Member | Paginated documents visible under organization, owner, and group policy. |
| `POST /api/v1/documents` | Member | Multipart file (TXT/MD/PDF/DOCX, max 50 MiB), access scope and optional group; `202` queued. |
| `PUT /api/v1/documents/{document}` | Document owner/admin according to scope | Multipart replacement; queues re-indexing. |
| `GET /api/v1/documents/{document}/source` | Authorized reader | Streams the original file as an attachment; unauthorized and cross-tenant resources return `404`. |
| `DELETE /api/v1/documents/{document}` | Document owner/admin according to scope | Deletes source, text, chunks and vectors; `204`. |
| `POST /api/v1/documents/reindex` | Owner/Admin | `{}`; `202 {"queued":12}`; `422` without an active model profile. |
| `GET /api/v1/metrics` | Member | Organization totals for the last 24 hours. |
| `GET /api/v1/admin/metrics` | Owner/Admin | Adds usage by user/profile, remaining org daily budget, rejected requests, hourly usage/cost, p95 latency and queue age. |
| Usage limits | Automatic | Rejected before provider call with `429`; denial reason is recorded without prompts or secrets. |
| SaaS plan gate | Automatic | After plan/grace expiry, reads continue and writes return `402 plan_expired`; On-Premise deployments skip license enforcement. |
