<?php

namespace App\Actions\Templates;

use App\Models\Template;
use App\Models\TemplateFavourite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleTemplateFavourite
{
    /**
     * @return array{favourited: bool}
     */
    public function handle(User $user, Template $template): array
    {
        return DB::transaction(function () use ($user, $template) {
            $existing = TemplateFavourite::query()
                ->where('user_id', $user->id)
                ->where('template_id', $template->id)
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return ['favourited' => false];
            }

            TemplateFavourite::query()->create([
                'user_id' => $user->id,
                'template_id' => $template->id,
            ]);

            return ['favourited' => true];
        });
    }
}
