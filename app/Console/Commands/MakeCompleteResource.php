<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeCompleteResource extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:complete {name : The name of the model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a model, migration, factory, seeder, and resource in one command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // Get the configuration values from config/makecomplete.php
        $config = config('makecomplete');

        // Create Model and Migration if enabled
        if ($config['model']) {
            $this->call('make:model', [
                'name' => $name,
                '--migration' => $config['migration'],
            ]);
        }

        // Create Controller and Migration if enabled
        if ($config['controller']) {
            $this->call('make:controller', [
                'name' => $name . 'Controller',
            ]);
        }

        // Create Factory if enabled
        if ($config['factory']) {
            $this->call('make:factory', [
                'name' => $name . 'Factory',
            ]);
        }

        // Create Seeder if enabled
        if ($config['seeder']) {
            $this->call('make:seeder', [
                'name' => $name . 'Seeder',
            ]);
        }

        // Create Resource if enabled
        if ($config['resource']) {
            $this->call('make:resource', [
                'name' => $name . 'Resource',
            ]);
        }

        $this->info("Components for {$name} created successfully.");
    }
}
