@extends('layouts.app')

@section('content')
    <main class="min-h-screen bg-white flex items-start justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">
            <!-- タイトル -->
            <h2 class="text-center text-2xl font-bold text-gray-900">ログイン</h2>
            <!-- フォーム -->
            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <!-- メールアドレス -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-bold text-gray-700">メールアドレス</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="mt-1 block w-full px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-400
                        border @error('email') border-red-500 @else border-gray-300 @enderror">
                    @error('email')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- パスワード -->
                <div class="mb-12">
                    <label for="password" class="block text-sm font-bold text-gray-700">パスワード</label>
                    <input id="password" name="password" type="password" required
                        class="mt-1 block w-full px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-400
                        border @error('password') border-red-500 @else border-gray-300 @enderror">
                    @error('password')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ログインボタン -->
                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 bg-red-400 hover:bg-red-500 text-white font-semibold rounded">
                        ログインする
                    </button>
                </div>
            </form>


            <!-- 会員登録リンク -->
            <p class="mt-4 text-center">
                <a href="{{ route('register') }}" class="text-sm text-blue-500 hover:underline">
                    会員登録はこちら
                </a>
            </p>
        </div>
    </main>
@endsection
