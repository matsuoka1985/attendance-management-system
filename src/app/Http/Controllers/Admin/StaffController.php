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
        /** スタッフ一覧を取得 */
        $staffs = User::query()
            ->where('role', '!=', 'admin')
            ->orderBy(
                Schema::hasColumn('users', 'name_kana') ? 'name_kana' : 'name'
            )
            ->get(['id', 'name', 'email']);

        return view('admin.staff.index', compact('staffs'));
    }

}
