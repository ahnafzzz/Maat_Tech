<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','sans-serif'],mono:['DM Mono','monospace']},colors:{tech:{300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488'},cyber:{border:'#263141',panel:'#121722'}}}}}</script>
</head>
<body class="min-h-screen bg-[#090b10] font-sans text-white">
    <div class="fixed inset-0 -z-10 opacity-70 [background-image:linear-gradient(rgba(38,49,65,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(38,49,65,.35)_1px,transparent_1px)] [background-size:42px_42px]"></div>

    <main class="mx-auto flex min-h-screen max-w-md items-center px-5">
        <form method="POST" action="{{ route('register.store') }}" class="w-full border border-cyber-border bg-cyber-panel/90 p-7 shadow-2xl shadow-black/40 sm:p-9">
            @csrf

            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center border border-tech-400 bg-tech-600"><i data-lucide="cpu" class="h-5 w-5"></i></span>
                <span>
                    <strong class="block font-mono text-lg">MAAT TECHNOLOGIE BD</strong>
                    <small class="font-mono text-[9px] tracking-[.18em] text-tech-400">ACCOUNT_CREATION</small>
                </span>
            </a>

            <div class="mt-8">
                <p class="font-mono text-xs tracking-[.2em] text-tech-400">REGISTER</p>
                <h1 class="mt-2 text-2xl font-bold">Create your customer account.</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">Your account lets you track orders, save addresses, and manage profile security.</p>
            </div>

            @if ($errors->any())
                <p class="mt-5 border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">{{ $errors->first() }}</p>
            @endif

            <label class="mt-6 block font-mono text-[10px] text-slate-400">FULL_NAME<input name="name" required value="{{ old('name') }}" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500"></label>
            <label class="mt-4 block font-mono text-[10px] text-slate-400">EMAIL_ADDRESS<input type="email" name="email" required value="{{ old('email') }}" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500"></label>
            <label class="mt-4 block font-mono text-[10px] text-slate-400">PASSWORD<input type="password" name="password" required class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500"></label>
            <label class="mt-4 block font-mono text-[10px] text-slate-400">CONFIRM_PASSWORD<input type="password" name="password_confirmation" required class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500"></label>

            <button class="mt-6 flex w-full items-center justify-center gap-2 border border-tech-400 bg-tech-600 p-3 text-sm font-mono hover:bg-tech-500"><i data-lucide="user-plus" class="h-4 w-4"></i>CREATE_ACCOUNT</button>

            <p class="mt-5 text-center text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="text-tech-300 hover:text-tech-400">Login</a></p>
        </form>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
