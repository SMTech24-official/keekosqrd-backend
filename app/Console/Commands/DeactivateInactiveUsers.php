<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Console\Command;

class DeactivateInactiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:deactivate-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate users who have not logged in for over a month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the date one month ago
        $thresholdDate = Carbon::now()->subMonth();

        // Find and update inactive users
        $affectedRows = User::where('last_login_at', '<', $thresholdDate)
            ->where('status', '!=', 'inactive') // Only update active users
            ->update(['status' => 'inactive']);

        // Output the result to the console
        $this->info("Successfully deactivated {$affectedRows} users.");
    }
}
