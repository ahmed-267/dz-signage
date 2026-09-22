<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistVersion>
 */
class PlaylistVersionFactory extends Factory
{
    protected $model = PlaylistVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'version_number' => 1,
            'created_by' => User::factory(),
            'published_at' => null,
        ];
    }

    public function forPlaylist(Playlist $playlist): static
    {
        return $this->state(fn () => [
            'playlist_id' => $playlist->id,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_at' => now(),
        ]);
    }
}
