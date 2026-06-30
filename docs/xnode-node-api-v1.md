# XNode Node API V1

This repository exposes `/node/api/v1` for early xnode-agent development.

Implemented endpoints:

- `POST /node/api/v1/enroll`
- `GET /node/api/v1/config`
- `GET /node/api/v1/users`
- `GET /node/api/v1/detect-rules`
- `POST /node/api/v1/runtime`
- `POST /node/api/v1/traffic`
- `POST /node/api/v1/online`
- `POST /node/api/v1/detect-log`
- `POST /node/api/v1/heartbeat`

## Authentication

`POST /node/api/v1/enroll` is public at the routing layer, but it validates a one-time enroll token internally:

```http
Authorization: Bearer xne_...
```

The enroll token is stored in `node_tokens.token_hash` with `token_type = "enroll"`. It must match the enrolling `node_id`, must not be used or revoked, and must not be expired. After successful enrollment, the panel marks it with `used_at`.

All other `/node/api/v1` endpoints require a node token:

```http
Authorization: Bearer xn_...
```

`X-Node-Token: xn_...` is also accepted by `NodeApiToken` for local debugging. Agents should use the `Authorization` header.

The node token is stored only as `node_tokens.token_hash` with `token_type = "node"`. The plaintext `node_token` is returned only once from `/enroll` and must be stored only on the node side.

## Temporary enroll-token helper

There is no admin UI or CLI wrapper yet. For local development, future admin or CLI code can call:

```php
$token = App\Services\NodeEnrollmentService::createEnrollTokenForNode(1, 600);
```

Show the returned `xne_...` token once to the operator, then discard it. The helper stores only the token hash and sets `expires_at` to `time() + $ttlSeconds`.

## Current data behavior

- `/runtime` upserts safe runtime metadata in `node_runtimes`.
- `/heartbeat` updates safe runtime heartbeat metadata in `node_runtimes`.
- `/traffic`, `/online`, and `/detect-log` are accepted-only for now.
- This pass does not mutate user traffic, online logs, detect logs, hourly usage, billing data, subscriptions, or legacy `/mod_mu` behavior.
