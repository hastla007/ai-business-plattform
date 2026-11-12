# Remote model node agent

Create a node in the organization admin API with `POST /api/v1/nodes/enroll`. The response includes a one-time `token`; store it as a secret on the node host. The node agent makes outbound HTTPS requests to the platform and sends heartbeats, polls work, and returns model responses. It does not expose the model server to the platform or the public internet.

Install dependencies with `python -m pip install -r requirements.txt`, then set:

- `PLATFORM_URL`: platform origin, for example `https://engine.example.com`
- `NODE_TOKEN`: one-time token returned by enrollment
- `MODEL_BASE_URL`: local OpenAI-compatible model server origin
- `NODE_MODELS`: comma-separated model identifiers served by this node
- `MAX_PARALLEL`: local request concurrency (at most the server-registered node limit)
- Optional `NODE_VERSION` and `HARDWARE_LABEL`

Run `python agent.py` under a process supervisor. Configure TLS verification normally; do not put tokens in source control. Disable the node in the admin API to revoke access. The agent only forwards POST requests for chat completions and embeddings to the configured local model server.
