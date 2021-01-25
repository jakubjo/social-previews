<?php


namespace SocialPreviews;


use Illuminate\Config\Repository;

class RequestHandler
{
    public function __invoke(
        Repository $configRepository,
        Config $config,
        Renderer $renderer,
        FileHandler $fileHandler,
        string $data
    ) {
        $config->applyEncodedData($data);

        $image = $renderer
            ->withConfig($config)
            ->render();

        if ($configRepository->get('social-previews.disable_image_cache') !== true) {
            $fileHandler->saveImage($image, $data);
        }

        $image->show('png', [
            'png_compression_level' => Renderer::PNG_COMPRESSION_LEVEL,
        ]);
    }
}
