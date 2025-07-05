{{-- resources/views/user/stamp_correction_requests/index.blade.php --}}
@extends('layouts.app')

@section('title', '申請一覧')

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            {{-- ───────────────── ヘッドライン ───────────────── --}}
            <h1 class="text-2xl font-bold mb-8 border-l-4 border-black pl-2">申請一覧</h1>

            {{-- ───── 成功メッセージ ───── --}}
            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ───────────────── Alpine ―──────────────── --}}
            <div x-data="{ tab: 'pending' }">
                {{-- タブ --}}
                <nav class="mb-6 flex border-b border-gray-300 text-sm font-semibold">
                    <button x-on:click="tab = 'pending'" class="pb-3 px-8 border-b-2 transition-colors"
                        :class="tab === 'pending' ? 'text-black border-black' : 'text-gray-500 border-transparent'">
                        承認待ち
                    </button>
                    <button x-on:click="tab = 'approved'" class="pb-3 px-8 border-b-2 transition-colors"
                        :class="tab === 'approved' ? 'text-black border-black' : 'text-gray-500 border-transparent'">
                        承認済み
                    </button>
                </nav>

                {{-- ================ 承認待ち ================ --}}
                <div x-show="tab === 'pending'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-md shadow text-center text-sm sm:text-base">
                            <thead class="border-b-2 border-gray-200 text-gray-500 font-semibold">
                                <tr>
                                    <th class="py-3 px-4">状態</th>
                                    <th class="py-3 px-4">名前</th>
                                    <th class="py-3 px-4">対象日</th>
                                    <th class="py-3 px-4">申請理由</th>
                                    <th class="py-3 px-4">申請日時</th>
                                    <th class="py-3 px-4">詳細</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingRequests as $req)
                                    <tr class="border-b border-gray-200 text-gray-500 font-semibold">
                                        <td class="py-3 px-2 sm:px-4">承認待ち</td>
                                        <td class="py-3 px-2 sm:px-4">{{ $req->user->name }}</td>
                                        <td class="py-3 px-2 sm:px-4">
                                            {{ $req->target_date ? \Carbon\Carbon::parse($req->target_date)->format('Y/m/d') : '-' }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4 truncate max-w-[9rem] sm:max-w-none">
                                            {{ Str::limit($req->reason, 24) }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4">{{ $req->created_at->format('Y/m/d') }}</td>
                                        <td class="py-3 px-2 sm:px-4 text-black">
                                            {{-- 詳細リンク --}}
                                            @if ($req->attendance_id)
                                                <a href="{{ route('attendance.show', $req->attendance_id) }}"
                                                    class="hover:underline">詳細</a>
                                            @else
                                                <a href="{{ route('attendance.create', [
                                                    'date' => $req->target_date,
                                                ]) }}"
                                                    class="hover:underline">詳細</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-gray-500">承認待ちの申請はありません。</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ================ 承認済み ================ --}}
                <div x-show="tab === 'approved'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-md shadow text-center text-sm sm:text-base">
                            <thead class="border-b-2 border-gray-200 text-gray-500 font-semibold">
                                <tr>
                                    <th class="py-3 px-4">状態</th>
                                    <th class="py-3 px-4">名前</th>
                                    <th class="py-3 px-4">対象日</th>
                                    <th class="py-3 px-4">申請理由</th>
                                    <th class="py-3 px-4">申請日時</th>
                                    <th class="py-3 px-4">詳細</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($approvedRequests as $req)
                                    <tr class="border-b border-gray-200 text-gray-500 font-semibold">
                                        <td class="py-3 px-2 sm:px-4">
                                            {{ $req->status === 'approved' ? '承認' : '却下' }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4">{{ $req->user->name }}</td>
                                        <td class="py-3 px-2 sm:px-4">
                                            {{ $req->target_date ? \Carbon\Carbon::parse($req->target_date)->format('Y/m/d') : '-' }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4 truncate max-w-[9rem] sm:max-w-none">
                                            {{ Str::limit($req->reason, 24) }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4">
                                            {{ $req->applied_at?->format('Y/m/d') }}
                                        </td>
                                        <td class="py-3 px-2 sm:px-4 text-black">
                                            @if ($req->attendance_id)
                                                <a href="{{ route('attendance.show', $req->attendance_id) }}"
                                                    class="hover:underline">詳細</a>
                                            @else
                                                <a href="{{ route('attendance.create', [
                                                    'date' => $req->target_date,
                                                ]) }}"
                                                    class="hover:underline">詳細</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-gray-500">承認済みの申請はありません。</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
