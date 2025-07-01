@extends('layouts.app')

@section('title', '出勤')

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center py-24 px-4 sm:px-6 lg:px-8 bg-gray-100 text-center">
        {{-- フラッシュメッセージ --}}
        @if (session('success'))
            <div class="mb-4 w-full max-w-md bg-green-100 border border-green-400 text-green-800 px-4 py-2 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 w-full max-w-md bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded">
                {{ session('error') }}
            </div>
        @endif

        {{-- 状態バッジ --}}
        <div class="mb-4">
            <span class="inline-block text-xs bg-gray-200 text-gray-700 rounded-full px-3 py-1">
                @switch($status)
                    @case('not_working')
                        勤務外
                        @break
                    @case('working')
                        出勤中
                        @break
                    @case('on_break')
                        休憩中
                        @break
                    @case('finished')
                        退勤済
                        @break
                @endswitch
            </span>
        </div>

        {{-- 日付 --}}
        <p class="text-lg sm:text-xl font-light mb-2">
            {{ now()->isoFormat('YYYY年M月D日(ddd)') }}
        </p>

        {{-- 時刻 --}}
        <p id="clock" class="text-5xl sm:text-6xl font-extrabold mb-12">
            {{ now()->format('H:i') }}
        </p>

        {{-- コンテンツ分岐 --}}
        @switch($status)
            @case('not_working')
                <form method="POST" action="{{ route('attendance.start') }}">
                    @csrf
                    <button type="submit" id="clock-in-button"
                        class="w-36 sm:w-44 bg-black text-white font-bold py-3 rounded-xl transition hover:bg-gray-700 hover:scale-105 hover:shadow-lg transform duration-150 ease-in-out">
                        出勤
                    </button>
                </form>
                @break

            @case('working')
                <div class="flex gap-6 justify-center flex-wrap">
                    <form method="POST" action="{{ route('attendance.end') }}">
                        @csrf
                        <button type="submit"
                            class="w-36 sm:w-44 bg-black text-white font-bold py-3 rounded-xl transition hover:bg-gray-700 hover:scale-105 hover:shadow-lg transform duration-150 ease-in-out">
                            退勤
                        </button>
                    </form>
                    <form method="POST" action="{{ route('break.start') }}">
                        @csrf
                        <button type="submit"
                            class="w-36 sm:w-44 bg-white text-black font-bold py-3 rounded-xl transition hover:bg-gray-200 hover:scale-105 hover:shadow transform duration-150 ease-in-out">
                            休憩入
                        </button>
                    </form>
                </div>
                @break

            @case('on_break')
                <form method="POST" action="{{ route('break.end') }}">
                    @csrf
                    <button type="submit"
                        class="w-36 sm:w-44 bg-white text-black font-bold py-3 rounded-xl transition hover:bg-gray-200 hover:scale-105 hover:shadow transform duration-150 ease-in-out">
                        休憩戻
                    </button>
                </form>
                @break

            @case('finished')
                <p class="text-base font-semibold text-gray-800">お疲れ様でした。</p>
                @break
        @endswitch
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hh}:${mm}`;
        }

        // 初回即時実行
        updateClock();

        // 次の分頭までのミリ秒を計算
        const now = new Date();
        const delay = (60 - now.getSeconds()) * 1000 - now.getMilliseconds();

        setTimeout(() => {
            updateClock(); // 分頭に1回実行
            setInterval(updateClock, 60000); // 以降は毎分
        }, delay);
    </script>
@endsection
