<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [

            /*
            |------------------------------------------------------------------
            | DASHBOARD
            |------------------------------------------------------------------
            */
            [
                'key'        => 'dashboard',
                'label'      => 'Dashboard',
                'icon'       => 'fas fa-tachometer-alt',
                'route'      => '/',
                'section'    => 'dashboard',
                'sort_order' => 1,
            ],

            /*
            |------------------------------------------------------------------
            | MASTER DATA
            |------------------------------------------------------------------
            */
            [
                'key'        => 'customer',
                'label'      => 'Customer',
                'icon'       => 'fas fa-building',
                'route'      => '/customer',
                'section'    => 'master',
                'sort_order' => 10,
            ],
            [
                'key'        => 'project',
                'label'      => 'Project',
                'icon'       => 'fas fa-project-diagram',
                'route'      => '/cluster',
                'section'    => 'master',
                'sort_order' => 11,
            ],
            [
                'key'        => 'milestone',
                'label'      => 'Milestone',
                'icon'       => 'fas fa-stream',
                'route'      => '/stage',
                'section'    => 'master',
                'sort_order' => 12,
            ],
            [
                'key'        => 'beneficiaries',
                'label'      => 'Beneficiaries',
                'icon'       => 'fas fa-user-friends',
                'route'      => '/beneficiaries',
                'section'    => 'master',
                'sort_order' => 13,
            ],
            [
                'key'        => 'account',
                'label'      => 'Account',
                'icon'       => 'fas fa-list',
                'route'      => '/account',
                'section'    => 'master',
                'sort_order' => 15,  // setelah user-access (14)
            ],
            [
                'key'        => 'master-pic',
                'label'      => 'PIC',
                'icon'       => 'fas fa-id-badge',
                'route'      => '/master-pic',
                'section'    => 'master',
                'sort_order' => 16,
            ],
            [
                'key'        => 'source-fund',
                'label'      => 'Source Fund',
                'icon'       => 'fas fa-piggy-bank',
                'route'      => '/source-fund',
                'section'    => 'master',
                'sort_order' => 17,
            ],
            [
                'key'        => 'team',
                'label'      => 'Team',
                'icon'       => 'fas fa-sitemap',
                'route'      => '/team',
                'section'    => 'master',
                'sort_order' => 18,
            ],
            [
                'key'        => 'user-access',
                'label'      => 'User & Access',
                'icon'       => 'fas fa-users',
                'route'      => '/user',
                'section'    => 'master',
                'sort_order' => 14,
            ],

            /*
            |------------------------------------------------------------------
            | FINANCE MANAGEMENT
            |------------------------------------------------------------------
            */
            [
                'key'        => 'finance-summary',
                'label'      => 'Summary',
                'icon'       => 'fas fa-chart-pie',
                'route'      => '/finance-summary',
                'section'    => 'finance',
                'sort_order' => 20,
            ],
            [
                'key'        => 'list-po',
                'label'      => 'List PO',
                'icon'       => 'fas fa-file-contract',
                'route'      => '/project',
                'section'    => 'finance',
                'sort_order' => 21,
            ],
            [
                'key'        => 'finance-budgeting',
                'label'      => 'Budgeting',
                'icon'       => 'fas fa-money-check-alt',
                'route'      => '/budget-plan?from=finance',
                'section'    => 'finance',
                'sort_order' => 22,
            ],
            [
                'key'        => 'finance-cost-request',
                'label'      => 'Cost Request',
                'icon'       => 'fas fa-file-invoice-dollar',
                'route'      => '/budget-request',
                'section'    => 'finance',
                'sort_order' => 23,
            ],
            [
                'key'        => 'finance-daily-transaction',
                'label'      => 'Daily Transaction',
                'icon'       => 'fas fa-layer-group',
                'route'      => '/daily-transaction',
                'section'    => 'finance',
                'sort_order' => 24,
            ],
            [
                'key'        => 'cash-management',
                'label'      => 'Cash Management',
                'icon'       => 'fas fa-wallet',
                'route'      => '/cash-management',
                'section'    => 'finance',
                'sort_order' => 25,
            ],
            [
                'key'        => 'invoice-cash-receipt',
                'label'      => 'Invoice & Cash Receipt',
                'icon'       => 'fas fa-receipt',
                'route'      => '/invoice-cash-receipt',
                'section'    => 'finance',
                'sort_order' => 24,
            ],
            [
                'key'        => 'reports',
                'label'      => 'Reports',
                'icon'       => 'fas fa-chart-bar',
                'route'      => '/reports',
                'section'    => 'finance',
                'sort_order' => 25,
            ],
            [
                'key'        => 'forecast',
                'label'      => 'Finance Forecast',
                'icon'       => 'fas fa-binoculars',
                'route'      => '/forecast',
                'section'    => 'finance',
                'sort_order' => 26,
            ],

            /*
            |------------------------------------------------------------------
            | GENERAL — modul lintas divisi, tidak digerbang per section finance/project
            |------------------------------------------------------------------
            */
            [
                'key'        => 'feedback-inbox',
                'label'      => 'Feedback Inbox (CEO)',
                'icon'       => 'fas fa-inbox',
                'route'      => '/feedback-inbox',
                'section'    => 'general',
                'sort_order' => 40,
            ],

            /*
            |------------------------------------------------------------------
            | PROJECT MANAGEMENT
            |------------------------------------------------------------------
            */
            [
                'key'        => 'project-summary',
                'label'      => 'Summary',
                'icon'       => 'fas fa-chart-pie',
                'route'      => '/service-delivery-summary',
                'section'    => 'project',
                'sort_order' => 30,
            ],
            [
                'key'        => 'project-monitoring',
                'label'      => 'Project Monitoring',
                'icon'       => 'fas fa-binoculars',
                'route'      => '/project-monitoring',
                'section'    => 'project',
                'sort_order' => 33,
            ],

        ];

        foreach ($menus as $menu) {
            DB::table('menu_items')->updateOrInsert(
                ['key' => $menu['key']],
                array_merge($menu, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Menu items seeded: ' . count($menus) . ' items');
    }
}