<?php

namespace App\Providers;


use App\Models\User;
use Illuminate\Support\Facades\Gate;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Đăng ký Gate kiểm tra quyền admin
        Gate::define('change blog status', function (User $user) {
            // Kiểm tra xem người dùng có phải là admin không
            return $user->hasRole('admin');
        });
    }
}
