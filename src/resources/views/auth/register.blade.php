@extends('layouts.app')

@section('title', '会員登録')

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-center mb-10">会員登録</h1>



        <form method="POST" action="{{ route('register') }}" class="w-full max-w-lg" novalidate>
            @csrf

            {{-- 名前 --}}
            <div class="mb-6">
                <label for="name" class="block text-sm font-bold mb-2">名前</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required autofocus>
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- メールアドレス --}}
            <div class="mb-6">
                <label for="email" class="block text-sm font-bold mb-2">メールアドレス</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required>
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- パスワード --}}
            <div class="mb-6">
                <label for="password" class="block text-sm font-bold mb-2">パスワード</label>
                <input id="password" name="password" type="password"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required autocomplete="new-password">
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- パスワード確認 --}}
            <div class="mb-16">
                <label for="password_confirmation" class="block text-sm font-bold mb-2">パスワード確認</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                    class="block w-full rounded-lg border border-black px-4 py-2
                    focus:outline-none focus:ring-2 focus:ring-black"
                    required>
            </div>

            {{-- 登録ボタン --}}
            <div>
                <button type="submit"
                    class="w-full bg-black hover:bg-gray-800 text-white font-semibold py-2 rounded-md transition-colors">
                    登録する
                </button>
            </div>
        </form>

        {{-- ログインリンク --}}
        <p class="mt-6 text-sm text-center">
            <a href="{{ route('login') }}" class="text-blue-600 hover:underline">ログインはこちら</a>
        </p>
    </div>
@endsection
