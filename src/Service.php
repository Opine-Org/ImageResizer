<?php
//Based on the work of Joe Lencioni, Smart Image Resizer 1.4.1 (http://shiftingpixel.com)
namespace Opine\ImageResizer;
use Exception;

class Service {
    private static $quality = 90;
    private static $memory = '100M';
    private $enforceReferer = false;
    private $unlink = false;
    private $secret;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    public function preProcess ($file, $width, $height, $cropratio, $type, $extension) {
        $filedir = null;
        if (substr_count($file, '/') > 0) {
            $filedir = explode('/', $file);
            $filename = array_pop($filedir);
            $filedir = implode('/', $filedir);
        }
        $imagedir = $_SERVER['DOCUMENT_ROOT'] . '/imagecache/' . $width . '/' . $height . '/' . $cropratio . '/' . $type . '/' . $filedir;
        $image = $imagedir . '/' . $filename;
        if (!file_exists($imagedir)) {
            @mkdir($imagedir, 0755, true);
            if (!file_exists($imagedir)) {
                $this->error('Can not write to cache dir');
            }
        }
        $this->process([
            'file'          => $file,
            'filepath'      => $_SERVER['DOCUMENT_ROOT'] . '/' . $file,
            'image'         => $image,
            'type'          => $type,
            'height'        => $height,
            'width'         => $width,
            'cropratio'     => $cropratio,
            'imagedir'      => $imagedir,
            'extension'     => $extension
        ]);
    }

    public function error ($msg) {
        http_response_code(400);
        echo $msg;
        exit;
    }

    private static function GCD($a, $b) {
        while ($b != 0) {
            $remainder = $a % $b;
            $a = $b;
            $b = $remainder;
        }
        return abs ($a);
    }

    public static function aspectRatio($width, $height) {
        if(!isset($width) || !(isset($height))) {
            throw new Exception('Must provide Height and Width');
        }
        $gcd = self::GCD($width, $height);
        $a = $width/$gcd;
        $b = $height/$gcd;
        return $ratio = $a . ":" . $b;
    }

    public function getPath($url, $width, $height, $cropratio=false) {
        $test = strtolower(substr($url, 0, 5));
        $type = 'L';
        if ($test == 'https') {
            $type = 'ES';
            $url = substr($url, 7);
        } elseif ($test == 'http:') {
            $type = 'E';
            $url = substr($url, 6);
        } else {
            if (substr($test, 0, 1) != '/') {
                throw new Exception('path must be absolute or external');
            }
        }
        if ($cropratio === false) {
            $cropratio = $width . ':'. $height;
        }
        $path = $width . '/' . $height . '/' . $cropratio . '/' . $type . $url;
        return '/imagecache/' . $path . '?' . $this->secret->encrypt($path);
    }

    private function getExternalFile (array &$options) {
        if ($options['type'] == 'E') {
            $external = 'http://' . $options['file'];
        } else {
            $external = 'https://' . $options['file'];
        }
        $options['filepath'] = $_SERVER['DOCUMENT_ROOT'] . '/imagecache/' . uniqid() . '.' . $options['extension'];
        file_put_contents($options['filepath'], file_get_contents($external));
        $this->unlink = true;
    }

