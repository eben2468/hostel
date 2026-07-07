<?php
namespace App\Core;

/** Validated image upload handling. Stores files under public/uploads/<subdir>. */
class Upload
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED   = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * Validate and store an uploaded image.
     *
     * @param array  $file   an entry from $_FILES
     * @param string $subdir subdirectory under public/uploads (e.g. 'students')
     * @return array{ok:bool, path?:string, error?:string} path is relative to uploads/
     */
    public static function image(array $file, string $subdir): array
    {
        $check = self::validate($file['tmp_name'] ?? '', $file['name'] ?? '', (int) ($file['size'] ?? 0), (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
        if (!$check['ok']) {
            return $check;
        }

        $dir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $check['ext'];
        $dest = $dir . '/' . $name;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $dest)
            : @rename($file['tmp_name'], $dest); // test/CLI fallback

        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }
        return ['ok' => true, 'path' => $subdir . '/' . $name];
    }

    /**
     * Validate an upload without moving it (separately testable).
     *
     * @return array{ok:bool, ext?:string, error?:string}
     */
    public static function validate(string $tmpPath, string $origName, int $size, int $error): array
    {
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed (error code ' . $error . ').'];
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and 2 MB.'];
        }
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
        }
        // Confirm the bytes really are an image of the claimed type.
        $info = @getimagesize($tmpPath);
        if ($info === false || !in_array($info['mime'], self::ALLOWED, true)) {
            return ['ok' => false, 'error' => 'The file is not a valid image.'];
        }
        return ['ok' => true, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
    }

    /** Delete a previously stored upload (path relative to uploads/). */
    public static function remove(?string $path): void
    {
        if (!$path) {
            return;
        }
        $full = UPLOAD_PATH . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Derive a tight, square favicon from a logo: trims the surrounding
     * transparent (or uniform-colour) border and centres the mark on a square
     * canvas so it fills the icon instead of looking tiny in the browser tab.
     *
     * @param string $logoRel logo path relative to uploads/
     * @return string|null    new favicon path relative to uploads/, or null on failure
     */
    public static function makeFavicon(string $logoRel, int $size = 128, float $marginPct = 0.0): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null; // GD not available
        }
        $src = UPLOAD_PATH . '/' . $logoRel;
        $info = is_file($src) ? @getimagesize($src) : false;
        if (!$info) {
            return null;
        }
        $im = match ($info['mime']) {
            'image/png'  => @imagecreatefrompng($src),
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null,
            default      => null,
        };
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        [$minX, $minY, $maxX, $maxY] = self::contentBox($im, $w, $h);
        if ($maxX < $minX || $maxY < $minY) {   // nothing detected → use whole image
            $minX = 0; $minY = 0; $maxX = $w - 1; $maxY = $h - 1;
        }
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;

        // Square canvas sized to the content plus a small margin.
        $side   = max(1, (int) round(max($cw, $ch) * (1 + $marginPct * 2)));
        $canvas = imagecreatetruecolor($side, $side);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $side, $side, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopy($canvas, $im, (int) (($side - $cw) / 2), (int) (($side - $ch) / 2), $minX, $minY, $cw, $ch);

        // Resample to the final favicon size.
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, $size, $size, $side, $side);

        $dir = UPLOAD_PATH . '/branding';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = 'favicon_' . bin2hex(random_bytes(6)) . '.png';
        $ok = imagepng($out, $dir . '/' . $name);
        imagedestroy($im);
        imagedestroy($canvas);
        imagedestroy($out);
        return $ok ? 'branding/' . $name : null;
    }

    /**
     * Bounding box of the "real" content: opaque pixels when the image has
     * transparency, otherwise pixels that differ from the top-left border colour.
     *
     * @return array{0:int,1:int,2:int,3:int} [minX, minY, maxX, maxY]
     */
    private static function contentBox($im, int $w, int $h): array
    {
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1; $anyAlpha = false;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $a = (imagecolorat($im, $x, $y) >> 24) & 0x7F; // 0 opaque .. 127 transparent
                if ($a > 10) {
                    $anyAlpha = true;
                }
                if ($a < 100) { // sufficiently opaque
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($anyAlpha && $maxX >= $minX) {
            return [$minX, $minY, $maxX, $maxY];
        }

        // Opaque image: trim a uniform border colour sampled from the corner.
        $bg = imagecolorat($im, 0, 0);
        $br = ($bg >> 16) & 0xFF; $bgc = ($bg >> 8) & 0xFF; $bb = $bg & 0xFF;
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if (abs((($c >> 16) & 0xFF) - $br) > 18 || abs((($c >> 8) & 0xFF) - $bgc) > 18 || abs(($c & 0xFF) - $bb) > 18) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        return [$minX, $minY, $maxX, $maxY];
    }
}
