<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing\Contracts;

interface ActivationStore
{
    public function get(string $product): ?string;

    public function put(string $product, string $document): void;

    public function forget(string $product): void;
}
