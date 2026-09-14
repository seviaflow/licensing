<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing\Middleware;

use Closure;
use Illuminate\Http\Request;
use SeviaFlow\Licensing\LicenseGate;
use Symfony\Component\HttpFoundation\Response;

final class RequireLicensedModule
{
    public function __construct(private readonly LicenseGate $gate) {}

    public function handle(Request $request, Closure $next, string $product): Response
    {
        $decision = $this->gate->check($product, [
            'installation_uuid' => config('seviaflow-licensing.installation_uuid'),
            'environment' => config('seviaflow-licensing.environment'),
            'host' => $request->getHost(),
        ]);
        if (! $decision->allowed) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code' => $decision->code,
                    'message' => $decision->message,
                ], 403);
            }

            abort(403, $decision->message);
        }

        $request->attributes->set('seviaflow.license_decision', $decision);

        return $next($request);
    }
}
