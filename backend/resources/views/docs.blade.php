<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Load Collector API Docs</title>

    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 0;
            background: #f6f7fb;
            color: #111;
        }

        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 18px 60px;
        }

        .card {
            background: #fff;
            border: 1px solid #e6e8ef;
            border-radius: 14px;
            padding: 18px;
            margin: 14px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
        }

        h1 { margin: 0 0 10px; font-size: 26px; }
        h2 { margin: 0 0 10px; font-size: 18px; }

        p { margin: 10px 0; line-height: 1.5; }

        code, pre {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        pre {
            background: #0b1020;
            color: #e7e9ee;
            padding: 14px;
            border-radius: 12px;
            overflow: auto;
        }

        .pill {
            display:inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background:#eef2ff;
            border:1px solid #dbe0ff;
            font-size: 12px;
            margin-right: 8px;
        }

        .kv {
            display:grid;
            grid-template-columns: 180px 1fr;
            gap: 10px;
        }

        .muted { color: #555; }

        .warn {
            background:#fff7ed;
            border:1px solid #fed7aa;
        }

        .ok {
            background:#ecfdf5;
            border:1px solid #a7f3d0;
        }

        a { color:#1d4ed8; text-decoration:none; }
        a:hover { text-decoration:underline; }
    </style>
</head>

<body>
<div class="wrap">

    <!-- HEADER -->
    <div class="card">
        <h1>Load Collector API — Documentation</h1>

        <p class="muted">
            Base URL:
            <br>
            <b>https://api.sandbox.voldhaul.com/api/load-collector</b>
        </p>

        <p class="muted">
            Available endpoints:
            <span class="pill">GET /api/importjobs/list</span>
            <span class="pill">POST /api/loads/push</span>
        </p>

        <p class="muted">
            All endpoints require <b>Bearer token authentication</b> (Laravel Sanctum).
        </p>
    </div>

    <!-- AUTH -->
    <div class="card ok">
        <h2>Authentication</h2>

        <p>Send this header with every request:</p>

        <pre>Authorization: Bearer YOUR_API_KEY</pre>

        <p class="muted">
            Tokens are issued by the system administrator using:
        </p>

        <pre>php artisan app:issue-token --email=user@client.com --name=client-access --revoke-old</pre>
    </div>

    <!-- ENDPOINT 1 -->
    <div class="card">
        <h2>1) List Available Jobs</h2>

        <div class="kv">
            <div><b>Method</b></div><div>GET</div>
            <div><b>URL</b></div>
            <div><code>/api/importjobs/list</code></div>
            <div><b>Auth</b></div><div>Required</div>
        </div>

        <p><b>Example (curl):</b></p>

        <pre>curl -X GET \
"https://api.sandbox.voldhaul.com/api/load-collector/api/importjobs/list" \
-H "Accept: application/json" \
-H "Authorization: Bearer YOUR_API_KEY"</pre>

        <p><b>Response:</b></p>

        <pre>{
  "items": [
    {
      "jobname": "Renegade-Formentera-Pe...",
      "signature": []
    },
    {
      "jobname": "(OLYMPUS) Petro Hunt - ...",
      "signature": []
    }
  ]
}</pre>
    </div>

    <!-- ENDPOINT 2 -->
    <div class="card">
        <h2>2) Push Load Data (JSON + Optional Image)</h2>

        <div class="kv">
            <div><b>Method</b></div><div>POST</div>
            <div><b>URL</b></div>
            <div><code>/api/loads/push</code></div>
            <div><b>Content-Type</b></div>
            <div><code>multipart/form-data</code></div>
            <div><b>Auth</b></div><div>Required</div>
        </div>

        <p><b>Form fields:</b></p>

        <ul>
            <li><code>payload</code> (required): JSON file</li>
            <li><code>bolimage</code> (optional): JPG / PNG image</li>
        </ul>

        <p><b>Example (JSON only):</b></p>

        <pre>curl -X POST \
"https://api.sandbox.voldhaul.com/api/load-collector/api/loads/push" \
-H "Authorization: Bearer YOUR_API_KEY" \
-F "payload=@load.json"</pre>

        <p><b>Example (JSON + Image):</b></p>

        <pre>curl -X POST \
"https://api.sandbox.voldhaul.com/api/load-collector/api/loads/push" \
-H "Authorization: Bearer YOUR_API_KEY" \
-F "payload=@load.json;type=application/json" \
-F "bolimage=@photo.jpg;type=image/jpeg"</pre>

        <p><b>Response:</b></p>

        <pre>{
  "ok": true,
  "id": 15
}</pre>

        <div class="card warn" style="margin-top:14px;">
            <b>Common Errors</b>

            <ul>
                <li><b>401</b> — Missing or invalid token</li>
                <li><b>422</b> — Missing <code>payload</code> or invalid JSON</li>
                <li><b>404</b> — Incorrect URL</li>
                <li><b>500</b> — Server error (contact admin)</li>
            </ul>
        </div>
    </div>

    <!-- STORAGE -->
    <div class="card">
        <h2>Data Storage</h2>

        <p><b>Database tables:</b></p>

        <ul>
            <li><code>fake_jobs</code> — Available jobs</li>
            <li><code>loadimports</code> — Uploaded records</li>
            <li><code>personal_access_tokens</code> — API tokens</li>
        </ul>

        <p><b>Files are stored under:</b></p>

        <pre>storage/app/loadimports/YYYY-MM-DD/</pre>

        <p class="muted">
            Database stores metadata and file paths. Actual files are stored on disk.
        </p>
    </div>

    <!-- ADMIN -->
    <div class="card">
        <h2>Token Management (Admin)</h2>

        <p class="muted">
            Tokens are shown only once when created.
            Save them securely.
        </p>

        <pre>php artisan app:issue-token \
--email=client@example.com \
--name=client-access \
--revoke-old</pre>
    </div>

</div>
</body>
</html>
