<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('storage:migrate-public-uploads')]
#[Description('Move legacy public uploads into their persistent public and private storage locations')]
class MigrateAnnouncementAttachments extends Command
{
    public function handle(): int
    {
        $publicRoot = Storage::disk('public')->path('');
        $privateRoot = Storage::disk('local')->path('');
        $appRoot = dirname($privateRoot);

        File::ensureDirectoryExists($publicRoot);
        File::ensureDirectoryExists($privateRoot);

        foreach ([
            [$appRoot.'/annonces', $privateRoot.'/annonces'],
            [$publicRoot.'/annonces', $privateRoot.'/annonces'],
            [$appRoot.'/cours', $publicRoot.'/cours'],
        ] as [$source, $destination]) {
            $this->moveDirectory($source, $destination);
        }

        $this->info('Les fichiers publics existants ont été migrés vers leurs espaces de stockage appropriés.');

        return self::SUCCESS;
    }

    private function moveDirectory(string $source, string $destination): void
    {
        if (! File::isDirectory($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        if (File::isDirectory($destination)) {
            if (! File::copyDirectory($source, $destination) || ! File::deleteDirectory($source)) {
                throw new RuntimeException("Impossible de migrer le répertoire {$source}.");
            }

            return;
        }

        if (! File::moveDirectory($source, $destination)) {
            throw new RuntimeException("Impossible de migrer le répertoire {$source}.");
        }
    }
}
