<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class UploaderService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadDir,
    ) {
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        $targetDir = $this->uploadDir . '/' . $directory;

        $count = count(glob($targetDir . '/image-*')) + 1;
        $filename = 'image-' . $count . '.' . $file->getClientOriginalExtension();

        $file->move($targetDir, $filename);

        return '/uploads/' . $directory . '/' . $filename;
    }
}
