<?php


namespace SocialPreviews;


use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Imagine\Image\ImageInterface;

class FileHandler
{
    public function __construct(
        private Repository $config,
    ) {}

    public function fontExists(string $family, string $variant): bool
    {
        return file_exists($this->getFontPath($family, $variant));
    }

    public function getFontPath(string $family, string $variant): string
    {
        $filename = Str::kebab($family . ' ' . $variant) . '.ttf';

        return $this->config->get('social-previews.font_cache_path') . '/' . $filename;
    }

    public function saveFont(string $family, string $variant, string $contents): self
    {
        $dir = $this->config->get('social-previews.font_cache_path');

        $this
            ->ensureDirectoryExists($dir)
            ->ensureGitIgnoreExists($dir);

        file_put_contents($this->getFontPath($family, $variant), $contents);

        return $this;
    }

    private function ensureDirectoryExists(string $dir): self
    {
        if (is_dir($dir)) {
            return $this;
        }

        mkdir($dir, 0777, true);

        return $this;
    }

    private function ensureGitIgnoreExists(string $dir): self
    {
        $ensure = $this->config->get('social-previews.ensure_gitignore_exists');

        if (!$ensure) {
            return $this;
        }

        $filename = $dir . '/.gitignore';

        if (file_exists($filename)) {
            return $this;
        }

        file_put_contents($filename, implode("\n", [
            '*',
            '!.gitignore',
            '',
        ]));

        return $this;
    }

    public function imageExists(string $data): bool
    {
        return file_exists($this->getImagePath($data));
    }

    private function getImagePath(string $data): string
    {
        return public_path(
            $this->config->get('social-previews.route_path') . '/' . $data . '.png'
        );
    }

    public function saveImage(ImageInterface $image, string $data): self
    {
        $routePath = $this->config->get('social-previews.route_path');

        $dir = public_path($routePath);

        $this
            ->ensureDirectoryExists($dir)
            ->ensureGitIgnoreExists($dir);

        $filename = $this->getImagePath($data);

        $directories = explode('/', $filename);
        array_pop($directories);

        $directories = implode('/', $directories);

        $this->ensureDirectoryExists($directories);

        $image->save($filename, [
            'png_compression_level' => Renderer::PNG_COMPRESSION_LEVEL,
        ]);

        return $this;
    }
}
