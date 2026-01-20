<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Support\GatewayClient;

class ListProjectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'october:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all projects from October CMS Gateway';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching projects...');

        try {
            $client = new GatewayClient();
            
            // Fetch projects
            $response = $client->listProjects();
            
            if (isset($response['data']) && is_array($response['data'])) {
                $headers = ['Project Name', 'Project ID'];
                $rows = [];
                
                foreach ($response['data'] as $project) {
                    $rows[] = [
                        $project['name'] ?? 'N/A',
                        $project['project_id'] ?? 'N/A',
                    ];
                }
                
                $this->table($headers, $rows);
            } else {
                $this->info('No projects found or unexpected response format.');
                if (!empty($response)) {
                    $this->info('Response keys: ' . implode(', ', array_keys($response)));
                }
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
