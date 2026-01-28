<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use App\Support\GatewayClient;

class GetProjectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateway:project {project_id : The Project ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get details for a specific project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->argument('project_id');
        $this->info("Fetching details for project {$projectId}...");

        try {
            $client = new GatewayClient();
            $project = $client->getProject($projectId);

            $this->info("Project Details:");
            
            $rows = [
                ['Name', $project['name'] ?? 'N/A'],
                ['ID', $project['project_id'] ?? 'N/A'],
                ['License', $project['license_key'] ?? 'N/A'],
                ['Status', $project['status'] ?? 'N/A'],
                ['Author', $project['author'] ?? 'N/A'],
                ['Created', $project['created_at'] ?? 'N/A'],
            ];

            $this->table(['Key', 'Value'], $rows);

            if (!empty($project['plugins'])) {
                $this->newLine();
                $this->info('Plugins:');
                foreach ($project['plugins'] as $plugin) {
                    $this->line("- $plugin");
                }
            }

            if (!empty($project['themes'])) {
                $this->newLine();
                $this->info('Themes:');
                foreach ($project['themes'] as $theme) {
                    $this->line("- $theme");
                }
            }

            if (!empty($project['domains'])) {
                $this->newLine();
                $this->info('Domains:');
                foreach ($project['domains'] as $domain) {
                    $this->line("- $domain");
                }
            }

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
