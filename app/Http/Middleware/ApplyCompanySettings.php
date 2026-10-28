<?php

namespace App\Http\Middleware;

use App\Models\CompanySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyCompanySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = CompanySettings::query()->find(1);

        if ($settings) {
            config([
                'app.name' => $settings->company_name,
                'app.timezone' => $settings->timezone,
                'company.currency' => $settings->currency,
                'company.date_format' => $settings->date_format,
                'company.working_days' => $settings->working_days,
                'company.work_start' => $settings->work_start,
                'company.work_end' => $settings->work_end,
            ]);
            view()->share('companySettings', $settings);

            if (in_array($settings->timezone, timezone_identifiers_list(), true)) {
                date_default_timezone_set($settings->timezone);
            }
        }

        return $next($request);
    }
}