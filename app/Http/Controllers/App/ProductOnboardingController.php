<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\Onboarding\ProductOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductOnboardingController extends Controller
{
    public function __construct(private readonly ProductOnboarding $onboarding) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'advance', 'complete', 'skip', 'restart'])],
            'step' => ['nullable', 'integer', 'min:1', 'max:'.ProductOnboarding::STEPS],
        ]);

        match ($data['action']) {
            'start' => $this->onboarding->start($user),
            'advance' => $this->onboarding->advance($user, (int) ($data['step'] ?? 1)),
            'complete' => $this->onboarding->complete($user),
            'skip' => $this->onboarding->skip($user),
            'restart' => $this->onboarding->restart($user),
            default => abort(422, 'Invalid onboarding action.'),
        };

        return redirect()->back();
    }
}
