{{-- resources/views/auth/verification-complete.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12">
  <div class="w-full max-w-md text-center space-y-6">
    <h2 class="text-xl font-bold text-green-700">メール認証が完了しました</h2>
    <p>
        メールアドレスの確認が完了しました。<br>ご確認ありがとうございます。
    </p>

    <a href="{{ route('attendance.stamp') }}"
       class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded">
      出勤登録画面へ進む
    </a>
  </div>
</div>
@endsection
