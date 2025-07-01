@extends('layouts.app')

@section('title', '勤怠一覧')

@section('content')
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">

        {{-- ───────── タイトル ───────── --}}
        <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">
            {{ $date->format('Y年n月j日') }}の勤怠
        </h1>

        {{-- ───────── 前日 / 当日 / 翌日 ───────── --}}
        <div class="flex items-center justify-between bg-white p-4 rounded-md shadow mb-10">
            <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex justify-between w-full">

                {{-- 前日 --}}
                <button name="date" value="{{ $prev }}"
                        class="text-sm text-gray-600 hover:text-black flex items-center gap-1">
                    <img src="{{ asset('images/icons/left-arrow.svg') }}" class="w-4 h-4">前日
                </button>

                {{-- 当日 --}}
                <span class="text-base sm:text-lg font-semibold flex items-center gap-2">
                    <img src="{{ asset('images/icons/calendar.svg') }}" class="w-5 h-5">
                    {{ $date->format('Y/m/d') }}
                </span>

                {{-- 翌日 --}}
                <button name="date" value="{{ $next }}"
                        class="text-sm text-gray-600 hover:text-black flex items-center gap-1">
                    翌日<img src="{{ asset('images/icons/right-arrow.svg') }}" class="w-4 h-4">
                </button>
            </form>
        </div>

        {{-- ───────── 勤怠テーブル ───────── --}}
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-md shadow text-center text-sm sm:text-base">
                <thead class="border-b-2 border-gray-200 text-gray-500 font-semibold tracking-wide">
                    <tr>
                        <th class="py-3 px-4">名前</th>
                        <th class="py-3 px-4">出勤</th>
                        <th class="py-3 px-4">退勤</th>
                        <th class="py-3 px-4">休憩</th>
                        <th class="py-3 px-4">合計</th>
                        <th class="py-3 px-4">詳細</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-200 text-gray-500 font-semibold tracking-wider">
                        <td class="py-3 px-4">{{ $row['name'] }}</td>
                        <td class="py-3 px-4">{{ $row['start'] }}</td>
                        <td class="py-3 px-4">{{ $row['end'] }}</td>
                        <td class="py-3 px-4">{{ $row['break'] }}</td>
                        <td class="py-3 px-4">{{ $row['total'] }}</td>
                        <td class="py-3 px-4">
                            <a href="
                            {{ route('admin.attendance.show', $row['id']) }}
                             "
                               class="font-bold text-black hover:underline">詳細</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
