<?php
namespace ImageResizer;
//Based on the work of Joe Lencioni, Smart Image Resizer 1.4.1 (http://shiftingpixel.com)

// for external images
// http://yoursite.com/imagecache/width/height/cropratio/E/www.somesite.com/path/to/file.jpg

// for local images
// http://yoursite.com/imagecache/width/height/cropratio/L/path/to/file.jpg

// in your code, just give the image path the ImageResizer::getpath(url, width, height, cropratio)
class ImageResizer {
	private static $quality = 90;
	private static $memory = '100M';
	private $slim;

	public function __construct ($slim) {
		$this->slim = $slim;
	}

	public function route ($enforceRefer=false) {
		$this->slim->get('/imagecache/:path+', function ($pieces) use ($enforceRefer) {
			if ($enforceRefer) {
				if (!isset($_SERVER['HTTP_REFERER'])) {
					$this->error('Bad request');
				}
				if (substr_count($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) < 1) {
					$this->error('Bad referer');
				}
			}

			if (count($pieces) < 6) {
				$this->error('Invalid Path');
			}

			$width = array_shift($pieces);
			if (!is_numeric($width)) {
				$this->error('Invalid Width ' . $width);
			}

			$height = array_shift($pieces);
			if (!is_numeric($height)) {
				$this->error('Invalid Height ' . $height);
			}

			$cropratio = array_shift($pieces);
			if (substr_count($cropratio, ':') != 1) {
				$this->error('Invalid Crop Ratio');
			}
			$cropPieces = explode(':', $cropratio, 2);
			if ((!isset($cropPieces[0]) || !is_numeric($cropPieces[0])) || (!isset($cropPieces[1]) || !is_numeric($cropPieces[1])) ) {
				$this->error('Invalid Crop Ratio');
			}

			$type = array_shift($pieces);
			if (!in_array($type, array('L', 'E','ES'))) {
				$this->error('Invalid Conversion Type ' . $type);
			}

			$file = implode('/', $pieces);
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			if (!in_array(strtolower($extension), array('jpg', 'jpeg', 'png', 'gif'))) {
				$this->error('Invalid Image Type');
			}

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
			$this->process(array(
				'file' => $file,
				'filepath' => $_SERVER['DOCUMENT_ROOT'] . '/' . $file,
				'image' => $image,
				'type' => $type,
				'height' => $height,
				'width' => $width,
				'cropratio' => $cropratio,
				'imagedir' => $imagedir
			));
		});
	}

