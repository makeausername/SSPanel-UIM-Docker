# XNode Node API V1 Skeleton

This repository exposes a temporary `/node/api/v1` skeleton for xnode-agent development.

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

This pass intentionally does not enable production token authentication, subscription generation, traffic mutation, online log writes, detect log writes, billing updates, or admin UI pages. `NodeApiToken` is present only as a middleware skeleton and is not attached to the route group yet.
