<?php

namespace Database\Seeders;

use App\Models\DefaultSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = [
            'referral_registration_bonus_amount' => 10.00, // every referral
            'referral_withdrawal_bonus_percentage' => 2.00, // every withdrawal
            'deposit_bkash_account' => '01700000000', 
            'deposit_rocket_account' => '01800000000',
            'deposit_nagad_account' => '01900000000',
            'min_deposit_amount' => 100.00, // every deposit
            'max_deposit_amount' => 10000.00, // every deposit
            'withdrawal_balance_deposit_charge_percentage' => 2.00, // every deposit
            'instant_withdraw_charge' => 10.00, // every withdrawal
            'withdraw_charge_percentage' => 20.00, // every withdrawal
            'min_withdraw_amount' => 500.00, // every withdrawal
            'max_withdraw_amount' => 10000.00, // every withdrawal
            'task_posting_charge_percentage' => 5.00, // every task
            'task_posting_additional_screenshot_charge' => 2.50, // every screenshot
            'task_posting_boosted_time_charge' => 5.00, // every 15 minutes
            'task_posting_additional_running_day_charge' => 2.50, // every day
            'task_posting_min_budget' => 100.00, // every task
            'task_proof_max_bonus_amount' => 20.00, // every proof
            'task_proof_monthly_free_review_time' => 30, // times
            'task_proof_additional_review_charge' => 0.25, // every review
            'task_proof_status_auto_approved_time' => 72, // hours
            'task_proof_status_rejected_charge_auto_refund_time' => 72, // hours
            'user_max_blocked_time' => 3, // times
        ];

        DefaultSetting::create($setting);

        $this->command->info('Default settings added successfully.');

        return;
    }
}
