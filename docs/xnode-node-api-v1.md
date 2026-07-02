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

All other protected `/node/api/v1` endpoints require a node token:

```http
Authorization: Bearer xn_...
```

`X-Node-Token: xn_...` is also accepted by `NodeApiToken` for local debugging. Agents should use the `Authorization` header.

The node token is stored only as `node_tokens.token_hash` with `token_type = "node"`. The plaintext `node_token` is returned only once from `/enroll` and must be stored only on the node side.

## Admin runtime status and enroll command

The admin node edit page (`GET /admin/node/{id}/edit`) now shows the latest XNode runtime metadata from `node_runtimes`, including state, last heartbeat, agent/core versions, config hash, last error, public key, and short IDs. If a node has not reported runtime data yet, the page shows it as unregistered / no heartbeat.

Admins can generate a one-time enroll token and early integration command from the same edit page. The action returns PowerShell and Bash `xnode-agent --check` command text for current integration testing. The plaintext `xne_...` enroll token is stored only as `node_tokens.token_hash`, is shown only in that admin action response, defaults to a 10 minute TTL, and is consumed after successful enrollment.

The final one-click `/node/install.sh` installer endpoint is not implemented in this step and remains future work.

## Generate a temporary enroll token from CLI

Operators can also generate an enroll token from the existing `Tool` command:

```bash
php xcat Tool generateXNodeEnrollToken 1001
```

The optional second argument is the TTL in seconds. It defaults to `600`:

```bash
php xcat Tool generateXNodeEnrollToken 1001 600
```

In Docker installs, run it inside the app container:

```bash
docker compose exec app php xcat Tool generateXNodeEnrollToken 1001 600
```

The command validates that `node_id` is a positive integer and exists in the `node` table. It stores only the enroll token hash in `node_tokens.token_hash` with `token_type = "enroll"`, prints the plaintext `xne_...` token once, and sets `expires_at` to `time() + ttl_seconds`.

## Response envelope

Normal responses use this JSON envelope:

```json
{
  "ret": 1,
  "data": {},
  "request_id": "xn_..."
}
```

Error responses use this JSON envelope:

```json
{
  "ret": 0,
  "msg": "Invalid node token",
  "code": "AUTH_INVALID_TOKEN",
  "request_id": "xn_..."
}
```

## Curl examples

Generate a one-time enroll token:

```bash
docker compose exec app php xcat Tool generateXNodeEnrollToken 1001 600
```

Enroll the node with that token:

```bash
curl -sS -X POST https://panel.example.com/node/api/v1/enroll \
  -H "Authorization: Bearer xne_xxx" \
  -H "Content-Type: application/json" \
  -d '{"node_id":1001,"domain":"node1.example.com","agent_version":"dev","install_fingerprint":"manual-test","host":{"os":"linux","arch":"amd64"}}'
```

After successful enrollment, `/enroll` returns the plaintext `node_token` once to the agent. The agent must use that token for protected endpoints:

```http
Authorization: Bearer xn_...
```

Fetch the node config:

```bash
curl -sS https://panel.example.com/node/api/v1/config \
  -H "Authorization: Bearer xn_xxx"
```

Fetch the users DTO list:

```bash
curl -sS https://panel.example.com/node/api/v1/users \
  -H "Authorization: Bearer xn_xxx"
```

`GET /node/api/v1/users` returns real eligible users for the authenticated node. The UUIDs in this response must match the UUIDs emitted in `/sub/{token}/v2ray` VLESS Reality links because xnode-agent uses them to render Xray `clients[].id`. The mock user UUID is retained only as a test/helper fallback and is no longer used for normal authenticated nodes.

Fetch detect rules:

```bash
curl -sS https://panel.example.com/node/api/v1/detect-rules \
  -H "Authorization: Bearer xn_xxx"
```

Report runtime metadata:

```bash
curl -sS -X POST https://panel.example.com/node/api/v1/runtime \
  -H "Authorization: Bearer xn_xxx" \
  -H "Content-Type: application/json" \
  -d '{"node_id":1001,"agent_version":"dev","core_version":"","state":"running","public_key":"public-key-example","short_ids":["0123456789abcdef"],"capabilities":["vless","reality","vision"],"config_hash":"stub-config-v1","last_error":""}'
```

Send a heartbeat:

```bash
curl -sS -X POST https://panel.example.com/node/api/v1/heartbeat \
  -H "Authorization: Bearer xn_xxx" \
  -H "Content-Type: application/json" \
  -d '{"node_id":1001,"state":"running","config_hash":"stub-config-v1","last_error":""}'
```

## V2Ray subscription compatibility

`GET /sub/{token}/v2ray` can include XNode `vless://` Reality links alongside the existing legacy VMess lines. The `/v2ray` name is kept for client compatibility.

XNode subscription links require safe runtime metadata in `node_runtimes.public_key` and `node_runtimes.short_ids_json`. If the runtime row is missing, the public key is empty, or the short IDs are malformed, the XNode link is skipped.

The Reality private key never leaves the node and is not included in panel subscription output.

## Current data behavior

- `/users` reads real eligible users from the `user` table for the authenticated node and does not use the mock user for normal node-token requests.
- `/runtime` upserts safe runtime metadata in `node_runtimes`.
- `/heartbeat` updates safe runtime heartbeat metadata in `node_runtimes`.
- `/traffic`, `/online`, and `/detect-log` are accepted-only for now.
- This pass does not mutate user traffic, online logs, detect logs, hourly usage, billing data, subscriptions, or legacy `/mod_mu` behavior.
