<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Foundry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'JetBrains Mono', monospace;
            background: #fff;
            color: #2c3e50;
            min-height: 100vh;
            text-transform: uppercase;
            -webkit-font-smoothing: antialiased;
        }
        header {
            background: #8f2f2f;
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header span { font-size: 14px; font-weight: 600; letter-spacing: .04em; }
        .header-nav { display: flex; align-items: center; gap: 12px; }
        .nav-btn {
            background: none;
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 4px;
            color: #fff;
            padding: 6px 14px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .nav-btn:hover { border-color: #fff; background: rgba(255,255,255,.1); }
        main { padding: 2rem 1.5rem; max-width: 900px; margin: 0 auto; }
        h2 {
            font-size: 12px; font-weight: 600; color: #8f2f2f;
            letter-spacing: .06em; margin: 1.75rem 0 .75rem;
        }
        .flash {
            background: #eef7f0; border: 1px solid #b7ddc4; color: #2e7d52;
            border-radius: 4px; padding: 10px 14px; font-size: 12px; margin-bottom: 1rem;
        }
        .errors {
            background: #fdf0f0; border: 1px solid #e0b0b0; color: #8f2f2f;
            border-radius: 4px; padding: 10px 14px; font-size: 12px; margin-bottom: 1rem;
        }
        .errors ul { margin: 0; padding-left: 1.1rem; }
        .card {
            background: #fff; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 1.25rem 1.4rem; margin-bottom: 1rem;
        }
        .ref { font-size: 11px; color: #666; letter-spacing: .04em; text-transform: none; }
        .ref code {
            background: #f2f2f2; padding: 2px 6px; border-radius: 3px;
            font-size: 11px; color: #2c3e50; word-break: break-all;
        }
        .ref strong { color: #2c3e50; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1rem; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field.full { grid-column: 1 / -1; }
        label { font-size: 10px; font-weight: 500; letter-spacing: .08em; color: #2c3e50; }
        input[type="text"], input[type="password"] {
            width: 100%; background: transparent; border: none; border-bottom: 1px solid #ccc;
            padding: 7px 0; color: #2c3e50; font-family: 'JetBrains Mono', monospace;
            font-size: 13px; outline: none; text-transform: none; transition: border-color .2s;
        }
        input[type="text"]:focus, input[type="password"]:focus { border-bottom-color: #8f2f2f; }
        .check { display: flex; align-items: center; gap: 8px; font-size: 11px; letter-spacing: .04em; }
        .check input { width: auto; }
        .row-actions { display: flex; gap: 8px; align-items: center; margin-top: 1rem; }
        .btn {
            background: #8f2f2f; color: #fff; border: none; border-radius: 4px; padding: 8px 18px;
            font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;
            letter-spacing: .05em; text-transform: uppercase; cursor: pointer; transition: background .2s;
        }
        .btn:hover { background: #7a2222; }
        .btn--ghost { background: none; color: #8f2f2f; border: 1px solid #e0b0b0; }
        .btn--ghost:hover { background: #fdf0f0; }
        .host-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
        .host-url { font-size: 13px; font-weight: 600; color: #2c3e50; text-transform: none; word-break: break-all; }
        .badge { font-size: 9px; font-weight: 600; padding: 2px 7px; border-radius: 10px; letter-spacing: .06em; }
        .badge--on { background: #e7f4ec; color: #2e7d52; }
        .badge--off { background: #f1f1f1; color: #999; }
        .badge--secret { background: #eef1fb; color: #3a539b; }
        .empty { font-size: 12px; color: #bbb; margin: .5rem 0 0; }
        .env-item { font-size: 12px; color: #555; text-transform: none; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .env-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <header>
        <span>SQL Designer — Foundry</span>
        <div class="header-nav">
            <a href="{{ route('admin.dashboard') }}" class="nav-btn">Dashboard</a>
            <a href="{{ route('admin.library') }}" class="nav-btn">Library</a>
            <a href="{{ route('admin.reviews') }}" class="nav-btn">Reviews</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="nav-btn">Sign Out</button>
            </form>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card ref">
            <p>Configure the Foundry stacks users may connect to. Each host needs an OAuth client registered in its Foundry Developer Console.</p>
            <p style="margin-top:.5rem;">Register this redirect URI on every client: <code>{{ $redirectUri }}</code></p>
            <p style="margin-top:.5rem;">Custom (unlisted) hosts: <strong>{{ $allowCustomHosts ? 'allowed' : 'blocked' }}</strong> (set via <code>FOUNDRY_ALLOW_CUSTOM_HOSTS</code>).</p>
        </div>

        <h2>Add Foundry Host</h2>
        <div class="card">
            <form method="POST" action="{{ route('admin.foundry.store') }}">
                @csrf
                <div class="grid">
                    <div class="field full">
                        <label for="new_host_url">Host URL</label>
                        <input type="text" id="new_host_url" name="host_url" value="{{ old('host_url') }}" placeholder="https://yourstack.palantirfoundry.com">
                    </div>
                    <div class="field">
                        <label for="new_display_name">Display name</label>
                        <input type="text" id="new_display_name" name="display_name" value="{{ old('display_name') }}" placeholder="Your Foundry">
                    </div>
                    <div class="field">
                        <label for="new_client_id">OAuth client ID</label>
                        <input type="text" id="new_client_id" name="client_id" value="{{ old('client_id') }}">
                    </div>
                    <div class="field">
                        <label for="new_client_secret">Client secret (optional)</label>
                        <input type="password" id="new_client_secret" name="client_secret" placeholder="Blank = public PKCE client" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label>Enabled</label>
                        <span class="check"><input type="checkbox" name="enabled" value="1" checked> Allow connections</span>
                    </div>
                </div>
                <div class="row-actions">
                    <button type="submit" class="btn">Add Host</button>
                </div>
            </form>
        </div>

        <h2>Configured Hosts</h2>
        @forelse ($hosts as $host)
            <div class="card">
                <div class="host-head">
                    <span class="host-url">{{ $host->host_url }}</span>
                    <span>
                        <span class="badge {{ $host->enabled ? 'badge--on' : 'badge--off' }}">{{ $host->enabled ? 'Enabled' : 'Disabled' }}</span>
                        <span class="badge {{ $host->client_secret ? 'badge--secret' : 'badge--off' }}">{{ $host->client_secret ? 'Confidential' : 'Public PKCE' }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('admin.foundry.update', $host) }}">
                    @csrf
                    @method('PATCH')
                    <div class="grid">
                        <div class="field full">
                            <label>Host URL</label>
                            <input type="text" name="host_url" value="{{ $host->host_url }}">
                        </div>
                        <div class="field">
                            <label>Display name</label>
                            <input type="text" name="display_name" value="{{ $host->display_name }}">
                        </div>
                        <div class="field">
                            <label>OAuth client ID</label>
                            <input type="text" name="client_id" value="{{ $host->client_id }}">
                        </div>
                        <div class="field">
                            <label>Replace client secret</label>
                            <input type="password" name="client_secret" placeholder="Blank = keep current" autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label>Options</label>
                            <span class="check"><input type="checkbox" name="enabled" value="1" @checked($host->enabled)> Enabled</span>
                            <span class="check"><input type="checkbox" name="clear_secret" value="1"> Clear secret (make public)</span>
                        </div>
                    </div>
                    <div class="row-actions">
                        <button type="submit" class="btn">Save</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.foundry.destroy', $host) }}" onsubmit="return confirm('Remove this Foundry host?');" style="margin-top:.6rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--ghost">Remove</button>
                </form>
            </div>
        @empty
            <p class="empty">No database-configured hosts yet.</p>
        @endforelse

        <h2>Diagram Agent Models</h2>
        <div class="card ref">
            <p>Allowlist of Foundry AIP models the diagram agent may call via the OpenAI-compatible proxy <code>{{ $llmEndpoint }}</code>.</p>
            <p style="margin-top:.5rem;">Agent status: <strong>{{ $llmEnabled ? 'enabled' : 'disabled' }}</strong> (set via <code>FOUNDRY_LLM_ENABLED</code>). AIP must be enabled on the Foundry stack.</p>
            <p style="margin-top:.5rem;">Leave host blank to make a model available on every host. One default per host scope.</p>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('admin.foundry.models.store') }}">
                @csrf
                <div class="grid">
                    <div class="field">
                        <label for="m_model">Model ID</label>
                        <input type="text" id="m_model" name="model" value="{{ old('model') }}" placeholder="gpt-4o">
                    </div>
                    <div class="field">
                        <label for="m_display_name">Display name</label>
                        <input type="text" id="m_display_name" name="display_name" value="{{ old('display_name') }}" placeholder="GPT-4o">
                    </div>
                    <div class="field">
                        <label for="m_host_url">Host (blank = all)</label>
                        <input type="text" id="m_host_url" name="host_url" value="{{ old('host_url') }}" placeholder="https://yourstack.palantirfoundry.com">
                    </div>
                    <div class="field">
                        <label for="m_provider">Provider</label>
                        <input type="text" id="m_provider" name="provider" value="{{ old('provider', 'openai') }}" placeholder="openai">
                    </div>
                    <div class="field">
                        <label for="m_max">Max output tokens</label>
                        <input type="text" id="m_max" name="max_output_tokens" value="{{ old('max_output_tokens') }}" placeholder="(default)">
                    </div>
                    <div class="field">
                        <label for="m_temp">Temperature</label>
                        <input type="text" id="m_temp" name="temperature" value="{{ old('temperature') }}" placeholder="(default)">
                    </div>
                    <div class="field full">
                        <label for="m_desc">Description</label>
                        <input type="text" id="m_desc" name="description" value="{{ old('description') }}">
                    </div>
                    <div class="field">
                        <label>Options</label>
                        <span class="check"><input type="checkbox" name="enabled" value="1" checked> Enabled</span>
                        <span class="check"><input type="checkbox" name="is_default" value="1"> Default for host</span>
                    </div>
                    <div class="field">
                        <label for="m_sort">Sort order</label>
                        <input type="text" id="m_sort" name="sort_order" value="{{ old('sort_order', '0') }}">
                    </div>
                </div>
                <div class="row-actions">
                    <button type="submit" class="btn">Add Model</button>
                </div>
            </form>
        </div>

        @forelse ($models as $model)
            <div class="card">
                <div class="host-head">
                    <span class="host-url">{{ $model->display_name ?: $model->model }} <span class="ref">({{ $model->model }})</span></span>
                    <span>
                        @if ($model->is_default)<span class="badge badge--secret">Default</span>@endif
                        <span class="badge {{ $model->enabled ? 'badge--on' : 'badge--off' }}">{{ $model->enabled ? 'Enabled' : 'Disabled' }}</span>
                    </span>
                </div>
                <p class="ref" style="margin-bottom:.6rem;">Host: <code>{{ $model->host_url ?: 'all hosts' }}</code></p>
                <form method="POST" action="{{ route('admin.foundry.models.update', $model) }}">
                    @csrf
                    @method('PATCH')
                    <div class="grid">
                        <div class="field">
                            <label>Model ID</label>
                            <input type="text" name="model" value="{{ $model->model }}">
                        </div>
                        <div class="field">
                            <label>Display name</label>
                            <input type="text" name="display_name" value="{{ $model->display_name }}">
                        </div>
                        <div class="field">
                            <label>Host (blank = all)</label>
                            <input type="text" name="host_url" value="{{ $model->host_url }}">
                        </div>
                        <div class="field">
                            <label>Provider</label>
                            <input type="text" name="provider" value="{{ $model->provider }}">
                        </div>
                        <div class="field">
                            <label>Max output tokens</label>
                            <input type="text" name="max_output_tokens" value="{{ $model->max_output_tokens }}">
                        </div>
                        <div class="field">
                            <label>Temperature</label>
                            <input type="text" name="temperature" value="{{ $model->temperature }}">
                        </div>
                        <div class="field full">
                            <label>Description</label>
                            <input type="text" name="description" value="{{ $model->description }}">
                        </div>
                        <div class="field">
                            <label>Options</label>
                            <span class="check"><input type="checkbox" name="enabled" value="1" @checked($model->enabled)> Enabled</span>
                            <span class="check"><input type="checkbox" name="is_default" value="1" @checked($model->is_default)> Default for host</span>
                        </div>
                        <div class="field">
                            <label>Sort order</label>
                            <input type="text" name="sort_order" value="{{ $model->sort_order }}">
                        </div>
                    </div>
                    <div class="row-actions">
                        <button type="submit" class="btn">Save</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.foundry.models.destroy', $model) }}" onsubmit="return confirm('Remove this model?');" style="margin-top:.6rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--ghost">Remove</button>
                </form>
            </div>
        @empty
            <p class="empty">No agent models configured yet.</p>
        @endforelse

        @if (count($envHosts))
            <h2>From Environment (read-only)</h2>
            <div class="card">
                @foreach ($envHosts as $env)
                    <div class="env-item">{{ $env['display_name'] }} — {{ $env['host_url'] }}</div>
                @endforeach
                <p class="ref" style="margin-top:.6rem;">These come from <code>FOUNDRY_HOSTS_JSON</code>. Add a matching host above to manage it here.</p>
            </div>
        @endif
    </main>
</body>
</html>
