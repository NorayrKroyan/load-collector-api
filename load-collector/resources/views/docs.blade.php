<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Load Collector API Docs</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; background: #f6f7fb; color: #111; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 28px 18px 60px; }
        .card { background: #fff; border: 1px solid #e6e8ef; border-radius: 14px; padding: 18px 18px; margin: 14px 0; box-shadow: 0 2px 10px rgba(0,0,0,.04); }
        h1 { margin: 0 0 10px; font-size: 26px; }
        h2 { margin: 0 0 10px; font-size: 18px; }
        p { margin: 10px 0; line-height: 1.5; }
        code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        pre { background: #0b1020; color: #e7e9ee; padding: 14px; border-radius: 12px; overflow:auto; }
        .pill { display:inline-block; padding: 4px 10px; border-radius: 999px; background:#eef2ff; border:1px solid #dbe0ff; font-size: 12px; margin-right: 8px;}
        .kv { display:grid; grid-template-columns: 180px 1fr; gap: 10px; }
        .muted { color: #555; }
        .warn { background:#fff7ed; border:1px solid #fed7aa; }
        .ok { background:#ecfdf5; border:1px solid #a7f3d0; }
        a { color:#1d4ed8; text-decoration:none; }
        a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>Load Collector API — Documentation</h1>
        <p class="muted">
            This service provides two authenticated endpoints:
            <span class="pill">GET /api/importjobs/list</span>
            <span class="pill">POST /api/loads/push</span>
        </p>
        <p class="muted">All endpoints require <b>Sanctum Bearer token</b> in the Authorization header.</p>
    </div>

    <div class="card ok">
        <h2>Authentication</h2>
        <p>Use header:</p>
        <pre>Authorization: Bearer YOUR_TOKEN</pre>
        <p class="muted">Tokens are created via the admin command (recommended) or via Tinker in development.</p>
    </div>

    <div class="card">
        <h2>1) List available jobs</h2>
        <div class="kv">
            <div><b>Method</b></div><div>GET</div>
            <div><b>URL</b></div><div><code>/api/importjobs/list</code></div>
            <div><b>Auth</b></div><div>Required (Bearer token)</div>
        </div>

        <p><b>Example (curl):</b></p>
        <pre>curl --request GET \
  --url http://127.0.0.1:8000/api/importjobs/list \
  --header "accept: application/json" \
  --header "authorization: Bearer YOUR_TOKEN"</pre>

        <p><b>Response (example):</b></p>
        <pre>{
  "items": [
    { "jobname": "Renegade-Formentera-Pe...", "signature": [] },
    { "jobname": "(OLYMPUS) Petro Hunt - ...", "signature": [] }
  ]
}</pre>
    </div>

    <div class="card">
        <h2>2) Push load data (JSON + optional image)</h2>
        <div class="kv">
            <div><b>Method</b></div><div>POST</div>
            <div><b>URL</b></div><div><code>/api/loads/push</code></div>
            <div><b>Content-Type</b></div><div><code>multipart/form-data</code></div>
            <div><b>Auth</b></div><div>Required (Bearer token)</div>
        </div>

        <p><b>Form fields:</b></p>
        <ul>
            <li><code>payload</code> (required): JSON file upload or raw JSON string</li>
            <li><code>bolimage</code> (optional): image file (jpg/png)</li>
        </ul>

        <p><b>Example (payload JSON file only):</b></p>
        <pre>curl -X POST http://127.0.0.1:8000/api/loads/push \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "payload=@load.json"</pre>

        <p><b>Example (payload JSON file + image):</b></p>
        <pre>curl -X POST http://127.0.0.1:8000/api/loads/push \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "payload=@load.json" \
  -F "bolimage=@photo.jpg"</pre>

        <p><b>Example response:</b></p>
        <pre>{ "ok": true, "id": 1 }</pre>

        <div class="card warn" style="margin-top:14px;">
            <b>Common errors</b>
            <ul>
                <li><b>401 Unauthenticated</b>: missing/incorrect Bearer token</li>
                <li><b>422</b>: missing <code>payload</code> or invalid JSON</li>
                <li><b>404 route not found</b>: wrong URL (watch for hidden newline in Postman)</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <h2>Where data is stored</h2>
        <p><b>Database tables:</b></p>
        <ul>
            <li><code>fake_jobs</code> — seeded with required jobnames</li>
            <li><code>loadimports</code> — one row per upload (paths, sizes, timestamps, parsed JSON)</li>
            <li><code>personal_access_tokens</code> — Sanctum tokens (hashed)</li>
        </ul>

        <p><b>Files on disk:</b> stored under:</p>
        <pre>storage/app/loadimports/YYYY-MM-DD/</pre>

        <p class="muted">The DB stores file paths; the actual payload JSON and images are saved on disk.</p>
    </div>

    <div class="card">
        <h2>Token creation (recommended admin command)</h2>
        <p class="muted">
            Tokens can’t be “viewed” later (only shown once when created). Generate and copy it immediately.
        </p>
        <pre>php artisan app:issue-token --email=tester@client.com --name=test-access --revoke-old</pre>
    </div>

</div>
</body>
</html>
