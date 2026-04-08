# Image Storage Finalisation — Migrate to Firebase Storage

## Context

Task 5.4 in the staging checklist: migrate file storage from local disk to Firebase Storage. The project already uses `kreait/firebase-php` v7.22 for auth/FCM, which bundles `google/cloud-storage`. Currently KTM photos and profile photos are stored on the local `public` disk at `storage/app/public/`. There is no server-side image compression — only client-side via Flutter's image_picker (85% quality). This plan adds Firebase Storage + server-side compression via `intervention/image`.

---

## Phase 1: Infrastructure (no behavior changes)

### 1.1 Install intervention/image
```
composer require intervention/image
```

### 1.2 Add Firebase Storage config
**Modify:** `backend/config/services.php` — add `'storage_bucket' => env('FIREBASE_STORAGE_BUCKET')` to the `firebase` array

**Modify:** `backend/.env.example` — add `FIREBASE_STORAGE_BUCKET=anjem-me.appspot.com`

### 1.3 Register Firebase Storage singleton
**Modify:** `backend/app/Providers/AppServiceProvider.php`

Add singleton for `Kreait\Firebase\Contract\Storage`:
```php
$this->app->singleton(\Kreait\Firebase\Contract\Storage::class, function ($app) {
    return $app->make(Factory::class)
        ->withDefaultStorageBucket(config('services.firebase.storage_bucket'))
        ->createStorage();
});
```

### 1.4 Create FirebaseStorageService
**New file:** `backend/app/Services/FirebaseStorageService.php`

Methods:
- `upload(string $objectPath, string $fileContents, string $contentType): string` — uploads with `predefinedAcl: publicRead`, returns public URL
- `delete(string $objectPath): void` — deletes, swallows NotFoundException
- `getPublicUrl(string $objectPath): string` — returns `https://storage.googleapis.com/{bucket}/{path}`
- `extractObjectPath(string $url): ?string` — extracts path from Firebase URL (for deletion)

### 1.5 Create ImageCompressionService
**New file:** `backend/app/Services/ImageCompressionService.php`

Uses `Intervention\Image\ImageManager` with GD driver:
- `compress(string $filePath, int $maxWidth, int $maxHeight, int $quality = 80): string`
- Uses `scaleDown()` (never upscales) + `toJpeg()` (consistent output format)
- Returns raw JPEG binary string

---

## Phase 2: Migrate upload + delete logic

### 2.1 Rewrite KycVerificationService uploads
**Modify:** `backend/app/Services/KycVerificationService.php`

- Inject `FirebaseStorageService` + `ImageCompressionService`
- `storeKtmPhoto()`: compress to 1920x1080 @80%, upload to `ktm_photos/ktm_{userId}_{time}.jpg`, return full Firebase URL
- `storeProfilePhoto()`: compress to 512x512 @80%, upload to `avatars/avatar_{userId}_{time}.jpg`, return full Firebase URL
- No more `Storage::disk('public')` for uploads

### 2.2 Update KycVerificationService::revokeKycData() deletion
**Modify:** `backend/app/Services/KycVerificationService.php`

- Use `extractObjectPath()` to detect Firebase vs legacy URLs
- Firebase URLs → `$storageService->delete($objectPath)`
- Legacy `/storage/...` URLs → `Storage::disk('public')->delete(...)` (backward compat)

### 2.3 Update KycResource approve/reject deletion
**Modify:** `backend/app/Filament/Resources/KycResource.php`

- Same Firebase vs legacy detection pattern in `approveKyc()` and `rejectKyc()`
- Resolve `FirebaseStorageService` from container: `app(FirebaseStorageService::class)`

### 2.4 Update UserController::updateAvatar()
**Modify:** `backend/app/Http/Controllers/Api/UserController.php`

- Inject `FirebaseStorageService` + `ImageCompressionService`
- Delete old avatar (Firebase or legacy), compress + upload new to Firebase

---

## Phase 3: URL display compatibility

### 3.1 Add resolveImageUrl helper to KycResource
**Modify:** `backend/app/Filament/Resources/KycResource.php`

Add static helper used by `buildReviewModalHtml()`:
```php
private static function resolveImageUrl(?string $url): ?string
{
    if (!$url) return null;
    if (str_starts_with($url, 'http')) return $url;
    return url($url); // Legacy /storage/... path
}
```

### No mobile changes needed
Firebase URLs are full absolute URLs — `NetworkImage()` works as-is.

---

## Phase 4: Data migration command

### 4.1 Create MigrateStorageToFirebase command
**New file:** `backend/app/Console/Commands/MigrateStorageToFirebase.php`

- `php artisan storage:migrate-to-firebase [--dry-run]`
- Scans `driver_profiles.ktm_url` and `users.profile_picture` for `/storage/...` URLs
- Reads local file → compresses → uploads to Firebase → updates DB URL
- Logs missing files (already deleted by approval)

---

## File Manifest

### New files (3):
| File | Description |
|------|-------------|
| `backend/app/Services/FirebaseStorageService.php` | Firebase Storage wrapper |
| `backend/app/Services/ImageCompressionService.php` | Image compression with intervention/image |
| `backend/app/Console/Commands/MigrateStorageToFirebase.php` | One-time data migration |

### Modified files (6):
| File | Change |
|------|--------|
| `backend/composer.json` | Add `intervention/image` |
| `backend/config/services.php` | Add `storage_bucket` to firebase config |
| `backend/.env.example` | Add `FIREBASE_STORAGE_BUCKET` |
| `backend/app/Providers/AppServiceProvider.php` | Register Storage singleton |
| `backend/app/Services/KycVerificationService.php` | Rewrite upload/delete to use Firebase |
| `backend/app/Filament/Resources/KycResource.php` | Update deletion + URL resolution |
| `backend/app/Http/Controllers/Api/UserController.php` | Update avatar upload/delete |

### No changes needed:
- Mobile code (URLs already absolute)
- `UserResource.php`, `DriverProfile` model, `User` model (just string columns)
- `SubmitKycRequest.php` (validation unchanged)
- `DriverKycController.php` (delegates to service)

---

## Verification

1. `composer install` — confirm intervention/image installs
2. Set `FIREBASE_STORAGE_BUCKET` in `.env`
3. Test KYC submission: upload KTM + profile photo → verify files appear in Firebase Console
4. Test KYC approval: verify KTM file deleted from Firebase
5. Test KYC rejection: verify both files deleted from Firebase
6. Test avatar update: verify old deleted, new uploaded
7. Test admin panel: KYC review modal shows images from Firebase URLs
8. Run `php artisan storage:migrate-to-firebase --dry-run` to preview migration
9. `php artisan test` — confirm no regressions
