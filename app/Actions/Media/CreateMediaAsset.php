<?php

namespace App\Actions\Media;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMediaAsset
{
    /**
     * @param  array{
     *     type: MediaType|string,
     *     name: string,
     *     file?: UploadedFile|null,
     *     text_content?: string|null,
     *     url?: string|null,
     *     duration_seconds?: int|null,
     *     width?: int|null,
     *     height?: int|null
     * }  $data
     */
    public function handle(User $user, Workspace $workspace, array $data): MediaAsset
    {
        $type = $data['type'] instanceof MediaType
            ? $data['type']
            : MediaType::from((string) $data['type']);

        return DB::transaction(function () use ($user, $workspace, $data, $type) {
            $attributes = [
                'workspace_id' => $workspace->id,
                'type' => $type,
                'name' => trim((string) $data['name']),
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'text_content' => null,
                'url' => null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
            ];

            if ($type->isFileBased()) {
                $file = $data['file'] ?? null;
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'file' => 'A file is required for this media type.',
                    ]);
                }

                $stored = MediaStorage::storeUpload($file, $workspace->id, $type);
                $attributes = array_merge($attributes, $stored);

                if (BillingEntitlement::enforce()) {
                    $limit = BillingEntitlement::storageLimitBytes($workspace);
                    if ($limit !== null) {
                        $used = (int) MediaAsset::query()
                            ->where('workspace_id', $workspace->id)
                            ->sum('size_bytes');
                        $incoming = (int) $attributes['size_bytes'];
                        if (($used + $incoming) > $limit) {
                            throw ValidationException::withMessages([
                                'file' => 'This upload would exceed your plan storage allowance. Upgrade your plan or remove unused Media.',
                            ]);
                        }
                    }
                }

                if (isset($data['width'])) {
                    $attributes['width'] = $data['width'];
                }
                if (isset($data['height'])) {
                    $attributes['height'] = $data['height'];
                }
            } elseif ($type === MediaType::Text) {
                $attributes['text_content'] = (string) ($data['text_content'] ?? '');
            } elseif ($type === MediaType::Link) {
                $attributes['url'] = (string) ($data['url'] ?? '');
            }

            return MediaAsset::query()->create($attributes);
        });
    }
}
