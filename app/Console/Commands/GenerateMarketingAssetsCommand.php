<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoPhotoCatalog;
use App\Support\Marketing\MarketingImageFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sync curated Unsplash JPEGs into public/images/marketing/ (no remote hotlinks).
 * Falls back to GD silhouette compose only when a curated source file is missing.
 */
class GenerateMarketingAssetsCommand extends Command
{
    protected $signature = 'marketing:generate-assets {--force : Overwrite existing files}';

    protected $description = 'Sync curated marketing photo assets into public/images/marketing';

    public function handle(): int
    {
        $dir = public_path('images/marketing');
        File::ensureDirectoryExists($dir);

        $written = 0;

        foreach (MarketingImageFactory::catalog() as $key => $spec) {
            $path = $dir.DIRECTORY_SEPARATOR.$spec['file'];

            if (File::exists($path) && ! $this->option('force')) {
                $this->line("skip  {$spec['file']} (exists — use --force)");

                continue;
            }

            $sourcePath = isset($spec['source_file'])
                ? DemoPhotoCatalog::path((string) $spec['source_file'])
                : null;
            $fromCurated = is_string($sourcePath) && is_file($sourcePath) && filesize($sourcePath) > 1000;

            $binary = MarketingImageFactory::resolveBinary($spec);
            File::put($path, $binary);
            $kb = number_format(strlen($binary) / 1024, 1);
            $origin = $fromCurated ? 'photo' : 'gd-fallback';
            $this->info("wrote {$spec['file']} ({$kb} KB) [{$key}] via {$origin}");
            $written++;
        }

        $this->newLine();
        $this->info("Done. {$written} asset(s) written to public/images/marketing/");

        return self::SUCCESS;
    }
}