    private function process (array $options) {
        if ($options['type'] == 'E' || $options['type'] == 'ES') {
            $this->getExternalFile($options);
        }
        if (preg_match('/(\.\.|<|>)/', (string)$options['file'])) {
            $this->error('Bad Request Error: malformed image path. Image paths must begin with \'/\', ' . $options['file']);
        }
        if (!file_exists($options['filepath'])) {
            $this->error('Not Found Error: image does not exist: ' . $options['filepath']);
        }
        $size = GetImageSize($options['filepath']);
        $mime = $size['mime'];
        if (substr($mime, 0, 6) != 'image/') {
            $this->error('Bad Request Error: requested file is not an accepted type: ' . $options['filepath']);
        }

        $width          = $size[0];
        $height         = $size[1];
        $maxWidth       = $options['width'];
        $maxHeight      = $options['height'];
        $color          = FALSE;
        $offsetX    = 0;
        $offsetY    = 0;

        if (isset($options['cropratio'])) {
            $cropRatio      = explode(':', (string) $options['cropratio']);
            if (count($cropRatio) == 2) {
                $ratioComputed      = $width / $height;
                $cropRatioComputed  = (float) $cropRatio[0] / (float) $cropRatio[1];

                if ($ratioComputed < $cropRatioComputed) { // Image is too tall so we will crop the top and bottom
                    $origHeight = $height;
                    $height     = $width / $cropRatioComputed;
                    $offsetY    = ($origHeight - $height) / 2;
                } else if ($ratioComputed > $cropRatioComputed) { // Image is too wide so we will crop off the left and right sides
                    $origWidth  = $width;
                    $width      = $height * $cropRatioComputed;
                    $offsetX    = ($origWidth - $width) / 2;
                }
            }
        }

        $xRatio     = $maxWidth / $width;
        $yRatio     = $maxHeight / $height;
        if ($xRatio * $height < $maxHeight) { // Resize the image based on width
            $tnHeight   = ceil($xRatio * $height);
            $tnWidth    = $maxWidth;
        } else {
            $tnWidth    = ceil($yRatio * $width);
            $tnHeight   = $maxHeight;
        }
        ini_set('memory_limit', self::$memory);

        switch ($size['mime']) {
            case 'image/gif':
                //we will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
                //this is maybe not the ideal solution, but IE6 can suck it
                $creationFunction   = 'ImageCreateFromGif';
                $outputFunction     = 'ImagePng';
                $mime               = 'image/png'; // We need to convert GIFs to PNGs
                $doSharpen          = FALSE;
                self::$quality      = round(10 - (self::$quality / 10)); // We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
                break;

            case 'image/x-png':
            case 'image/png':
                $creationFunction   = 'ImageCreateFromPng';
                $outputFunction     = 'ImagePng';
                $doSharpen          = FALSE;
                self::$quality      = round(10 - (self::$quality / 10)); // PNG needs a compression level of 0 (no compression) through 9
                break;

            default:
                $creationFunction   = 'imagecreatefromjpeg';
                $outputFunction     = 'ImageJpeg';
                $doSharpen          = TRUE;
                break;
        }

        $dst = imagecreatetruecolor($tnWidth, $tnHeight);
        $src = $creationFunction($options['filepath']);
        if (in_array($size['mime'], ['image/gif', 'image/png'])) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        ImageCopyResampled($dst, $src, 0, 0, $offsetX, $offsetY, $tnWidth, $tnHeight, $width, $height);
        if ($doSharpen) {
            $sharpness  = self::findSharp($width, $tnWidth);
            $sharpenMatrix  = [
                [-1, -2, -1],
                [-2, $sharpness + 12, -2],
                [-1, -2, -1]
            ];
            $divisor        = $sharpness;
            $offset         = 0;
            imageconvolution($dst, $sharpenMatrix, $divisor, $offset);
        }
        $result = $outputFunction($dst, $options['image'], self::$quality);
        if ($result !== true) {
            $this->error('Can not write file');
        }
        ImageDestroy($src);
        ImageDestroy($dst);
        if ($this->unlink === true) {
            unlink($options['filepath']);
        }
        header('Content-type: ' . $mime);
        header('Content-Length: ' . filesize($options['image']));
        echo file_get_contents($options['image']);
    }

    private static function findSharp($orig, $final) {
        $final  = $final * (750.0 / $orig);
        $a      = 52;
        $b      = -0.27810650887573124;
        $c      = .00047337278106508946;

        $result = $a + $b * $final + $c * $final * $final;

        return max(round($result), 0);
    }
}