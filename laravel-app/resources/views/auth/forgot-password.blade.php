<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','sans-serif'],mono:['DM Mono','monospace']},colors:{tech:{300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488'},cyber:{border:'#263141',panel:'#121722'}}}}}</script>
</head>
<body class="min-h-screen bg-[#090b10] font-sans text-white">
    <div class="fixed inset-0 -z-10 opacity-70 [background-image:linear-gradient(rgba(38,49,65,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(38,49,65,.35)_1px,transparent_1px)] [background-size:42px_42px]"></div>

    <main class="mx-auto flex min-h-screen max-w-md items-center px-5">
        <form method="POST" action="{{ route('password.email') }}" class="w-full border border-cyber-border bg-cyber-panel/90 p-7 shadow-2xl shadow-black/40 sm:p-9">
            @csrf
            <h1 class="font-mono text-lg">MAAT TECHNOLOGIE BD</h1>
            <p class="mt-2 text-sm text-slate-400">Enter your email to receive a secure password reset link.</p>

            @if (session('status'))
                <p class="mt-5 border border-emerald-700 bg-emerald-950/40 p-3 text-sm text-emerald-200">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-5 border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">{{ $errors->first() }}</p>
            @endif

            <label class="mt-6 block font-mono text-[10px] text-slate-400">EMAIL_ADDRESS<input type="email" name="email" required value="{{ old('email') }}" class="mt-2 w-full border border-cyber-border bg-[#090d14] p-3 font-sans text-sm outline-none focus:border-tech-500"></label>

            <button class="mt-6 w-full border border-tech-400 bg-tech-600 p-3 text-sm font-mono hover:bg-tech-500">SEND_RESET_LINK</button>
            <p class="mt-4 text-center text-sm text-slate-500"><a href="{{ route('login') }}" class="text-tech-300 hover:text-tech-400">Back to login</a></p>
        </form>
    </main>
</body>
</html>
