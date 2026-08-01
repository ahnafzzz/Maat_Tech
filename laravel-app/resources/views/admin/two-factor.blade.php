<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Two-Factor Challenge | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','sans-serif'],mono:['DM Mono','monospace']},colors:{tech:{300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488',950:'#042f2e'},cyber:{border:'#263141',panel:'#121722'}}}}}</script>
</head>
<body class="min-h-screen bg-[#050609] font-sans text-white">
    <div class="fixed inset-0 -z-10 opacity-70 [background-image:linear-gradient(rgba(38,49,65,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(38,49,65,.35)_1px,transparent_1px)] [background-size:32px_32px]"></div>
    <main class="mx-auto flex min-h-screen max-w-md items-center px-5">
        <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="w-full border border-tech-800 bg-cyber-panel/95 p-7 shadow-2xl shadow-teal-950/20 sm:p-9">
            @csrf
            <h1 class="font-mono text-lg">ADMIN_TWO_FACTOR</h1>
            <p class="mt-3 text-sm text-slate-400">A verification code was sent to your admin email. Enter it below to finish sign-in.</p>
            @if (session('status'))
                <p class="mt-5 border border-emerald-700 bg-emerald-950/40 p-3 text-sm text-emerald-200">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="mt-5 border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">{{ $errors->first() }}</p>
            @endif
            <label class="mt-6 block font-mono text-[10px] text-tech-300">VERIFICATION_CODE<input name="code" required inputmode="numeric" maxlength="6" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm text-white outline-none focus:border-tech-500"></label>
            <button class="mt-7 flex w-full items-center justify-center gap-2 border border-tech-400 bg-tech-600 p-3 text-sm font-mono hover:bg-tech-500">VERIFY_ACCESS</button>
        </form>
    </main>
</body>
</html>
