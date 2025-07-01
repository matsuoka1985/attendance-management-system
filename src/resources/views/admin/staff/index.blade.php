{{-- resources/views/admin/staff/index.blade.php --}}
@extends('layouts.app')

@section('title', 'スタッフ一覧')

@section('content')
    <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">

        <div class="max-w-3xl mx-auto">
            {{-- 見出し ――――――――――――――――――――――――――――――――――― --}}
            <h1 class="text-2xl font-bold mb-8 border-l-4 border-black pl-2">
                スタッフ一覧
            </h1>

            {{-- 一覧テーブル ――――――――――――――――――――――――――――― --}}
            <div class="bg-white rounded-md shadow overflow-x-auto">
                <table class="min-w-full text-center">
                    <thead class="bg-white border-b-2 border-gray-200 text-gray-500 font-semibold tracking-wide">
                        <tr>
                            <th class="py-3 px-2 font-semibold">名前</th>
                            <th class="py-3 px-2 font-semibold">メールアドレス</th>
                            <th class="py-3 px-2 font-semibold">月次勤怠</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($staffs as $staff)
                            <tr class="hover:bg-gray-50 text-gray-500 font-semibold ">
                                {{-- 名前 --}}
                                <td class="py-3 px-2">{{ $staff->name }}</td>

                                {{-- メール --}}
                                <td class="py-3 px-2 break-all">{{ $staff->email }}</td>

                                {{-- 詳細ボタン --}}
                                <td class="py-3 px-2">
                                    <a
                                        href="{{ route('admin.staff_attendance.index', $staff->id) }}"
                                        class="text-black hover:underline"
                                    >
                                        詳細
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-gray-500">
                                    スタッフが登録されていません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
