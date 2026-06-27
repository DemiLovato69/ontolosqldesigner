<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — SQL Designer</title>
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
            display: flex;
            flex-direction: column;
            text-transform: uppercase;
            -webkit-font-smoothing: antialiased;
        }
        header {
            background: #8f2f2f;
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
        }
        header span {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: .04em;
        }
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 6px;
            padding: 2rem;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        h1 {
            font-size: 18px;
            font-weight: 600;
            color: #8f2f2f;
            margin-bottom: 1.75rem;
            letter-spacing: .06em;
        }
        label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 6px;
            letter-spacing: .08em;
        }
        input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid #ccc;
            padding: 8px 0;
            color: #2c3e50;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            margin-bottom: 1.25rem;
            outline: none;
            transition: border-color .2s;
            text-transform: none;
        }
        input:focus { border-bottom-color: #8f2f2f; }
        .error {
            background: #fdf0f0;
            border: 1px solid #e0b0b0;
            color: #8f2f2f;
            border-radius: 4px;
            padding: 10px 14px;
            font-size: 12px;
            margin-bottom: 1.25rem;
        }
        button[type="submit"] {
            background: #8f2f2f;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 24px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .06em;
            cursor: pointer;
            transition: background .2s;
            text-transform: uppercase;
        }
        button[type="submit"]:hover { background: #7a2222; }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #999;
            font-size: 11px;
            letter-spacing: .08em;
            margin: 1.5rem 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        .divider span { padding: 0 .75rem; }
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px 24px;
            color: #2c3e50;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .06em;
            cursor: pointer;
            text-decoration: none;
            text-transform: uppercase;
            transition: background .2s, border-color .2s;
        }
        .google-btn:hover { background: #f5f5f5; border-color: #b0b0b0; }
        .google-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
    </style>
</head>
<body>
    <header>
        <span>SQL Designer — Admin</span>
    </header>

    <main>
        <div class="card">
            <h1>Login</h1>

            @if ($errors->has('credentials'))
                <div class="error">{{ $errors->first('credentials') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password">

                <button type="submit">Sign In</button>
            </form>

            <div class="divider"><span>or</span></div>

            <a class="google-btn" href="{{ route('admin.login.google') }}">
                <svg viewBox="0 0 48 48" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                Sign in with Google
            </a>
        </div>
    </main>
</body>
</html>
