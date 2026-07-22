# XNode Node API V1

This repository exposes `/node/api/v1` for early xnode-agent development.

Implemented endpoints:

- `POST /node/api/v1/enroll`
- `GET /node/api/v1/config`
- `GET /node/api/v1/users`
- `GET /node/api/v1/detect-rules`
- `GET /node/api/v1/audit-rules`
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

During this rollout, the default Reality upstream is `www.cloudflare.com:443` and the default Reality SNI/server name is `www.cloudflare.com`. The Xray server config should render this as `realitySettings.target = "www.cloudflare.com:443"` and `realitySettings.serverNames = ["www.cloudflare.com"]`.

Fetch the users DTO list:

```bash
curl -sS https://panel.example.com/node/api/v1/users \
  -H "Authorization: Bearer xn_xxx"
```

`GET /node/api/v1/users` returns real eligible users for the authenticated node. It always excludes banned users and empty or mock UUIDs. Administrators bypass class and node-group restrictions to match the client API's unlimited/connectable behavior; normal users retain the existing class and node-group filters.

The UUIDs in this response must match the UUIDs emitted in `/sub/{token}/v2ray` VLESS Reality links because xnode-agent uses them to render Xray `clients[].id`. The mock user UUID is retained only as a test/helper fallback and is no longer used for normal authenticated nodes.

## Managed XNode access and traffic policy

XNode nodes (`sort = 15`) use one panel-managed policy. There is no server cost profile or node-tier choice:

- `node_class = 0` and `node_group = 0`: available to every eligible paid user without node tiers.
- `traffic_rate = 2` for every XNode node: reported upload and download bytes are each multiplied by two when charged to the user's monthly quota.
- Dynamic traffic rate is disabled: a byte always consumes the same quota regardless of time.
- `node_speedlimit = 0` and `node_bandwidth_limit = 0`: no panel-side speed or node quota limit.
- `bandwidthlimit_resetday = 1`: the displayed node traffic counter resets on day 1 while remaining unlimited.

The managed Unlimited plan remains disabled because it has no finite monthly quota. Existing XNode rows are migrated to the same two-times rate, stale cost-profile metadata is removed, and current `node_bandwidth` counters are preserved. Non-XNode node types retain the original configurable SSPanel-UIM behavior.

Fetch detect rules:

```bash
curl -sS https://panel.example.com/node/api/v1/detect-rules \
  -H "Authorization: Bearer xn_xxx"
```

`/detect-rules` remains available for older agents. XNode v0.1.7 and later use the scoped v2 bundle:

```bash
curl -sS https://panel.example.com/node/api/v1/audit-rules \
  -H "Authorization: Bearer xn_xxx"
```

The v2 response carries `revision`, `rules_hash`, and rules for protocol, domain suffix/regex, IP/CIDR, and port matching. It supports `If-None-Match`; agents keep a last-good local cache and acknowledge the applied revision, hash, status, and apply time in runtime/heartbeat reports.

Report runtime metadata:

```bash
curl -sS -X POST https://panel.example.com/node/api/v1/runtime \
  -H "Authorization: Bearer xn_xxx" \
  -H "Content-Type: application/json" \
  -d '{"node_id":1001,"agent_version":"dev","core_version":"","state":"running","public_key":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA","short_ids":["fedcba9876543210","0123456789abcdef"],"reality_hash":"15190bdb7c73c3b9f12a4c483e3205ce851f4bee063e99a02c09e5438473e50f","capabilities":["vless","reality","vision"],"config_hash":"stub-config-v1","last_error":""}'
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

XNode subscription links require a running, error-free runtime seen within the last 180 seconds. The panel validates the base64url public key, canonicalizes and sorts the short IDs, recalculates their SHA-256 Reality hash, and requires it to match `node_runtimes.reality_hash`. Any invalid or stale newest runtime skips that node's XNode link without falling back to older metadata.

The VLESS Reality subscription SNI must match the node config `reality.server_names` / Xray `realitySettings.serverNames`. Xray client JSON uses `realitySettings.password` for the Reality public key, while share links use `pbk=<public key>`. Short IDs are emitted as `sid=<short id>`.

The Reality private key never leaves the node and is not included in panel subscription output.

## Current data behavior

- `/users` reads active, unexpired, non-banned users with remaining traffic from the `user` table for the authenticated node and does not use the mock user for normal node-token requests.
- The UUID returned by `/users` matches the UUID used in `/sub/{token}/v2ray`, and xnode-agent writes it to Xray `clients[].id`.
- `/runtime` stores `public_key`, normalized `short_ids`, and `reality_hash` together only after the panel recalculates and verifies the canonical hash.
- `/heartbeat` updates safe runtime heartbeat metadata in `node_runtimes`.
- `/traffic` is idempotent by `report_id`, bills user upload and download with the node rate, and stores the raw bidirectional sum in `node_bandwidth`.
- `/online` and `/detect-log` persist their reports through the same authenticated XNode API contract.
