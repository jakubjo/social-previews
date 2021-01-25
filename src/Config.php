<?php


namespace SocialPreviews;


use BadMethodCallException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

class Config
{
    const POSITION_LEFT = 'left';
    const POSITION_CENTER = 'center';
    const POSITION_RIGHT = 'right';

    public int $width = 1200;
    public int $height = 630;

    public int $paddingLeft = 50;
    public int $paddingTop = 50;
    public int $paddingRight = 50;
    public int $paddingBottom = 50;

    public string $backgroundType = 'color';
    public array $backgroundColor = [255, 255, 255];

    public array $backgroundGradientFrom = [255, 255, 255];
    public array $backgroundGradientTo = [0, 0, 0];
    public int $backgroundGradientAngle = 0;

    public ?string $backgroundImagePath = null;

    public ?string $imagePublicPath = null;
    public string $imagePosition = self::POSITION_RIGHT;
    public int $imageMaxWidth = 400;
    public int $imageMaxHeight = 530;
    public int $imageBorder = 0;
    public array $imageBorderColor = [0, 0, 0];

    public string $fontFamily = 'Roboto';
    public string $fontWeight = '400';
    public array $fontColor = [0, 0, 0];
    public int $fontSize = 50;

    public string $text = '';

    private array $defaultConfig = [];

    public function __construct(Repository $configRepository)
    {
        $this->setDefaultConfig(
            $configRepository->get('social-previews.renderer')
        );
    }

    public function __toString(): string
    {
        return $this->getUrl();
    }

    public function __call(string $name, $value): self
    {
        if (substr($name, 0, 4) !== 'with') {
            throw new BadMethodCallException(
                sprintf('Method [%s] not found.', $name)
            );
        }

        $property = substr($name, 4);
        $property = lcfirst($property);

        if (!property_exists($this, $property)) {
            throw new InvalidArgumentException(
                sprintf('Property [%s] does not exists.', $property)
            );
        }

        $this->{$property} = $value[0];

        return $this;
    }

    public function with(array $properties): self
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
                continue;
            }

            throw new InvalidArgumentException(
                sprintf('Property [%s] does not exists.', $property)
            );
        }

        return $this;
    }

    private function setDefaultConfig(array $config): self
    {
        $config = $this->flattenConfigArray([], $config);

        $this->defaultConfig = $config;

        return $this->with($config);
    }

    public function applyEncodedData(string $data): self
    {
        $props = $this->decodeDiff($data);

        return $this->with($props);
    }

    private function flattenConfigArray(array $arr, $input, string $prefix = null): array
    {
        foreach ($input as $key => $value) {
            $key = Str::camel($key);
            $path = $prefix ? $prefix . ucfirst($key) : $key;

            if (!is_array($value)) {
                $arr[$path] = $value;
                continue;
            }

            if (array_keys($value) === range(0, count($value) - 1)) {
                $arr[$path] = $value;
                continue;
            }

            $arr = $this->flattenConfigArray($arr, $value, $path);
        }

        return $arr;
    }

    public function getCanvasWidth(): int
    {
        return $this->width - $this->paddingLeft - $this->paddingRight;
    }

    public function getCanvasHeight(): int
    {
        return $this->height - $this->paddingTop - $this->paddingBottom;
    }

    public function getUrl(): string
    {
        $diff = $this->getDiffedProperties();

        $data = $this->encodeDiff($diff);

        return route('socialPreviews.show', [$data]);
    }

    private function encodeDiff(array $diff): string
    {
        $configHash = hash('crc32', json_encode($this->defaultConfig));

        $diff = json_encode($diff);
        $base64 = base64_encode($diff);

        $base64 = str_replace(['+', '/', '='], ['-', '_', ''], $base64);

        $base64 = str_split($base64, 32);
        $base64 = implode('/', $base64);

        return $configHash . '/' . $base64;
    }

    private function decodeDiff(string $diff): array
    {
        $parts = explode('/', $diff);

        array_shift($parts);

        $base64 = implode('', $parts);

        $base64 = str_replace(['-','_'], ['+','/'], $base64);

        $json = base64_decode($base64);

        return json_decode($json, true) ?? [];
    }

    private function getDiffedProperties(): array
    {
        $reflection = new ReflectionClass($this);

        $values = [];

        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $values[] = [
                'name' => $property->getName(),
                'runtime' => $property->getValue($this),
                'default' => $this->defaultConfig[$property->getName()] ?? $property->getDefaultValue(),
            ];
        }

        $values = collect($values)
            ->filter(function($value) {
                return $value['runtime'] !== $value['default'];
            })
            ->pluck('runtime', 'name')
            ->all();

        return $values;
    }
}
