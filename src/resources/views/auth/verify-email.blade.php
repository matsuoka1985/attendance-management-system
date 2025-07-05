{{-- resources/views/auth/verify-email.blade.php --}}
@extends('layouts.app')

@php
    // .env で MAIL_CLIENT_URL を設定（無ければボタン非表示）
    $mailUrl = config('app.mail_client_url');
@endphp

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center py-12">

        {{-- 上部のロゴバーは layouts.app に含まれる想定 --}}

        <div class="w-full max-w-lg text-center space-y-10">

            {{-- フラッシュメッセージ --}}
            @if (session('status') === 'verification-link-sent')
                <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-2 rounded">
                    認証メールを再送しました。
                </div>
            @endif

            {{-- メッセージ --}}
            <p class="leading-relaxed font-bold">
                登録していただいたメールアドレスに認証メールを送付しました。<br>
                メール認証を完了してください。
            </p>

            {{-- 認証はこちらから（開発環境用） --}}
            @if ($mailUrl)
                <a href="{{ $mailUrl }}" target="_blank"
                   class="inline-block border border-black bg-gray-300 rounded-md px-10 py-3 font-semibold hover:bg-gray-400 transition">
                    認証はこちらから
                </a>
            @endif

            {{-- 認証メール再送 --}}
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit"
                        class="text-blue-600 text-sm hover:text-blue-800">
                    認証メールを再送する
                </button>
            </form>

        </div>
    </div>
@endsection
