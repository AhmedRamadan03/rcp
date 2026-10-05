<?php

namespace App\Console\Commands;

use App\Models\MessageTemplate;
use App\Services\TargetService;
use Database\Seeders\MessageVariablesSeeder;
use Illuminate\Console\Command;

/**
 * Class RemoveDuplicationMessageTemplate
 *
 * Console command responsible for removing invalid MessageTemplate
 * records whose model does not match the official template definitions
 * defined in MessageVariablesSeeder.
 *
 * Logic:
 * - Loads the expected (title => model) mapping from MessageVariablesSeeder.
 * - Iterates through all MessageTemplate records.
 * - Ignores templates whose title does not exist in the seeder definitions.
 * - Compares each template's model with the expected model.
 * - Permanently deletes any template whose model does not match.
 *
 * Usage:
 *   php artisan app:remove-duplicate-message
 *
 * ⚠️ Warning:
 * This command permanently deletes invalid MessageTemplate records.
 * Ensure the database is backed up before running it in a production
 * environment.
 */
class RemoveDuplicationMessageTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-duplicate-message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate MessageTemplate records with the same title, keeping only the oldest one.';

    /**
     * Execute the console command.
     *
     * Steps:
     * 1. Load the expected (title => model) mapping from MessageVariablesSeeder.
     * 2. Retrieve all MessageTemplate records.
     * 3. Skip any template whose title is not defined in the seeder.
     * 4. Compare each template's model with the expected model.
     * 5. Delete templates whose model does not match the seeder definition.
     *
     * @return void
     */
    public function handle()
    {
        $definitions = collect((new MessageVariablesSeeder())->definitions())
            ->pluck('model', 'title');


        MessageTemplate::query()
            ->get()
            ->each(function (MessageTemplate $template) use ($definitions) {

                if (! $definitions->has($template->title)) {
                    return;
                }

                $expectedModel = $definitions[$template->title];

                if ($template->model !== $expectedModel) {
                    $this->info(
                        "Deleting template: {$template->title} ({$template->model})"
                    );

                    $template->delete();
                }
            });

        $this->info('Invalid templates removed successfully.');
    }
}