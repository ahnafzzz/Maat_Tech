<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductMediaStorage
{
    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('The product media upload could not be stored.');
        }

        return $path;
    }

    public function delete(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }
}
