<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalJsonFileStore
{
    public function read(string $path): ?array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        $payload = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : [];
    }

    public function write(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        if (! $disk->put($path, $json."\n")) {
            throw new RuntimeException("Unable to write {$path}");
        }
    }
}
