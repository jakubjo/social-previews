<?php


namespace SocialPreviews;


use Illuminate\Routing\UrlGenerator;
use Imagine\Filter\Advanced\Border;
use Imagine\Filter\Transformation;
use Imagine\Gd\Font;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Fill\Gradient\Horizontal;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Palette\RGB as RGBPalette;
use Imagine\Image\Point;

class Renderer
{
    const PNG_COMPRESSION_LEVEL = 5;

    private int $imageWidth = 0;
    private int $imageHeight = 0;

    public function __construct(
        private UrlGenerator $urlGenerator,
        private FileHandler $fileHandler,
        private FontFetcher $fontFetcher,
        Config $config = null
    ) {
        if (!$config) {
            $config = new Config;
        }

        $this->config = $config;
    }

    public function withConfig(Config $config): self
    {
        return new self(
            $this->urlGenerator,
            $this->fileHandler,
            $this->fontFetcher,
            $config,
        );
    }

    public function render(): ImageInterface
    {
        $image = $this->createImage();
        $image = $this->addImage($image);
        $image = $this->addText($image);

        return $image;
    }

    private function createImage(): ImageInterface
    {
        $config = $this->config;

        $type = $config->backgroundType;

        if ($type === 'image') {
            return $this->createFromImage(
                $this->config->width,
                $this->config->height,
                $config->backgroundImagePath,
            );
        }

        if ($type === 'gradient') {
            return $this->createWithGradient(
                $this->config->width,
                $this->config->height,
                $this->toColor($config->backgroundGradientFrom),
                $this->toColor($config->backgroundGradientTo),
                $config->backgroundGradientAngle,
            );
        }

        return (new Imagine)->create(
            new Box($config->width, $config->height),
            $this->toColor($config->backgroundColor)
        );
    }

    private function toColor($color): RGB
    {
        if (is_array($color)) {
            $color = sprintf(
                '#%02x%02x%02x',
                $color[0] ?? 0,
                $color[1] ?? 0,
                $color[2] ?? 0,
            );
        }

        return (new RGBPalette)->color($color);
    }

    private function createWithGradient(int $width, int $height, RGB $from, RGB $to, int $angle): ImageInterface
    {
        $image = (new Imagine)->create(new Box($width, $height));

        if ($angle !== 0) {
            $image->rotate($angle * -1);
        }

        $fill = new Horizontal($width, $from, $to);

        $image->fill($fill);

        if ($angle === 0) {
            return $image;
        }

        $image->rotate($angle);

        $size = $image->getSize();
        $x = $size->getWidth() / 2 - ($width / 2);
        $y = $size->getHeight() / 2 - ($height / 2);

        $image->crop(new Point($x, $y), new Box($width, $height));

        return $image;
    }

    private function createFromImage(int $width, int $height, string $path): ImageInterface
    {
        $openedImage = (new Imagine)
            ->open($path)
            ->thumbnail(new Box($width, $height));

        $size = $openedImage->getSize();
        $openedWidth = $size->getWidth();
        $openedHeight = $size->getHeight();

        $x = ($width - $openedWidth) / 2;
        $y = ($height - $openedHeight) / 2;

        return (new Imagine)
            ->create(new Box($width, $height))
            ->paste($openedImage, new Point($x, $y));
    }

    private function addText(ImageInterface $image): ImageInterface
    {
        $config = $this->config;

        $text = $config->text;

        if (!$text || empty($text)) {
            return $image;
        }

        $maxWidth = $config->getCanvasWidth();
        $maxHeight = $config->getCanvasHeight();

        $gap = min($config->paddingLeft, $config->paddingRight);

        if ($this->imageWidth > 0) {
            $maxWidth -= $this->imageWidth + $gap;
        }

        [$font, $text] = $this->getFont(
            $maxWidth,
            $maxHeight,
            $this->getFontFile(),
            $text,
            $config->fontSize,
            $this->toColor($config->fontColor)
        );

        $box = $font->box($text);

        if ($this->imageWidth > 0 && $config->imagePosition === Config::POSITION_LEFT) {
            $x = $config->width - ($config->width - $this->imageWidth - $gap - $config->paddingLeft);
        } elseif ($this->imageWidth > 0 && $config->imagePosition === Config::POSITION_RIGHT) {
            $x = $config->paddingLeft;
        } else {
            $x = $config->paddingLeft;
        }

        $y = ($config->getCanvasHeight() - $box->getHeight()) / 2 + $config->paddingTop;

        $point = new Point(
            $x > 0 ? $x : 0,
            $y > 0 ? $y : 0,
        );

        $image->draw()->text($text, $font, $point);

        return $image;
    }

    private function getFont(int $maxWidth, int $maxHeight, string $fontFile, string $text, int $maxFontSize, RGB $color): array
    {
        $maxFontSize -= 5;

        do {
            $maxFontSize -= 5;

            $font = new Font($fontFile, $maxFontSize, $color);

            $wrapAfter = 110;

            do {
                $wrapAfter -= 10;
                $box = $font->box(wordwrap($text, $wrapAfter));

                if ($wrapAfter <= 10) {
                    break;
                }

            } while ($box->getWidth() > $maxWidth);

            if ($maxFontSize <= 10) {
                break;
            }

        } while ($box->getHeight() > $maxHeight);

        return [$font, wordwrap($text, $wrapAfter)];
    }

    private function getFontFile(): string
    {
        $family = $this->config->fontFamily;
        $variant = $this->config->fontWeight;

        if ($this->fileHandler->fontExists($family, $variant)) {
            return $this->fileHandler->getFontPath($family, $variant);
        }

        $contents = $this->fontFetcher->fetch($family, $variant);

        return $this
            ->fileHandler
            ->saveFont($family, $variant, $contents)
            ->getFontPath($family, $variant);
    }

    private function addImage(ImageInterface $image): ImageInterface
    {
        $config = $this->config;

        $path = $config->imagePublicPath;
        $position = $config->imagePosition;

        if (! $path) {
            return $image;
        }

        $path = public_path($path);

        $maxWidth = $config->imageMaxWidth ?? $config->getCanvasWidth();
        $maxHeight = $config->imageMaxHeight ?? $config->getCanvasHeight();

        if ($maxWidth > $config->getCanvasWidth()) {
            $maxWidth = $config->getCanvasWidth();
        }

        if ($maxHeight > $config->getCanvasHeight()) {
            $maxHeight = $config->getCanvasHeight();
        }

        $border = $config->imageBorder;
        $borderColor = $config->imageBorderColor;

        $openedImage = (new Imagine)
            ->open($path)
            ->thumbnail(new Box($maxWidth, $maxHeight));

        if ($border > 0) {
            (new Transformation)->applyFilter(
                $openedImage,
                new Border($this->toColor($borderColor), $border, $border)
            );
        }

        $size = $openedImage->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();

        if ($position === Config::POSITION_LEFT) {
            $x = $config->paddingLeft;
        } else {
            $x = $config->width - $config->paddingRight - $width;
        }

        $y = ($config->getCanvasHeight() - $height) / 2 + $config->paddingTop;

        $this->imageWidth = $width;
        $this->imageHeight = $height;

        return $image
            ->paste($openedImage, new Point($x, $y));
    }
}
