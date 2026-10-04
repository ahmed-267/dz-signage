<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Digital Asset Links for the PWABuilder / Bubblewrap TWA that wraps `/player`.
 */
class DigitalAssetLinksController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $package = (string) config('player.android.package_name');
        $fingerprints = config('player.android.sha256_cert_fingerprints', []);

        if (! is_array($fingerprints) || $fingerprints === [] || $package === '') {
            return response()->json([]);
        }

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => array_values($fingerprints),
                ],
            ],
        ]);
    }
}
