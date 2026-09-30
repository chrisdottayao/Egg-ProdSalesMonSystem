<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Diagnoses "Could not connect to disk google ... File not found".
 * Run on the Railway cron service:  php artisan backup:drive-check
 * Add --create to create the missing backup subfolder, then re-run backup:run.
 */
class BackupDriveCheck extends Command
{
    protected $signature = 'backup:drive-check {--create : Create the backup subfolder if it is missing}';
    protected $description = 'Show which Google account/folder the backup disk really sees';

    public function handle(): int
    {
        $cfg  = config('filesystems.disks.google');
        $name = config('backup.backup.name');

        $this->line('Backup subfolder name expected (APP_NAME): "'.$name.'"');
        $this->line('GOOGLE_DRIVE_FOLDER_ID: '.($cfg['folderId'] ?: '(empty -> Drive root)'));

        $client = new GoogleClient();
        $client->setClientId($cfg['clientId']);
        $client->setClientSecret($cfg['clientSecret']);
        $client->refreshToken($cfg['refreshToken']);
        $drive = new GoogleServiceDrive($client);

        try {
            $about = $drive->about->get(['fields' => 'user(emailAddress,displayName)']);
            $this->info('Token belongs to: '.$about->getUser()->getEmailAddress());
        } catch (\Throwable $e) {
            $this->error('Token/credentials rejected: '.$e->getMessage());
            return self::FAILURE;
        }

        $rootId = $cfg['folderId'] ?: 'root';
        try {
            $root = $drive->files->get($rootId, ['fields' => 'id,name,mimeType,trashed', 'supportsAllDrives' => true]);
            $this->info('Root folder the app sees: "'.$root->getName().'"'.($root->getTrashed() ? ' (IN TRASH!)' : ''));
        } catch (\Throwable $e) {
            $this->error('Cannot open folder '.$rootId.' with this account (wrong account, or folder not shared with it): '.$e->getMessage());
            return self::FAILURE;
        }

        $kids = $drive->files->listFiles([
            'q' => "'{$rootId}' in parents and trashed = false",
            'fields' => 'files(id,name,mimeType)',
            'supportsAllDrives' => true, 'includeItemsFromAllDrives' => true,
        ])->getFiles();
        $this->line('Inside it:');
        foreach ($kids as $f) {
            $this->line('  - '.$f->getName().($f->getMimeType() === 'application/vnd.google-apps.folder' ? '/' : ''));
        }
        $found = collect($kids)->contains(fn ($f) => $f->getName() === $name);
        $this->line($found ? "FOUND \"{$name}\" — backup should work." : "MISSING \"{$name}\" directly inside that folder.");

        if (! $found && $this->option('create')) {
            Storage::disk('google')->makeDirectory($name);
            $this->info("Created \"{$name}\". Now run: php artisan backup:run --only-db");
        } elseif (! $found) {
            $this->warn('Re-run with --create to create it, or set GOOGLE_DRIVE_FOLDER_ID to the folder that contains it.');
        }

        return self::SUCCESS;
    }
}
