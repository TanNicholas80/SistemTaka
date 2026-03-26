<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable SSL verification globally for all Http:: calls (dev environment on Windows)
        Http::globalOptions([
            'verify' => false,
        ]);
        // Event listener untuk menyimpan kode_customer ke activity log
        Activity::created(function ($activity) {
            // Ambil kode_customer dari properties atau dari subject model
            $kodeCustomer = null;

            // Coba ambil dari properties
            if ($activity->properties && isset($activity->properties['kode_customer'])) {
                $kodeCustomer = $activity->properties['kode_customer'];
            }
            // Jika tidak ada di properties, coba ambil dari subject model
            elseif ($activity->subject && method_exists($activity->subject, 'getAttribute')) {
                $kodeCustomer = $activity->subject->kode_customer ?? null;
            }
            // Jika masih tidak ada, ambil dari session active_branch
            if (!$kodeCustomer) {
                $activeBranchId = session('active_branch');
                if ($activeBranchId) {
                    $branch = \App\Models\Branch::find($activeBranchId);
                    if ($branch) {
                        $kodeCustomer = $branch->customer_id;
                    }
                }
            }

            // Update activity log dengan kode_customer
            if ($kodeCustomer) {
                $activity->update(['kode_customer' => $kodeCustomer]);
            }
        });
    }
}
