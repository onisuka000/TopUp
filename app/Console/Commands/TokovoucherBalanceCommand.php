<?php

namespace App\Console\Commands;

use App\Services\TokovoucherService;
use Illuminate\Console\Command;

class TokovoucherBalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokovoucher:balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Tokovoucher member balance and verify API connectivity';

    /**
     * Execute the console command.
     */
    public function handle(TokovoucherService $tokovoucherService): int
    {
        $this->info('Checking Tokovoucher member balance...');

        $result = $tokovoucherService->checkBalance();

        if (($result['status'] ?? 0) != 1) {
            $this->error('Failed to get balance: ' . ($result['error_msg'] ?? $result['message'] ?? json_encode($result)));
            return Command::FAILURE;
        }

        $data = $result['data'] ?? [];
        $this->info('--- Tokovoucher Member Info ---');
        $this->line('Member Code : ' . ($data['member_code'] ?? 'N/A'));
        $this->line('Member Name : ' . ($data['nama'] ?? 'N/A'));
        $this->line('Saldo       : Rp ' . number_format($data['saldo'] ?? 0, 0, ',', '.'));

        return Command::SUCCESS;
    }
}
