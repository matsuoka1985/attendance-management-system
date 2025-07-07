<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Document</title>
    <style>
        input[type="time"]::-webkit-calendar-picker-indicator {
            display: none;
            -webkit-appearance: none;
        }
    </style>
</head>

<body>
    <header class="bg-black text-white relative z-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- ロゴ -->
                <div class="flex-shrink-0">
                    @php
                        if (!auth()->check()) {
                            $homeUrl = route('login');
                        } elseif (auth()->guard('admin')->check()) {
                            $homeUrl = route('admin.attendance.index');
                        } else {
                            $homeUrl = route('attendance.stamp');
                        }
                    @endphp
                    <a href="{{ $homeUrl }}">
                        <img class="h-10 max-h-[44px] w-auto max-w-[180px] sm:max-w-[240px] mr-2 sm:mr-4"
                            src="/images/logo.svg" alt="Coachtech Logo">

                    </a>
                </div>


                @php
                    use Illuminate\Support\Str;

                    $user = auth()->user();
                    $path = request()->path();
                    $showMenu = true;
                    $menus = [];

                    // ログイン・登録・メール認証中はヘッダーメニュー非表示
                    if (request()->is('login') || request()->is('register') || request()->is('email/verify*')) {
                        $showMenu = false;
                    }

                    // 未ログインユーザも非表示（2段階保険）
                    if (!$user) {
                        $showMenu = false;
                    }

                    // 管理者向けメニュー
                    if ($showMenu && request()->is('admin/*')) {
                        $menus = [
                            ['label' => '勤怠一覧', 'href' => route('admin.attendance.index')],
                            ['label' => 'スタッフ一覧', 'href' => route('admin.staff.index')],
                            ['label' => '申請一覧', 'href' => route('admin.request.index')],
                        ];
                        //一般スタッフが退勤後かつ勤怠登録画面にいる場合のメニュー
                    } elseif ($showMenu && request()->is('attendance') &&
                    $user->isClockedOutToday()
                    // false
                    ) {
                        $menus = [
                            ['label' => '今月の出勤一覧', 'href' => route('attendance.index')],
                            ['label' => '申請一覧', 'href' => route('request.index')],
                        ];
                        //一般スタッフのメニュー
                    } else {
                        $menus = [
                            ['label' => '勤怠', 'href' => route('attendance.stamp')],
                            ['label' => '勤怠一覧', 'href' => route('attendance.index')],
                            ['label' => '申請', 'href' => route('request.index')],
                        ];
                    }

                @endphp

                @if ($showMenu)
                    <!-- PCメニュー -->
                    <nav class="hidden md:flex items-center space-x-6">
                        @foreach ($menus as $menu)
                            <a href="{{ $menu['href'] }}" class="hover:underline">{{ $menu['label'] }}</a>
                        @endforeach
                        <form method="POST"
                            action="{{ Auth::guard('admin')->check() || Auth::user()?->isAdmin() ? route('admin.logout') : route('logout') }}">
                            @csrf
                            <button type="submit" class="hover:underline">ログアウト</button>
                        </form>
                    </nav>

                    <!-- ハンバーガーメニュー -->
                    <div class="md:hidden flex items-center">
                        <button id="mobile-menu-button" class="text-white focus:outline-none">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- モバイルメニュー -->
        @if ($showMenu)
            <div id="mobile-menu"
                class="hidden absolute top-16 left-0 w-full bg-black text-white px-4 py-6 space-y-4 z-50 transition-all duration-300 transform -translate-y-4 opacity-0 pointer-events-none">
                <div class="flex flex-col space-y-4">
                    @foreach ($menus as $menu)
                        <a href="{{ $menu['href'] }}"
                            class="block text-center bg-white text-black px-3 py-2 rounded hover:bg-gray-200">
                            {{ $menu['label'] }}
                        </a>
                    @endforeach
                    <form method="POST"
                        action="{{ Auth::guard('admin')->check() || Auth::user()?->isAdmin() ? route('admin.logout') : route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="block w-full text-center bg-white text-black px-3 py-2 rounded hover:bg-gray-200">
                            ログアウト
                        </button>
                    </form>
                </div>
            </div>

            <script>
                const menuBtn = document.getElementById('mobile-menu-button');
                const menu = document.getElementById('mobile-menu');

                menuBtn?.addEventListener('click', () => {
                    const isVisible = !menu.classList.contains('hidden');
                    if (isVisible) {
                        menu.classList.add('opacity-0', '-translate-y-4');
                        menu.classList.remove('opacity-100', 'translate-y-0');
                        setTimeout(() => {
                            menu.classList.add('hidden', 'pointer-events-none');
                        }, 200);
                    } else {
                        menu.classList.remove('hidden', 'pointer-events-none');
                        setTimeout(() => {
                            menu.classList.remove('opacity-0', '-translate-y-4');
                            menu.classList.add('opacity-100', 'translate-y-0');
                        }, 10);
                    }
                });

                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 768) {
                        menu.classList.add('hidden', 'opacity-0', '-translate-y-4', 'pointer-events-none');
                        menu.classList.remove('opacity-100', 'translate-y-0');
                    }
                });
            </script>
        @endif
    </header>

    @yield('content')
</body>

</html>
