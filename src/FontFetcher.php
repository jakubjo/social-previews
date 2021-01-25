<?php


namespace SocialPreviews;


use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use RuntimeException;

class FontFetcher
{
    public function __construct(
        private Factory $http,
    ) {}

    private function getFontFamilyUrl(string $family): string
    {
        return sprintf(
            'http://google-webfonts-helper.herokuapp.com/api/fonts/%s',
            Str::slug($family)
        );
    }

    public function fetch(string $family, string $variantName): string
    {
        $url = $this->getFontFamilyUrl($family);

        $response = $this
            ->http
            ->withOptions([
                'http_errors' => true,
            ])
            ->get($url);

        $data = $response->json();

        if (!isset($data['variants'])) {
            throw new RuntimeException(
                sprintf(
                    'Response did not contain any variant data. Response was: %s',
                    json_encode($data),
                )
            );
        }

        $variant = $this->findVariant($data['variants'], $variantName);

        if (isset($variant['ttf'])) {
            return file_get_contents($variant['ttf']);
        }

        throw new RuntimeException(
            sprintf('Path to TTF file not found in font variant data. Data was: %s.', json_encode($variant))
        );
    }

    private function findVariant(array $data, string $variantName)
    {
        $data = collect($data);

        return $data
            ->filter(function ($variantData) use ($variantName) {
                if ($variantData['id'] === $variantName) {
                    return true;
                }

                if ($variantData['fontWeight'] === (string) $variantName) {
                    return true;
                }

                if ($variantData['fontStyle'] === $variantName) {
                    return true;
                }

                return false;
            })
            ->first(null, function() use ($data, $variantName) {
                $possibleVariants = $data->map(function($variantData) {
                    return $variantData['id'];
                })->all();

                throw new RuntimeException(
                    sprintf(
                        'Variant "%s" not found in Google Web Fonts. Choose one of the following: %s',
                        $variantName,
                        json_encode($possibleVariants)
                    )
                );
            });
    }
}
