<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * One image on its way to a model.
 *
 * Bytes plus a mime type is the only shape every provider accepts, so it is
 * what this carries — whether the image arrived as a WhatsApp media download,
 * a console upload, or a file on disk.
 */
class ImageInput
{
    public const SUPPORTED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /**
     * @param  string|null  $url  where the image can be viewed later — a stored
     *                            upload, or the media URL a WhatsApp provider gives us
     */
    public function __construct(
        public readonly string $base64,
        public readonly string $mime,
        public readonly ?string $filename = null,
        public readonly ?string $url = null,
    ) {
        if (! in_array($mime, self::SUPPORTED_MIMES, true)) {
            throw new InvalidArgumentException("Format d'image non pris en charge : {$mime}");
        }
    }

    public static function fromPath(string $path, ?string $mime = null, ?string $url = null): self
    {
        $mime ??= mime_content_type($path) ?: 'image/jpeg';

        return new self(base64_encode((string) file_get_contents($path)), $mime, basename($path), $url);
    }

    public static function fromBinary(string $bytes, string $mime, ?string $filename = null, ?string $url = null): self
    {
        return new self(base64_encode($bytes), $mime, $filename, $url);
    }

    public function dataUri(): string
    {
        return 'data:'.$this->mime.';base64,'.$this->base64;
    }

    public function bytes(): int
    {
        return (int) (strlen($this->base64) * 3 / 4);
    }
}
