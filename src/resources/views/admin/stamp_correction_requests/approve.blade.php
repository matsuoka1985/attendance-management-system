{{-- resources/views/admin/stamp_correction_requests/approve.blade.php --}}
@extends('layouts.app')

@section('title', '修正申請 承認画面')

@php
    /* -------------------------------------------------------------
       タブ定義（承認待ち／承認済みで文言を切り替え）
       - base    : 承認待ち → 現在の確定ログ
                   承認済み → 承認前の打刻データ
       - preview : 承認待ち → 申請内容
                   承認済み → 申請による変更
    ------------------------------------------------------------- */
    $tabs = [
        'base' => [
            'title' => $isPending ? '現在の打刻データ' : '承認前の打刻データ',
            'data' => $base,
            'editable' => false, // 参照のみ
        ],
        'preview' => [
            'title' => $isPending ? '申請内容' : '申請による変更',
            'data' => $preview,
            'editable' => false, // 参照のみ
        ],
    ];
@endphp

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">

            {{-- ───────── タイトル ───────── --}}
            <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">勤怠詳細</h1>
            {{-- ───── 成功メッセージ ───── --}}
            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ───────── 差分バナー ───────── --}}
            @if (!empty($diffs))
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                    <p class="font-semibold text-blue-700 mb-1">
                        {{ $isPending ? '申請中の変更' : '申請による変更' }}
                    </p>
                    <ul class="text-sm text-blue-800 leading-6 space-y-0.5">
                        @foreach ($diffs as $diffEntry)
                            <li>
                                {{ $diffEntry['label'] }} :
                                <span class="line-through text-gray-400 mr-1">{{ $diffEntry['old'] ?: '—' }}</span>
                                <span class="text-red-600 font-semibold">→ {{ $diffEntry['new'] ?: '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ───────── 後続修正申請リンク ───────── --}}
            @if ($newerRequests->isNotEmpty())
                <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                    <p class="font-semibold text-yellow-700 mb-2">この勤怠には後続の修正申請があります：</p>
                    <ul class="text-sm space-y-1">
                        @foreach ($newerRequests as $request)
                            <li>
                                <a href="{{ route('admin.request.approve', $request->id) }}" class="text-blue-700 underline">
                                    {{ $request->created_at->format('Y/m/d H:i') }} に提出された
                                    {{ $request->status === 'pending' ? '未承認の' : '承認済みの' }}修正申請
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ───────── Alpine タブ ───────── --}}
            <div x-data="{ tab: 'base' }">
                {{-- タブヘッダ --}}
                <nav class="mb-4 flex space-x-0 text-sm font-semibold border-b border-gray-300">
                    @foreach ($tabs as $key => $meta)
                        <button class="pb-3 border-b-2 transition-colors w-40 text-center "
                            :class="tab === '{{ $key }}' ?
                                'text-black border-black' :
                                'text-gray-500 border-transparent'"
                            x-on:click="tab='{{ $key }}'">
                            {{ $meta['title'] }}
                        </button>
                    @endforeach
                </nav>

                {{-- タブコンテンツ --}}
                @foreach ($tabs as $key => $meta)
                    <div x-show="tab==='{{ $key }}'" x-cloak>
                        <div class="bg-white rounded-md shadow overflow-hidden space-y-0">

                            {{-- 名前 --}}
                            <div
                                class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                                <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">名前</div>
                                <div class="w-full sm:w-[17rem] font-bold">{{ $userName }}</div>
                            </div>

                            {{-- 日付 --}}
                            <div
                                class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                                <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">日付</div>
                                <div class="w-full sm:w-[17rem]  flex justify-between flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                                    <span class="px-4 py-1 text-center">{{ $workDate->year }}年</span>
                                    <span class="px-4 py-1 text-center">{{ $workDate->format('n月j日') }}</span>
                                </div>
                            </div>

                            @php($tabData = $meta['data'])

                            {{-- 出勤・退勤 --}}
                            <div
                                class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                                <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">出勤・退勤</div>
                                <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                                    <input type="time" value="{{ $tabData['start'] }}" disabled
                                        class="border rounded px-2 py-1 w-full sm:w-32 text-center">
                                    <span class="self-center">〜</span>
                                    <input type="time" value="{{ $tabData['end'] }}" disabled
                                        class="border rounded px-2 py-1 w-full sm:w-32 text-center">
                                </div>
                            </div>

                            {{-- 休憩行 --}}
                            @foreach ($tabData['breaks'] as $breakIndex => $breakData)
                                @if ($breakIndex === count($tabData['breaks']) - 1 && $breakData['start'] === '' && $breakData['end'] === '')
                                    @continue
                                @endif
                                <div
                                    class="border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                                    <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">
                                        {{ $breakIndex === 0 ? '休憩' : '休憩' . ($breakIndex + 1) }}
                                    </div>
                                    <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                                        <input type="time" value="{{ $breakData['start'] }}" disabled
                                            class="border rounded px-2 py-1 w-full sm:w-32 text-center">
                                        <span class="self-center">〜</span>
                                        <input type="time" value="{{ $breakData['end'] }}" disabled
                                            class="border rounded px-2 py-1 w-full sm:w-32 text-center">
                                    </div>
                                </div>
                            @endforeach

                            {{-- 備考 --}}
                            <div class="py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10">
                                <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">備考</div>
                                <div class="w-full sm:w-[17rem] flex items-center">
                                    <textarea rows="2" disabled class="border rounded w-full px-3 py-2">{{ $reason }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- アクションボタン --}}
                <div class="text-right mt-8">
                    @if ($isPending)
                        <form method="POST" action="{{ route('admin.request.approve.execute', $correctionRequest->id) }}">
                            @csrf
                            <button type="submit"
                                class="bg-black text-white font-semibold px-12 py-2 rounded hover:bg-gray-800">
                                承認
                            </button>
                        </form>
                    @else
                        <button type="button"
                            class="bg-gray-400 text-white font-semibold px-12 py-2 rounded cursor-default" disabled>
                            承認済み
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
