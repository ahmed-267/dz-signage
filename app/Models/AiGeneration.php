<?php

namespace App\Models;

use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property AiGenerationType $type
 * @property AiGenerationStatus $status
 * @property string $provider
 * @property string|null $model
 * @property string $prompt
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $output
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $idempotency_key
 * @property string|null $temp_disk
 * @property string|null $temp_path
 * @property int|null $media_asset_id
 * @property int|null $screen_design_id
 * @property int|null $usage_input_tokens
 * @property int|null $usage_output_tokens
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'type',
        'status',
        'provider',
        'model',
        'prompt',
        'options',
        'output',
        'error_code',
        'error_message',
        'idempotency_key',
        'temp_disk',
        'temp_path',
        'media_asset_id',
        'screen_design_id',
        'usage_input_tokens',
        'usage_output_tokens',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AiGenerationType::class,
            'status' => AiGenerationStatus::class,
            'options' => 'array',
            'output' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @return BelongsTo<ScreenDesign, $this>
     */
    public function screenDesign(): BelongsTo
    {
        return $this->belongsTo(ScreenDesign::class);
    }

    public function hasTempFile(): bool
    {
        return filled($this->temp_disk) && filled($this->temp_path);
    }

    public function deleteTempFile(): void
    {
        if (! $this->hasTempFile()) {
            return;
        }

        Storage::disk((string) $this->temp_disk)->delete((string) $this->temp_path);
        $this->forceFill([
            'temp_disk' => null,
            'temp_path' => null,
        ])->save();
    }
}
