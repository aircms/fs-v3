<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Front;
use Exception;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Imagick;
use Throwable;

class Item
{
  public ?string $dirName;
  public ?string $name;
  public ?string $ext;

  public ?string $realPath;
  public ?string $thumbnailRealPath;

  public ?string $path;
  public ?string $thumbnailPath;

  public ?string $url;
  public ?string $thumbnailUrl;

  public ?int $time;
  public ?string $mime;
  public ?int $size;
  public ?array $dims;

  public function __construct(string $path)
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $path   = Url::normalize($path);

    if (!($fullPath = realpath($config['path'] . $path))) {
      $fullPath = realpath(dirname($config['path']) . $path);
      if (!$fullPath) {
        $fullPath = realpath($path);
      }
    }

    if (!$fullPath) {
      throw new Exception('Item not found');
    }

    $path     = substr($fullPath, strlen(dirname($config['path'])));
    $stat     = stat($fullPath);
    $pathInfo = pathinfo($fullPath);

    $this->name     = $pathInfo['filename'];
    $this->path     = $path;
    $this->time     = $stat['ctime'] ?? false;
    $this->realPath = $fullPath;
    $this->dirName  = dirname($fullPath);
    $this->url      = $config['host'] . $path;

    if (is_file($fullPath)) {
      $this->mime = mime_content_type($fullPath);
      $this->size = $stat['size'] ?? false;
      $this->ext  = strtolower($pathInfo['extension'] ?? '');
      $this->thumbnail();

      try {
        $this->dims = [
          'width'  => 0,
          'height' => 0
        ];
        $dims       = getimagesize($fullPath);
        if ($dims) {
          $this->dims = [
            'width'  => $dims[0],
            'height' => $dims[1]
          ];
        }
      } catch (Throwable) {
      }

    } else {
      $this->mime = 'directory';
      $this->size = 0;
    }
  }

  public function getFormattedSize(?bool $decimal = true): string
  {
    if (!$this->size) {
      return '0b';
    }

    $bytes    = $this->size;
    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;

    if ($bytes < $kilobyte) {
      return $bytes . ' b';

    } elseif ($bytes < $megabyte) {
      return round($bytes / $kilobyte, $decimal ? 2 : 0) . ' kb';

    } elseif ($bytes < $gigabyte) {
      return round($bytes / $megabyte, $decimal ? 2 : 0) . ' mb';
    }

    return round($bytes / $gigabyte, $decimal ? 2 : 0) . ' gb';
  }

  public function isFile(): bool
  {
    return $this->mime !== 'directory';
  }

  public function getSimplifyMime(): string
  {
    $types = ['image', 'video', 'pdf', 'text'];

    foreach ($types as $type) {
      if (str_contains($this->mime, $type)) {
        return $type;
      }
    }

    return 'file';
  }

  public function toArray(): array
  {
    return [
      'size'      => $this->size,
      'mime'      => $this->mime,
      'time'      => $this->time,
      'dims'      => $this->dims ?? null,
      'src'       => $this->path,
      'thumbnail' => $this->thumbnailPath ?? null,
    ];
  }

  public function toJSON(): string
  {
    return htmlspecialchars(json_encode($this->toArray()), ENT_QUOTES, 'UTF-8');
  }

  private function thumbnail(): void
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $ext    = $this->getSimplifyMime() === 'image' ? $this->ext : 'png';

    $thumbFilename = $this->name . '_thumbnail_.' . $ext;
    $thumbFullPath = Url::normalize($this->dirName . '/' . $thumbFilename);

    $this->thumbnailRealPath = $thumbFullPath;
    $this->thumbnailPath     = dirname($this->path) . '/' . $thumbFilename;
    $this->thumbnailUrl      = $config['host'] . $this->thumbnailPath;

    if (file_exists($thumbFullPath)) {
      return;
    }

    $im = new Imagick();

    try {
      if ($this->getSimplifyMime() === 'video') {
        $ffMpeg = FFMpeg::create();
        $video  = $ffMpeg->open($this->realPath);
        $frame  = $video->frame(TimeCode::fromSeconds(0));
        $im->readImageBlob($frame->save(null, true, true));

      } elseif ($this->getSimplifyMime() === 'pdf') {
        $im->readImage($this->realPath . '[0]');
        $im->setImageFormat('png');

      } elseif (str_contains($this->mime, 'gif')) {
        $im->readImage($this->realPath . '[0]');

      } elseif ($this->getSimplifyMime() === 'image') {
        $im->readImage($this->realPath);
      }

      if ($im->getImageWidth() > $config['thumbnail']['width']) {
        $im->cropThumbnailImage($config['thumbnail']['width'], $config['thumbnail']['height']);
      }

      $im->writeImage($thumbFullPath);
      $im->clear();

    } catch (Throwable) {
      copy($this->realPath, $thumbFullPath);
    }
  }

  public static function instance(string $path): Item
  {
    return new Item($path);
  }
}
