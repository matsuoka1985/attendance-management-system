@extends('layouts.app')

@section('title', 'ログイン')

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-center mb-10">ログイン</h1>



        <form method="POST" action="{{ route('login') }}" class="w-full max-w-lg" novalidate>
            @csrf

            {{-- メールアドレス --}}
            <div class="mb-6">
                <label for="email" class="block text-sm font-bold mb-2">メールアドレス</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required autofocus>
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- パスワード --}}
            <div class="mb-16">
                <label for="password" class="block text-sm font-bold mb-2">パスワード</label>
                <input id="password" name="password" type="password"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required autocomplete="current-password">
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- ログインボタン --}}
            <div>
                <button type="submit"
                    class="w-full bg-black hover:bg-gray-800 text-white font-semibold py-2 rounded-md transition-colors">
                    ログインする
                </button>
            </div>
        </form>

        {{-- 会員登録リンク --}}
        <p class="mt-6 text-sm text-center">
            <a href="{{ route('register') }}" class="text-blue-600 hover:underline">会員登録はこちら</a>
        </p>
    </div>
@endsection
