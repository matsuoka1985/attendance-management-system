@extends('layouts.app')

@section('title', '勤怠詳細')

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">

        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">勤怠詳細</h1>
            @if ($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach (collect($errors->all())->unique() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{-- 申請中の変更（差分だけ） --}}
            @if (!empty($diffs))
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                    <p class="font-semibold text-blue-700 mb-1">申請中の変更</p>
                    <ul class="text-sm text-blue-800 leading-6 space-y-0.5">
                        @foreach ($diffs as $d)
                            <li>
                                {{ $d['label'] }} :
                                <span class="line-through text-gray-400 mr-1">{{ $d['old'] ?? '—' }}</span>
                                <span class="text-red-600 font-semibold">→ {{ $d['new'] ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>

                </div>
            @endif


            <div class="bg-white rounded-md shadow overflow-hidden">
                <form method="POST" action="{{ route('request.store') }}" id="attendance-form" class="space-y-0">
                    @csrf
                    {{-- 編集モードでこのページを閲覧して(後日の勤怠登録ではないということ。)$endAtが埋まっている時はfinishedという文字列を送る。 --}}
                    <input type="hidden" name="mode" value="{{ $mode === 'edit' && $endAt ? 'finished' : 'working' }}">


                    @if ($attendance?->id)
                        <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
                    @endif

                    <!-- 名前 -->
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">名前</div>
                        <div class="w-full sm:w-[17rem] font-bold">{{ $userName }}</div>
                    </div>

                    <!-- 日付 -->
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">日付</div>
                        <div class="justify-between w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                            <span class="px-4 py-1 text-center">{{ $workDate->year }}年</span>
                            <span class="px-4 py-1 text-center">{{ $workDate->format('n月j日') }}</span>
                            <!-- ★ hidden で送信用 -->
                            <input type="hidden" name="work_date" value="{{ $workDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- 出勤・退勤 -->
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">出勤・退勤</div>
                        <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                            <input type="time" name="start_at" {{-- value="{{ old('start_at', optional($attendance?->start_at)->format('H:i')) }}" --}}
                                value="{{ old('start_at', $startAt) }}"
                                class="border rounded px-2 py-1 w-full sm:w-32 text-center appearance-none"
                                @if ($hasPendingRequest) readonly @endif>
                            <span class="self-center">〜</span>
                            <input type="time" name="end_at" {{-- value="{{ old('end_at', optional($attendance?->end_at)->format('H:i')) }}" --}} value="{{ old('end_at', $endAt) }}"
                                class="border rounded px-2 py-1 w-full sm:w-32 text-center appearance-none"
                                @if ($hasPendingRequest) readonly @endif>
                        </div>
                    </div>

                    <!-- 動的な休憩入力欄 -->
                    <div id="break-sections"
                         data-initial-breaks='@json(old("breaks", $breaks ?? []))'
                         data-is-readonly="{{ $hasPendingRequest ? 'true' : 'false' }}"></div>

                    <!-- 備考 -->
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">備考</div>
                        <div class="w-full sm:w-[17rem] flex items-center">
                            <textarea name="reason" class="mt-2 sm:mt-0 border rounded w-full px-3 py-2" rows="2" @if($hasPendingRequest) readonly @endif>{{ old('reason', $reason) }}</textarea>
                        </div>
                    </div>


                </form>
            </div>

            <!-- 送信ボタン（formの外） -->
            <div class="text-right mt-6">
                @if ($hasPendingRequest)
                    <p class="text-sm text-red-500">＊承認待ちのため修正はできません。</p>
                @else
                    <button type="submit" form="attendance-form"
                        class="bg-black text-white font-semibold px-12 py-2 rounded hover:bg-gray-800">
                        {{ $mode === 'edit' ? '修正' : '登録' }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    @vite('resources/js/app.js')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('attendance-form');
            form.addEventListener('submit', (e) => {
                const dateStr = form.querySelector('input[name="work_date"]')?.value;
                if (!dateStr) return;

                const todayStr = new Date().toISOString().slice(0, 10);
                if (dateStr > todayStr) {
                    const proceed = confirm('未来の日付に対して勤怠登録申請をしようとしています。本当によろしいですか？');
                    if (!proceed) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>

@endsection
