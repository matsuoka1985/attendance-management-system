<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class StaffController extends Controller
{
    /**
     * スタッフ一覧を表示
     * GET /admin/staff/list   （route 名: admin.staff.index）
     */
    public function index(Request $request)
    {
        /** スタッフ一覧を取得（管理者を除外したい場合は適宜 where を追加） */
        $staffs = User::query()
            ->when(
                Schema::hasColumn('users', 'is_admin'),
                fn($q) => $q->where('is_admin', 0)
            )
            ->orderBy(
                Schema::hasColumn('users', 'name_kana') ? 'name_kana' : 'name'
            )
            ->get(['id', 'name', 'email']);

        return view('admin.staff.index', compact('staffs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
