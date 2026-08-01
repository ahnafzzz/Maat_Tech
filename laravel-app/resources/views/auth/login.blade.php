<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Login | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','sans-serif'],mono:['DM Mono','monospace']},colors:{tech:{300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488',950:'#042f2e'},cyber:{border:'#263141',panel:'#121722'}}}}}</script>
</head>
<body class="min-h-screen bg-[#090b10] font-sans text-white">
    <div class="fixed inset-0 -z-10 opacity-70 [background-image:linear-gradient(rgba(38,49,65,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(38,49,65,.35)_1px,transparent_1px)] [background-size:42px_42px]"></div>

    <main class="mx-auto flex min-h-screen max-w-md items-center px-5">
        <form method="POST" action="{{ route('login.store') }}" class="w-full border border-cyber-border bg-cyber-panel/90 p-7 shadow-2xl shadow-black/40 sm:p-9">
            @csrf

            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center border border-tech-400 bg-tech-600"><i data-lucide="cpu" class="h-5 w-5"></i></span>
                <span>
                    <strong class="block font-mono text-lg">MAAT TECHNOLOGIE BD</strong>
                    <small class="font-mono text-[9px] tracking-[.18em] text-tech-400">CUSTOMER_PORTAL</small>
                </span>
            </a>

            <div class="mt-8">
                <p class="font-mono text-xs tracking-[.2em] text-tech-400">CUSTOMER_LOGIN</p>
                <h1 class="mt-2 text-2xl font-bold">Welcome back.</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">Sign in to manage orders, addresses, wishlist, and account security.</p>
            </div>

            @if (session('status'))
                <p class="mt-5 border border-emerald-700 bg-emerald-950/40 p-3 text-sm text-emerald-200">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-5 border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">{{ $errors->first() }}</p>
            @endif

            <label class="mt-6 block font-mono text-[10px] text-slate-400">
                EMAIL_ADDRESS
                <input type="email" name="email" required value="{{ old('email') }}" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500">
            </label>

            <label class="mt-4 block font-mono text-[10px] text-slate-400">
                PASSWORD
                <input id="customer-password" type="password" name="password" required class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500">
            </label>

            <div class="mt-3 flex items-center justify-between">
                <label class="flex items-center gap-2 text-xs text-slate-400"><input type="checkbox" name="remember" class="accent-teal-500">Remember this device</label>
                <button id="toggle-password" type="button" class="text-xs text-tech-300 hover:text-tech-200">Show password</button>
            </div>

            <div class="mt-3 text-right">
                <a href="{{ route('password.request') }}" class="text-xs text-slate-400 hover:text-tech-300">Forgot password?</a>
            </div>

            <button class="mt-6 flex w-full items-center justify-center gap-2 border border-tech-400 bg-tech-600 p-3 text-sm font-mono hover:bg-tech-500">
                <i data-lucide="log-in" class="h-4 w-4"></i>
                LOGIN
            </button>

            <p class="mt-5 text-center text-sm text-slate-500">No account yet? <a href="{{ route('register') }}" class="text-tech-300 hover:text-tech-400">Create one</a></p>
        </form>
    </main>

    <script>
        lucide.createIcons();

        (function () {
            var btn = document.getElementById('toggle-password');
            var field = document.getElementById('customer-password');
            if (!btn || !field) {
                return;
            }
            btn.addEventListener('click', function () {
                var visible = field.type === 'password';
                field.type = visible ? 'text' : 'password';
                btn.textContent = visible ? 'Hide password' : 'Show password';
            });
        })();
    </script>
</body>
</html>
