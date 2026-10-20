<?php

namespace App\Support;

/**
 * EXIF orientation of uploaded photos.
 *
 * Phone cameras often store the pixels sideways and record the turn in the EXIF Orientation tag.
 * Browsers apply that tag when they show the photo, so AI box coordinates and the page layout must
 * use the same upright frame, or every annotation lands in the wrong place.
 */
class ImageOrientation
{
    /** EXIF Orientation of a JPEG: 1 is upright, 2-8 need a flip and/or a quarter turn. */
    public static function read(string $path): int
    {
        if (!function_exists('exif_read_data') || !is_file($path) || @exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return 1;
        }

        $exif = @exif_read_data($path, 'IFD0');
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /** True when the photo is shown turned a quarter, so its width and height trade places. */
    public static function swapsAxes(int $orientation): bool
    {
        return $orientation >= 5 && $orientation <= 8;
    }

    /**
     * Width and height of the photo as browsers display it.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function displaySize(string $path): ?array
    {
        $info = is_file($path) ? @getimagesize($path) : false;
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }

        $size = [(int) $info[0], (int) $info[1]];

        return self::swapsAxes(self::read($path)) ? [$size[1], $size[0]] : $size;
    }

    /**
     * Moves a [ymin, xmin, ymax, xmax] box (0-1000 scale) from the stored pixel frame into the
     * displayed frame, applying the same flip/turn the browser applies to the photo.
     */
    public static function mapBoxToDisplay(array $box, int $orientation): array
    {
        [$ymin, $xmin, $ymax, $xmax] = array_values($box);
        $a = self::mapPoint((float) $xmin, (float) $ymin, $orientation);
        $b = self::mapPoint((float) $xmax, (float) $ymax, $orientation);

        return [min($a[1], $b[1]), min($a[0], $b[0]), max($a[1], $b[1]), max($a[0], $b[0])];
    }

    /** @return array{0: float, 1: float} the point's [x, y] in the displayed frame */
    private static function mapPoint(float $x, float $y, int $orientation): array
    {
        $max = 1000.0;

        return match ($orientation) {
            2 => [$max - $x, $y],
            3 => [$max - $x, $max - $y],
            4 => [$x, $max - $y],
            5 => [$y, $x],
            6 => [$max - $y, $x],
            7 => [$max - $y, $max - $x],
            8 => [$y, $max - $x],
            default => [$x, $y],
        };
    }

    /**
     * The same JPEG bytes with the EXIF Orientation tag set to 1, so any reader sees the stored
     * pixels as they are. Null when the bytes are not a JPEG or carry no Orientation tag.
     */
    public static function withoutOrientationTag(string $jpeg): ?string
    {
        $length = strlen($jpeg);
        if ($length < 4 || substr($jpeg, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $position = 2;
        while ($position + 4 <= $length) {
            if ($jpeg[$position] !== "\xFF") {
                return null;
            }

            $marker = ord($jpeg[$position + 1]);
            if ($marker === 0xDA || $marker === 0xD9) {
                return null; // image data starts; no metadata segments follow
            }

            $segmentLength = unpack('n', substr($jpeg, $position + 2, 2))[1];
            $segmentEnd = $position + 2 + $segmentLength;
            $tiff = $position + 10;

            if ($marker === 0xE1 && substr($jpeg, $position + 4, 6) === "Exif\0\0" && $tiff + 8 <= $segmentEnd) {
                $byteOrder = substr($jpeg, $tiff, 2);
                if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
                    return null;
                }

                $little = $byteOrder === 'II';
                $u16 = fn (int $at): int => unpack($little ? 'v' : 'n', substr($jpeg, $at, 2))[1];
                $u32 = fn (int $at): int => unpack($little ? 'V' : 'N', substr($jpeg, $at, 4))[1];

                $ifd = $tiff + $u32($tiff + 4);
                if ($ifd + 2 > $segmentEnd) {
                    return null;
                }

                for ($index = 0, $count = $u16($ifd); $index < $count; $index++) {
                    $entry = $ifd + 2 + ($index * 12);
                    if ($entry + 12 > $segmentEnd) {
                        return null;
                    }

                    if ($u16($entry) === 0x0112) {
                        return substr_replace($jpeg, $little ? "\x01\x00" : "\x00\x01", $entry + 8, 2);
                    }
                }

                return null;
            }

            $position = $segmentEnd;
        }

        return null;
    }
}
