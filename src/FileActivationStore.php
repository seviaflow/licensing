<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use SeviaFlow\Licensing\Contracts\ActivationStore;

final class FileActivationStore implements ActivationStore
{
    public function __construct(private readonly Filesystem $files) {}

    public function get(string $product): ?string
    {
        $data = $this->read();

        return is_string($data[$product] ?? null) ? $data[$product] : null;
    }

    public function put(string $product, string $document): void
    {
        $data = $this->read();
        $data[$product] = $document;
        $this->files->put($this->path(), Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    public function forget(string $product): void
    {
        $data = $this->read();
        unset($data[$product]);
        $this->files->put($this->path(), Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    private function read(): array
    {
        if (! $this->files->exists($this->path())) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $this->files->get($this->path())), true, 32, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function path(): string
    {
        return ltrim((string) config('seviaflow-licensing.activation_path'), '/');
    }
}
