<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data seeding is disabled in production.');
        }

        $salesManager = User::query()
            ->where('email', 'sales.manager@company.com')
            ->where('role', 'SALES_MANAGER')
            ->where('status', 'ACTIVE')
            ->first();
        if (!$salesManager || !$salesManager->department || $salesManager->department->code !== 'SALES') {
            $this->command?->warn('Expected seeded Sales Manager is unavailable. Skipping opportunity seeding.');

            return;
        }

        $customer = Customer::query()->where('email', 'contact@northstar.test')->first();
        if (!$customer) {
            $customer = Customer::query()->firstOrCreate(
                ['email' => 'contact@opportunity-demo.test'],
                [
                    'customer_code' => 'CUST-DEMO-OPP-001',
                    'name' => 'Opportunity Demo Customer',
                    'company_name' => 'Opportunity Demo Ltd',
                    'city' => 'Yangon',
                    'country' => 'Myanmar',
                    'assigned_to' => $salesManager->id,
                    'status' => 'ACTIVE',
                    'customer_type' => 'NEW',
                ],
            );
        }
        if (!$customer->assigned_to) {
            throw new \RuntimeException('Demo opportunity customer has no assigned Sales user.');
        }

        $opportunities = [
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0001',
                'title' => 'Software License Deal',
                'description' => 'Enterprise software license for a demo customer.',
                'estimated_value' => 25000,
                'probability' => 30,
                'expected_close_date' => '2026-11-20',
                'actual_close_date' => null,
                'stage' => 'NEW',
                'status' => 'OPEN',
                'lost_reason' => null,
            ],
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0002',
                'title' => 'Annual Support Contract',
                'description' => 'Yearly technical support and maintenance demo opportunity.',
                'estimated_value' => 15000,
                'probability' => 60,
                'expected_close_date' => '2026-10-30',
                'actual_close_date' => null,
                'stage' => 'QUALIFIED',
                'status' => 'OPEN',
                'lost_reason' => null,
            ],
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0003',
                'title' => 'Website Development',
                'description' => 'Corporate website project for sales workflow demonstration.',
                'estimated_value' => 35000,
                'probability' => 75,
                'expected_close_date' => '2026-11-10',
                'actual_close_date' => null,
                'stage' => 'PROPOSAL',
                'status' => 'OPEN',
                'lost_reason' => null,
            ],
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0004',
                'title' => 'Cloud Migration Project',
                'description' => 'Infrastructure migration project for sales workflow demonstration.',
                'estimated_value' => 50000,
                'probability' => 90,
                'expected_close_date' => '2026-10-15',
                'actual_close_date' => null,
                'stage' => 'NEGOTIATION',
                'status' => 'OPEN',
                'lost_reason' => null,
            ],
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0005',
                'title' => 'Cybersecurity Assessment',
                'description' => 'Completed demo assessment opportunity.',
                'estimated_value' => 12000,
                'probability' => 100,
                'expected_close_date' => '2026-09-15',
                'actual_close_date' => '2026-09-15',
                'stage' => 'WON',
                'status' => 'CLOSED',
                'lost_reason' => null,
            ],
            [
                'number' => 'OPP-DEMO-EXTRA-2026-0006',
                'title' => 'Hardware Upgrade Package',
                'description' => 'Closed demo opportunity that was not won.',
                'estimated_value' => 18000,
                'probability' => 0,
                'expected_close_date' => '2026-08-15',
                'actual_close_date' => '2026-08-15',
                'stage' => 'LOST',
                'status' => 'CLOSED',
                'lost_reason' => 'Demo record: budget deferred.',
            ],
        ];

        foreach ($opportunities as $sample) {
            $opportunity = Opportunity::query()->firstOrCreate(
                ['opportunity_number' => $sample['number']],
                [
                    'customer_id' => $customer->id,
                    'assigned_to' => $salesManager->id,
                    'title' => $sample['title'],
                    'description' => $sample['description'],
                    'estimated_value' => $sample['estimated_value'],
                    'probability' => $sample['probability'],
                    'expected_close_date' => $sample['expected_close_date'],
                    'actual_close_date' => $sample['actual_close_date'],
                    'stage' => $sample['stage'],
                    'status' => $sample['status'],
                    'lost_reason' => $sample['lost_reason'],
                ],
            );

            if ((int) $opportunity->customer_id !== (int) $customer->id
                || (int) $opportunity->assigned_to !== (int) $salesManager->id) {
                throw new \RuntimeException("Demo opportunity {$sample['number']} has inconsistent customer or owner links.");
            }
        }
    }
}
