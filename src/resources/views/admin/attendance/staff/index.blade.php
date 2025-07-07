{{-- resources/views/admin/attendance/staff/index.blade.php --}}
@extends('layouts.app')

@section('title', $staff->name . 'さんの勤怠')

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            {{-- ───────── タイトル ───────── --}}
            <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">
                {{ $staff->name }}さんの勤怠
            </h1>

            {{-- ───────── 前月 / 当月 / 翌月 ───────── --}}
            <div class="flex items-center justify-between bg-white p-4 rounded-md shadow mb-6">
                <form method="GET" action="
                  {{-- {{ route('staff_attendance.index', $staff->id) }} --}}
                   "
                    class="flex items-center justify-between w-full">

                    {{-- 前月 --}}
                    <button type="submit" name="month" value="{{ $prevMonth->format('Y-m') }}"
                        class="text-sm text-gray-600 hover:text-black flex items-center">
                        <img src="{{ asset('images/icons/left-arrow.svg') }}" alt="前月" class="w-4 h-4 mr-1">前月
                    </button>

                    {{-- 当月 --}}
                    <div class="text-lg font-semibold flex items-center gap-2">
                        <img src="{{ asset('images/icons/calendar.svg') }}" alt="calendar" class="w-5 h-5">
                        {{ $currentMonth->format('Y/m') }}
                    </div>

                    {{-- 翌月 --}}
                    <button type="submit" name="month" value="{{ $nextMonth->format('Y-m') }}"
                        class="text-sm text-gray-600 hover:text-black flex items-center">
                        翌月<img src="{{ asset('images/icons/right-arrow.svg') }}" alt="翌月" class="w-4 h-4 ml-1">
                    </button>
                </form>
            </div>

            {{-- ───────── 勤怠テーブル ───────── --}}
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-md shadow text-base text-center leading-6">
                    <thead class="border-b-2 border-gray-200 text-gray-500 font-semibold tracking-wide">
                        <tr>
                            <th class="py-3 px-4">
                                <div class="inline-block w-[90px] text-left">日付</div>
                            </th>
                            <th class="py-3 px-4">出勤</th>
                            <th class="py-3 px-4">退勤</th>
                            <th class="py-3 px-4">休憩</th>
                            <th class="py-3 px-4">合計</th>
                            <th class="py-3 px-4">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendanceData as $row)
                            <tr class="border-b border-gray-200 text-gray-500 font-semibold tracking-wider">
                                <td class="py-3 px-4">
                                    <div class="inline-block w-[90px] text-left">{{ $row['date'] }}</div>
                                </td>
                                <td class="py-3 px-4">{{ $row['start'] }}</td>
                                <td class="py-3 px-4">{{ $row['end'] }}</td>
                                <td class="py-3 px-4">{{ $row['break'] }}</td>
                                <td class="py-3 px-4">{{ $row['work'] }}</td>
                                <td class="py-3 px-4">
                                    @if ($row['id'])
                                        {{-- 既に勤怠がある日 → 詳細（編集）画面 --}}
                                        <a href="{{ route('admin.attendance.show', $row['id']) }}"
                                            class="font-bold text-black hover:underline">
                                            詳細
                                        </a>
                                    @else
                                        {{-- まだ勤怠が無い日 → 新規作成フォームへ（スタッフIDと日付をクエリで） --}}
                                        <a href="{{ route('admin.attendance.create', [
                                            'user_id' => $staff->id,
                                            'date' => $row['work_date'],
                                        ]) }}"
                                            class="font-bold text-black hover:underline">
                                            詳細
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ───────── CSV 出力ボタン ───────── --}}
            <div class="flex justify-end mt-10">
                <a href="{{ route('admin.staff_attendance.csv', [
                    'id' => $staff->id,
                    'month' => $currentMonth->format('Y-m'),
                ]) }}"
                    class="bg-black text-white font-semibold px-10 py-2 rounded hover:bg-gray-800">
                    CSV出力
                </a>
            </div>
        </div>
    </div>
@endsection