	private function error ($msg) {
		header('Status: 400 ' . $msg);
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
			throw new \Exception('Must provide Height and Width');
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
				return;
			}
		}
		if ($cropratio === false) {
			$cropratio = $width . ':'. $height;
		}
		return '/imagecache/' . $width . '/' . $height . '/' . $cropratio . '/' . $type . $url;
	}

	private static function getExternalFile (array $options) {
		if ($options['type'] == 'E') {
			$external = 'http://' . $options['file'];
		} else {
			$external = 'https://' . $options['file'];
		}
		$local = $_SERVER['DOCUMENT_ROOT'] . '/' . $options['file'];
		$filedir = null;
		if (substr_count($local, '/') > 0) {
			$filedir = explode('/', $local);
			array_pop($filedir);
			$filedir = implode('/', $filedir);
		}
		if (!file_exists($filedir)) {
			@mkdir($filedir, 0755, true);
		}
		file_put_contents($local, file_get_contents($external));
	}

	private function process (array $options) {
		//get external file
		if ($options['type'] == 'E' || $options['type'] == 'ES') {
			self::getExternalFile($options);
		}

		//Images must be local files, so for convenience we strip the domain if it's there
		$image = preg_replace('/^(s?f|ht)tps?:\/\/[^\/]+/i', '', (string)$options['file']);

		//for security images cannot contain '..' or '<', and
		if (preg_match('/(\.\.|<|>)/', $image)) {
			$this->error('Bad Request Error: malformed image path. Image paths must begin with \'/\', ' . $options['file']);
		}

		// If the image doesn't exist, or we haven't been told what it is, there's nothing
		// that we can do
		if (!$image) {
			$this->error('Bad Request Error: no image was specified');
		}

		// Strip the possible trailing slash off the document root
		if (!file_exists($options['filepath'])) {
			$this->error('Not Found Error: image does not exist: ' . $options['filepath']);
		}

		// Get the size and MIME type of the requested image
		$size = GetImageSize($options['filepath']);
		$mime = $size['mime'];

		// Make sure that the requested file is actually an image
		if (substr($mime, 0, 6) != 'image/') {
			$this->error('Bad Request Error: requested file is not an accepted type: ' . $options['filepath']);
		}

		$width			= $size[0];
		$height			= $size[1];
		$maxWidth		= $options['width'];
		$maxHeight		= $options['height'];
		$color			= FALSE;

		// Ratio cropping
		$offsetX	= 0;
		$offsetY	= 0;

		if (isset($options['cropratio'])) {
			$cropRatio		= explode(':', (string) $options['cropratio']);
			if (count($cropRatio) == 2) {
				$ratioComputed		= $width / $height;
				$cropRatioComputed	= (float) $cropRatio[0] / (float) $cropRatio[1];

				if ($ratioComputed < $cropRatioComputed) { // Image is too tall so we will crop the top and bottom
					$origHeight	= $height;
					$height		= $width / $cropRatioComputed;
					$offsetY	= ($origHeight - $height) / 2;
				} else if ($ratioComputed > $cropRatioComputed) { // Image is too wide so we will crop off the left and right sides
					$origWidth	= $width;
					$width		= $height * $cropRatioComputed;
					$offsetX	= ($origWidth - $width) / 2;
				}
			}
		}

		// Setting up the ratios needed for resizing. We will compare these below to determine how to
		// resize the image (based on height or based on width)
		$xRatio		= $maxWidth / $width;
		$yRatio		= $maxHeight / $height;

		if ($xRatio * $height < $maxHeight) { // Resize the image based on width
			$tnHeight	= ceil($xRatio * $height);
			$tnWidth	= $maxWidth;
		} else {
			// Resize the image based on height
			$tnWidth	= ceil($yRatio * $width);
			$tnHeight	= $maxHeight;
		}

		// We don't want to run out of memory
		ini_set('memory_limit', self::$memory);

		// Set up the appropriate image handling functions based on the original image's mime type
		switch ($size['mime']) {
			case 'image/gif':
				//we will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
				//this is maybe not the ideal solution, but IE6 can suck it
				$creationFunction	= 'ImageCreateFromGif';
				$outputFunction		= 'ImagePng';
				$mime				= 'image/png'; // We need to convert GIFs to PNGs
				$doSharpen			= FALSE;
				self::$quality			= round(10 - (self::$quality / 10)); // We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
				break;

			case 'image/x-png':
			case 'image/png':
				$creationFunction	= 'ImageCreateFromPng';
				$outputFunction		= 'ImagePng';
				$doSharpen			= FALSE;
				self::$quality		= round(10 - (self::$quality / 10)); // PNG needs a compression level of 0 (no compression) through 9
				break;

			default:
				$creationFunction	= 'imagecreatefromjpeg';
				$outputFunction	 	= 'ImageJpeg';
				$doSharpen			= TRUE;
				break;
		}

		//set up a blank canvas for our resized image (destination)
		$dst = imagecreatetruecolor($tnWidth, $tnHeight);

		//read in the original image
		$src = $creationFunction($options['filepath']);

		if (in_array($size['mime'], array('image/gif', 'image/png'))) {
			//if this is a GIF or a PNG, we need to set up transparency
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
		}

		//resample the original image into the resized canvas we set up earlier
		ImageCopyResampled($dst, $src, 0, 0, $offsetX, $offsetY, $tnWidth, $tnHeight, $width, $height);
		if ($doSharpen) {
			$sharpness	= self::findSharp($width, $tnWidth);
			$sharpenMatrix	= array(
			array(-1, -2, -1),
			array(-2, $sharpness + 12, -2),
			array(-1, -2, -1)
			);
			$divisor		= $sharpness;
			$offset			= 0;
			imageconvolution($dst, $sharpenMatrix, $divisor, $offset);
		}

		//write the resized image to the cache
		$outputFunction($dst, $options['image'], self::$quality);

		//put the data of the resized image into a variable
		ob_start();
		$outputFunction($dst, null, self::$quality);
		$data	= ob_get_contents();
		ob_end_clean();

		//clean up the memory
		ImageDestroy($src);
		ImageDestroy($dst);

		// Send the image to the browser with some delicious headers
		header('Content-type: ' . $mime);
		header('Content-Length: ' . strlen($data));
		echo $data;
	}

	// function from Ryan Rud (http://adryrun.com)
	private static function findSharp($orig, $final) {
		$final	= $final * (750.0 / $orig);
		$a		= 52;
		$b		= -0.27810650887573124;
		$c		= .00047337278106508946;

		$result = $a + $b * $final + $c * $final * $final;

		return max(round($result), 0);
	}
}