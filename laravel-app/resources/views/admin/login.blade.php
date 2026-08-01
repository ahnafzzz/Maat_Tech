<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Access | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','sans-serif'],mono:['DM Mono','monospace']},colors:{tech:{300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488',950:'#042f2e'},cyber:{border:'#263141',panel:'#121722'}}}}}</script>
</head>
<body class="min-h-screen bg-[#050609] font-sans text-white">
    <div class="fixed inset-0 -z-10 opacity-70 [background-image:linear-gradient(rgba(38,49,65,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(38,49,65,.35)_1px,transparent_1px)] [background-size:32px_32px]"></div>
    <main class="mx-auto flex min-h-screen max-w-md items-center px-5">
        <form method="POST" action="{{ route('admin.login.store') }}" class="w-full border border-tech-800 bg-cyber-panel/95 p-7 shadow-2xl shadow-teal-950/20 sm:p-9">
            @csrf
            <div class="flex items-center gap-3 border-l-2 border-tech-500 pl-3">
                <img src="{{ asset('images/brand/maat-tech-logo.png') }}" alt="MAAT TECHNOLOGIE BD" class="h-12 w-14 shrink-0 object-contain object-left [filter:drop-shadow(0_0_6px_rgba(45,212,191,.25))]">
                <div><p class="font-mono text-sm font-bold tracking-wider">ADMIN_ACCESS_TERMINAL</p><p class="font-mono text-[9px] tracking-[.16em] text-tech-400">CLEARANCE REQUIRED</p></div>
            </div>
            <p class="mt-7 text-sm leading-6 text-slate-400">Restricted system control. Authenticate with a valid administrative identifier to continue.</p>
            @if($errors->any())<p class="mt-5 border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">{{ $errors->first() }}</p>@endif
            <label class="mt-6 block font-mono text-[10px] text-tech-300">ADMIN_ID<input name="admin_id" required pattern="ADM-[0-9]{4}-[A-Z]" placeholder="ADM-0001-Z" value="{{ old('admin_id') }}" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm text-white outline-none focus:border-tech-500"></label>
            <label class="mt-5 block font-mono text-[10px] text-tech-300">PASSWORD<input type="password" name="password" required class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm text-white outline-none focus:border-tech-500"></label>
            <button class="mt-7 flex w-full items-center justify-center gap-2 border border-tech-400 bg-tech-600 p-3 text-sm font-mono hover:bg-tech-500"><i data-lucide="terminal" class="h-4 w-4"></i>EXECUTE_LOGIN</button>
            <p class="mt-5 text-center font-mono text-[10px] text-slate-500">RATE_LIMITED / SESSION_GUARDED / AUDIT_READY</p>
        </form>
    </main>
    <script>lucide.createIcons()</script>
</body>
</html>
