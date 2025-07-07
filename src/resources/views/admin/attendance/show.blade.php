@extends('layouts.app')

@section('title', '勤怠詳細')

@php
    /* 既存勤怠なら実 ID、無い場合は new を渡し
 → POST /admin/attendance/{id|new}/fix へ飛ばす */
    $actionId = $attendance && $attendance->exists ? $attendance->id : 'new';
@endphp

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">

            {{-- ───── タイトル ───── --}}
            <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">勤怠詳細</h1>
            {{-- ───── 成功メッセージ ───── --}}
            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ───── バリデーションエラー ───── --}}
            @if ($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach (collect($errors->all())->unique() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif



            {{-- ───── 入力フォーム ───── --}}
            <div class="bg-white rounded-md shadow overflow-hidden">
                <form id="attendance-form" method="POST" action="{{ route('admin.attendance.fix', $actionId) }}"
                    class="space-y-0" novalidate>
                    @csrf

                    {{-- 勤務モード hidden --}}
                    <input type="hidden" name="mode" value="{{ $mode === 'edit' && $endAt ? 'finished' : 'working' }}">

                    {{-- 管理者：スタッフ ID を必須送信 --}}
                    <input type="hidden" name="user_id" value="{{ $attendance?->user_id ?? request('user_id') }}">

                    {{-- 既存勤怠なら hidden で送る --}}
                    @if ($attendance && $attendance->exists)
                        <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
                    @endif

                    {{-- ─ 名前 ─ --}}
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap">名前</div>
                        <div class="w-full sm:w-[17rem] font-bold">{{ $userName }}</div>
                    </div>

                    {{-- ─ 日付 ─ --}}
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10 ">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">日付</div>
                        <div class="justify-between w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                            <span class="px-4 py-1 text-center">{{ $workDate->year }}年</span>
                            <span class="px-4 py-1 text-center">{{ $workDate->format('n月j日') }}</span>
                            <input type="hidden" name="work_date" value="{{ $workDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    {{-- ─ 出勤・退勤 ─ --}}
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">出勤・退勤</div>
                        <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                            <input type="time" name="start_at" value="{{ old('start_at', $startAt) }}"
                                class="border rounded px-2 py-1 w-full sm:w-32 text-center appearance-none"
                                @if (!empty($pendingRequest)) readonly @endif>
                            <span class="self-center">〜</span>
                            <input type="time" name="end_at" value="{{ old('end_at', $endAt) }}"
                                class="border rounded px-2 py-1 w-full sm:w-32 text-center appearance-none"
                                @if (!empty($pendingRequest)) readonly @endif>
                        </div>
                    </div>

                    {{-- ─ 動的 休憩欄 ─ --}}
                    <div id="break-sections"></div>

                    {{-- ─ 備考 ─ --}}
                    <div class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                        <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">備考</div>
                        <div class="w-full sm:w-[17rem] flex items-center">
                            <textarea name="reason" rows="2" class="mt-2 sm:mt-0 border rounded w-full px-3 py-2"
                                @if ($hasPendingRequest) readonly @endif></textarea>
                        </div>
                    </div>
                </form>
            </div>

            {{-- ───── 送信ボタン ───── --}}
            {{-- ───── 送信ボタン ───── --}}
            <div class="text-right mt-6">
                @if (!empty($pendingRequest))
                    {{-- 申請中の修正申請がある場合のメッセージとリンク --}}
                    <p class="mb-2 text-sm text-gray-700">

                        <a href="{{ route('admin.request.approve', $pendingRequest->id) }}"
                            class="text-blue-700 underline">
                            承認待ちの修正申請があります
                        </a>
                    </p>
                    {{-- 修正不可ボタン（無効化） --}}
                    <button type="button" disabled class="bg-gray-400 text-white font-semibold px-12 py-2 rounded">
                        修正
                    </button>
                @else
                    {{-- 修正／登録ボタン --}}
                    <button type="submit" form="attendance-form"
                        class="bg-black text-white font-semibold px-12 py-2 rounded hover:bg-gray-800">
                        {{ $mode === 'edit' ? '修正' : '登録' }}
                    </button>
                @endif
            </div>


        </div>
    </div>


    {{-- ───── JS：未来日アラート確認 ───── --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('attendance-form');
            form.addEventListener('submit', function (e) {
                const workDate = new Date("{{ $workDate->format('Y-m-d') }}");
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (workDate > today) {
                    const confirmed = confirm('未来の日付に対して勤怠登録を行おうとしています。\nこのまま続行してもよろしいですか？');
                    if (!confirmed) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>

    {{-- ───── JS：休憩行の動的追加 ───── --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('break-sections');
            const initialBreaks = @json(old('breaks', $breaks ?? []));
            const isReadonly = @json(!empty($pendingRequest));

            /* テンプレ行 ---------- */
            const makeRow = idx => {
                const row = document.createElement('div');
                row.className =
                    'border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10';
                row.innerHTML = `
            <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">
                ${idx === 0 ? '休憩' : `休憩${idx + 1}`}
            </div>
            <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                <input type="time" name="breaks[${idx}][start]"
                       class="break-start border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? 'readonly' : ''}>
                <span class="self-center">〜</span>
                <input type="time" name="breaks[${idx}][end]"
                       class="break-end border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? 'readonly' : ''}>
            </div>`;
                return row;
            };

            /* 既存表示 ---------- */
            initialBreaks.forEach((v, i) => {
                const r = makeRow(i);
                r.querySelector('.break-start').value = v.start ?? '';
                r.querySelector('.break-end').value = v.end ?? '';
                container.appendChild(r);
            });
            if (container.children.length === 0) container.appendChild(makeRow(0));

            /* 行整理 ---------- */
            const renumber = () => {
                [...container.children].forEach((row, i) => {
                    row.querySelector('.text-gray-500').textContent = i === 0 ? '休憩' : `休憩${i + 1}`;
                    row.querySelector('.break-start').name = `breaks[${i}][start]`;
                    row.querySelector('.break-end').name = `breaks[${i}][end]`;
                });
            };

            const tidy = () => {
                /* 途中の空行削除（末尾除く） */
                for (let i = container.children.length - 2; i >= 0; i--) {
                    const row = container.children[i];
                    if (!row.querySelector('.break-start').value &&
                        !row.querySelector('.break-end').value) {
                        row.remove();
                    }
                }
                /* 末尾が入力済みなら空行を追加 */
                const last = container.lastElementChild;
                if (last && last.querySelector('.break-start').value &&
                    last.querySelector('.break-end').value) {
                    container.appendChild(makeRow(container.children.length));
                }
                renumber();
            };

            if (!isReadonly) {
                container.addEventListener('input', tidy);
            }
        });
    </script>
@endsection
