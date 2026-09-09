<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Administrator invitation | MAAT TECHNOLOGIE BD</title>
    <style>
        :root { color-scheme: dark; font-family: system-ui, sans-serif; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #050609; color: #e2e8f0; }
        main { width: min(26rem, calc(100% - 3rem)); border: 1px solid #263141; background: #101521; padding: 2rem; }
        h1 { margin: 0 0 1rem; font-size: 1.5rem; } p { color: #94a3b8; line-height: 1.55; }
        label { display: block; margin-top: 1rem; color: #99f6e4; font-size: .8rem; }
        input { box-sizing: border-box; width: 100%; margin-top: .4rem; padding: .8rem; border: 1px solid #263141; background: #090d14; color: white; }
        button { width: 100%; margin-top: 1.4rem; padding: .9rem; border: 1px solid #2dd4bf; background: #0d9488; color: white; cursor: pointer; }
        .error { border: 1px solid #9f1239; background: #4c0519; color: #fecdd3; padding: .8rem; }
    </style>
</head>
<body>
<main>
    <h1>Administrator invitation</h1>
    @if($expired)
        <p class="error">This invitation has expired. Ask a lead administrator to send a replacement link.</p>
    @else
        <p>Set the password for <strong>{{ $invitation->proposed_admin_id }}</strong>, issued to {{ $invitation->email }}. This invitation creates an operator account and does not grant lead privileges.</p>
        @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
        <form method="POST" action="{{ route('admin.invitations.accept', ['selector' => $selector]) }}">
            @csrf
            <input type="hidden" name="token" id="invitation-token" value="{{ $token }}">
            <label>PASSWORD<input type="password" name="password" required autocomplete="new-password"></label>
            <label>CONFIRM PASSWORD<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
            <button>ACTIVATE ADMINISTRATOR</button>
        </form>
    @endif
</main>
@if(! $expired)
<script>
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    document.getElementById('invitation-token').value ||= fragment.get('token') || '';
    history.replaceState(null, '', window.location.pathname);
</script>
@endif
</body>
</html>
