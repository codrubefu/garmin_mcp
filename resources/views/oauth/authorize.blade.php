<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect Garmin</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; background: #f5f4f0; color: #222; margin: 0; display: grid; place-items: center; min-height: 100vh; }
        main { background: #fff; padding: 2rem; border-radius: 12px; width: min(22rem, calc(100vw - 2rem)); box-sizing: border-box; box-shadow: 0 1px 4px #0002; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        label { display: block; margin: 1rem 0 .25rem; font-weight: 600; }
        input { width: 100%; box-sizing: border-box; padding: .6rem; font: inherit; border: 1px solid #bbb; border-radius: 8px; }
        button { width: 100%; margin-top: 1rem; padding: .6rem; font: inherit; font-weight: 600; color: #fff; background: #222; border: 0; border-radius: 8px; cursor: pointer; }
        .error { color: #b00020; margin: 1rem 0 0; }
    </style>
</head>
<body>
<main>
    <h1>Connect your Garmin data</h1>
    <p>This gives <strong>{{ $host }}</strong> read access to your Garmin data.</p>
    @if ($error)
        <p class="error" role="alert">{{ $error }}</p>
    @endif
    <form method="post" action="{{ route('oauth.authorize') }}">
        @csrf
        @foreach ($params as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required autofocus>
        <button type="submit">Allow access</button>
    </form>
</main>
</body>
</html>
