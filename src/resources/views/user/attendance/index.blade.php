@extends('layouts.app')

@section('title', '勤怠一覧')

@section('content')
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold mb-6 border-l-4 border-black pl-2">勤怠一覧</h1>

        <div class="flex items-center justify-between bg-white p-4 rounded-md shadow mb-6">
            <form method="GET" action="{{ route('attendance.index') }}" class="flex items-center justify-between w-full">
                <button type="submit" name="month" value="{{ $prevMonth->format('Y-m') }}"
                    class="text-sm text-gray-600 hover:text-black flex items-center">
                    <img src="{{ asset('images/icons/left-arrow.svg') }}" alt="前月" class="w-4 h-4 mr-1">前月
                </button>

                <div class="text-lg font-semibold flex items-center gap-2">
                    <img src="{{ asset('images/icons/calendar.svg') }}" alt="calendar" class="w-5 h-5">
                    {{ $currentMonth->format('Y/m') }}
                </div>

                <button type="submit" name="month" value="{{ $nextMonth->format('Y-m') }}"
                    class="text-sm text-gray-600 hover:text-black flex items-center">
                    翌月<img src="{{ asset('images/icons/right-arrow.svg') }}" alt="翌月" class="w-4 h-4 ml-1">
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-md shadow text-base text-center leading-6">
                <thead class="border-b-2 border-gray-200 text-gray-500 font-semibold tracking-wide">
                    <tr>
                        <th class="py-3 px-4 ">
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
                    @foreach ($attendanceData as $record)
                        <tr class="border-b border-gray-200 text-gray-500 font-semibold tracking-wider">
                            <td class="py-3 px-4 ">
                                <div class="inline-block w-[90px] text-left">{{ $record['date'] }}</div>
                            </td>
                            <td class="py-3 px-4">{{ $record['start'] }}</td>
                            <td class="py-3 px-4">{{ $record['end'] }}</td>
                            <td class="py-3 px-4">{{ $record['break'] }}</td>
                            <td class="py-3 px-4">{{ $record['work'] }}</td>
                            <td class="py-3 px-4">
                                @if ($record['id'])
                                    <a href="{{ route('attendance.show', $record['id']) }}"
                                       class="font-bold text-black hover:underline">詳細</a>
                                @else
                                    <a href="{{ route('attendance.create', ['date' => $record['work_date']]) }}"
                                       class="font-bold text-black hover:underline">詳細</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
