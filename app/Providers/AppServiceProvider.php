<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FollowUpPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\ReminderPolicy;
use App\Policies\SalePolicy;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Register policies
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(FollowUp::class, FollowUpPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Reminder::class, ReminderPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
    }
}