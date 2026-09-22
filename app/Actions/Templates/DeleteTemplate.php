<?php

namespace App\Actions\Templates;

use App\Models\Template;
use Illuminate\Support\Facades\DB;

class DeleteTemplate
{
    public function handle(Template $template): void
    {
        DB::transaction(function () use ($template): void {
            $template->favourites()->delete();
            $template->delete();
        });
    }
}
