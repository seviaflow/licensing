<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

use SeviaFlow\Licensing\Contracts\ActivationStore;

final class LicenseGate
{
    public function __construct(
        private readonly LicenseVerifier $verifier,
        private readonly ActivationStore $activations,
    ) {}

    public function check(string $product, array $context = []): LicenseDecision
    {
        if (! (bool) config('seviaflow-licensing.enforce', true)) {
            return new LicenseDecision(true, 'licensing_not_enforced', 'License enforcement is disabled for this environment.');
        }

        $context['product'] = $product;

        return $this->verifier->verify($this->activations->get($product), $context);
    }
}
