<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Models\User;
use App\Services\FirebaseStorageService;
use App\Services\ImageCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToFirebase extends Command
{
    protected $signature = 'storage:migrate-to-firebase {--dry-run : Preview changes without modifying data}';

    protected $description = 'Migrate local storage images to Firebase Storage';

    public function __construct(
        private FirebaseStorageService $storageService,
        private ImageCompressionService $compressionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        // Migrate KTM photos
        $ktmProfiles = DriverProfile::whereNotNull('ktm_url')
            ->where('ktm_url', 'like', '/storage/%')
            ->get();

        $this->info("Found {$ktmProfiles->count()} KTM photos to migrate.");

        foreach ($ktmProfiles as $profile) {
            $localPath = str_replace('/storage/', '', $profile->ktm_url);

            if (! Storage::disk('public')->exists($localPath)) {
                $this->warn("  SKIP: Missing file for driver_profile #{$profile->id}: {$localPath}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  WOULD migrate KTM: {$localPath}");
                $migrated++;
                continue;
            }

            try {
                $fileContents = Storage::disk('public')->get($localPath);
                $compressed = $this->compressionService->compressFromString($fileContents, 1920, 1080, 80);
                $objectPath = 'ktm_photos/ktm_' . $profile->user_id . '_' . time() . '.jpg';
                $url = $this->storageService->upload($objectPath, $compressed, 'image/jpeg');

                $profile->update(['ktm_url' => $url]);
                $this->line("  OK: {$localPath} -> {$objectPath}");
                $migrated++;
            } catch (\Exception $e) {
                $this->error("  ERROR migrating KTM for driver_profile #{$profile->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        // Migrate profile pictures
        $users = User::whereNotNull('profile_picture')
            ->where('profile_picture', 'like', '/storage/%')
            ->get();

        $this->info("Found {$users->count()} profile pictures to migrate.");

        foreach ($users as $user) {
            $localPath = str_replace('/storage/', '', $user->profile_picture);

            if (! Storage::disk('public')->exists($localPath)) {
                $this->warn("  SKIP: Missing file for user #{$user->id}: {$localPath}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  WOULD migrate avatar: {$localPath}");
                $migrated++;
                continue;
            }

            try {
                $fileContents = Storage::disk('public')->get($localPath);
                $compressed = $this->compressionService->compressFromString($fileContents, 512, 512, 80);
                $objectPath = 'avatars/avatar_' . $user->id . '_' . time() . '.jpg';
                $url = $this->storageService->upload($objectPath, $compressed, 'image/jpeg');

                $user->update(['profile_picture' => $url]);
                $this->line("  OK: {$localPath} -> {$objectPath}");
                $migrated++;
            } catch (\Exception $e) {
                $this->error("  ERROR migrating avatar for user #{$user->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Migration complete: {$migrated} migrated, {$skipped} skipped (missing), {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
