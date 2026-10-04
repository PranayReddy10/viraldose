<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ site_name() }}</title>
    @vite(['resources/css/admin.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-900 px-4">
    <form method="post" action="{{ route('admin.login.attempt') }}" class="w-full max-w-sm rounded-lg bg-white p-8 shadow-xl">
        @csrf
        <p class="text-center text-2xl font-black"><span class="text-brand-600">Viral</span>Dose</p>
        <p class="mt-1 text-center text-sm text-ink-500">Sign in to the admin panel</p>
        @if($errors->any())<p class="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <div class="mt-6"><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input"></div>
        <div class="mt-4"><label class="label" for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password" class="input"></div>
        <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="rounded"> Remember me</label>
        <button class="btn-primary mt-6 w-full" type="submit">Sign in</button>
    </form>
</body>
</html>
