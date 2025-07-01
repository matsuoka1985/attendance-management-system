@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12">
  <div class="w-full max-w-md text-center space-y-6">
    <h2 class="text-xl font-bold">メールアドレスの確認</h2>
    <p>登録していただいたメールアドレスに認証メールを送付しました。メール認証を完了してください。</p>

    <form method="POST" action="{{ route('verification.send') }}">
      @csrf
      <button class="bg-red-400 hover:bg-red-500 text-white font-semibold py-2 px-4 rounded">
        認証メールを再送する
      </button>
    </form>
  </div>
</div>
@endsection
