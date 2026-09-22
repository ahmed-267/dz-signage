<?php

namespace App\Support;

use App\Enums\MediaType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class MediaStorage
{
    public static function disk(): string
    {
        return (string) config('media.disk', 'public');
    }

    public static function directory(int $workspaceId): string
    {
        return 'workspaces/'.$workspaceId.'/media';
    }

    /**
     * Store an uploaded file under a generated, workspace-scoped path.
     * Never uses the client filename as the storage key.
     *
     * @return array{
     *     storage_disk: string,
     *     storage_path: string,
     *     original_filename: string,
     *     mime_type: string|null,
     *     extension: string|null,
     *     size_bytes: int,
     *     width: int|null,
     *     height: int|null
     * }
     */
    public static function storeUpload(UploadedFile $file, int $workspaceId, MediaType $type): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = self::directory($workspaceId);
        $disk = self::disk();

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new RuntimeException('Failed to store media file.');
        }

        [$width, $height] = self::readImageDimensions($file, $type);

        return [
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Physically copy a stored file for duplication (independent lifecycle).
     */
    public static function copyStoredFile(string $disk, string $path, int $workspaceId): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
        $destination = self::directory($workspaceId).'/'.Str::uuid()->toString().'.'.$extension;

        if (! Storage::disk($disk)->copy($path, $destination)) {
            throw new RuntimeException('Failed to duplicate media file.');
        }

        return $destination;
    }

    public static function deleteStoredFile(?string $disk, ?string $path): void
    {
        if (! filled($disk) || ! filled($path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private static function readImageDimensions(UploadedFile $file, MediaType $type): array
    {
        if (! in_array($type, [MediaType::Image, MediaType::Logo], true)) {
            return [null, null];
        }

        if ($file->getMimeType() === 'image/svg+xml') {
            return [null, null];
        }

        $realPath = $file->getRealPath();
        if (! $realPath) {
            return [null, null];
        }

        $info = @getimagesize($realPath);
        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}
