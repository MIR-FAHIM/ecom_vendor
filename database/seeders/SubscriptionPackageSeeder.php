<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'short_description' => 'For small stores starting online',
                'description' => 'A simple package for neighborhood stores that need a public store page, products, inventory, and basic order tools.',
                'price' => 999,
                'currency' => 'BDT',
                'billing_cycle' => 'monthly',
                'trial_days' => 7,
                'max_products' => 100,
                'max_orders_per_month' => 500,
                'max_staff' => 2,
                'max_branches' => 1,
                'commission_rate' => 0,
                'is_featured' => false,
                'is_popular' => false,
                'status' => 'active',
                'sort_order' => 1,
                'features' => [
                    'Public store page',
                    'Unlimited categories',
                    'Product inventory management',
                    'Order management',
                    'Pickup settings',
                    'Basic reports',
                    'Store SEO URL',
                ],
                'metadata' => [
                    'target_store' => 'Neighborhood stores and new sellers',
                ],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'short_description' => 'For growing stores with more products and orders',
                'description' => 'A balanced package for stores that need higher limits, stronger reporting, and more operational room.',
                'price' => 1999,
                'currency' => 'BDT',
                'billing_cycle' => 'monthly',
                'trial_days' => 7,
                'max_products' => 500,
                'max_orders_per_month' => 2000,
                'max_staff' => 5,
                'max_branches' => 2,
                'commission_rate' => 0,
                'is_featured' => true,
                'is_popular' => true,
                'status' => 'active',
                'sort_order' => 2,
                'features' => [
                    'Public store page',
                    'Unlimited categories',
                    'Product inventory management',
                    'Order management',
                    'Pickup settings',
                    'Delivery settings',
                    'Promo code support',
                    'Advanced reports',
                    'Store SEO URL',
                ],
                'metadata' => [
                    'target_store' => 'Growing supermarkets, bakeries, fashion, and electronics stores',
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'short_description' => 'For high-volume stores and multi-branch merchants',
                'description' => 'A premium package for serious merchants who need larger capacity, staff access, branch support, and priority growth features.',
                'price' => 4999,
                'currency' => 'BDT',
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'max_products' => null,
                'max_orders_per_month' => null,
                'max_staff' => 15,
                'max_branches' => 5,
                'commission_rate' => 0,
                'is_featured' => true,
                'is_popular' => false,
                'status' => 'active',
                'sort_order' => 3,
                'features' => [
                    'Public store page',
                    'Unlimited products',
                    'Unlimited categories',
                    'Product inventory management',
                    'Order management',
                    'Pickup settings',
                    'Delivery settings',
                    'Multi-branch support',
                    'Staff management',
                    'Advanced reports',
                    'Store SEO URL',
                    'Priority support',
                ],
                'metadata' => [
                    'target_store' => 'High-volume stores and multi-branch merchants',
                ],
            ],
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::updateOrCreate(
                ['slug' => $package['slug']],
                $package
            );
        }
    }
}
