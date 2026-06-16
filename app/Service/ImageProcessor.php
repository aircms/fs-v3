<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Imagick;

class ImageProcessor
{
  private Imagick $image;
  private string $format;

  const array SUPPORTED_IMAGE_FORMATS = ['jpeg', 'jpg', 'png', 'webp', 'avif'];

  public function __construct(string $imagePath)
  {
    if (!file_exists($imagePath)) {
      throw new Exception("File not found: $imagePath");
    }

    $this->image  = new Imagick($imagePath);
    $this->format = strtolower($this->image->getImageFormat());

    if (!in_array($this->format, self::SUPPORTED_IMAGE_FORMATS)) {
      throw new Exception("Unsupported image format: {$this->format}");
    }

    $this->image->setImageColorspace(Imagick::COLORSPACE_RGB);
  }

  public function cropAndResize(int $targetWidth, int $targetHeight): void
  {
    $origWidth  = $this->image->getImageWidth();
    $origHeight = $this->image->getImageHeight();

    $targetRatio = $targetWidth / $targetHeight;
    $origRatio   = $origWidth / $origHeight;

    if ($origRatio > $targetRatio) {
      $newWidth  = intval($origHeight * $targetRatio);
      $newHeight = $origHeight;
      $x         = intval(($origWidth - $newWidth) / 2);
      $y         = 0;

    } else {
      $newWidth  = $origWidth;
      $newHeight = intval($origWidth / $targetRatio);
      $x         = 0;
      $y         = intval(($origHeight - $newHeight) / 2);
    }

    $this->image->cropImage($newWidth, $newHeight, $x, $y);
    $this->image->setImagePage(0, 0, 0, 0);

    $this->image->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
  }

  public function resizeToLongSide(int $longSide): void
  {
    $width  = $this->image->getImageWidth();
    $height = $this->image->getImageHeight();

    if ($width >= $height) {
      $newWidth  = $longSide;
      $newHeight = intval($height * ($longSide / $width));
    } else {
      $newHeight = $longSide;
      $newWidth  = intval($width * ($longSide / $height));
    }

    $this->image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
  }

  public function save(string $destPath, int $quality = 70): void
  {
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $this->image->setImageFormat('jpeg');
        $this->image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $this->image->setImageCompressionQuality($quality);
        break;

      case 'png':
        $this->image->setImageFormat('png');
        $this->image->setOption('png:compression-level', (string)intval(9 * (100 - $quality) / 100));
        break;

      case 'webp':
        $origSize   = strlen($this->image->getImageBlob());
        $targetSize = (int)($origSize * ($quality / 100));

        $this->image->setImageFormat('webp');
        $this->image->setOption('webp:lossless', 'false');
        $this->image->setOption('webp:method', '6');
        $this->image->setOption('webp:target-size', (string)$targetSize);
        break;

      case 'avif':
        $this->image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $this->image->setImageFormat('AVIF');
        $this->image->setOption('heic:speed', '6');
        $this->image->setImageCompressionQuality($quality);
        break;

      default:
        throw new Exception("Unsupported output format: $ext");
    }

    $this->image->stripImage();
    $this->image->writeImage($destPath);
    $this->image->destroy();
  }
}
