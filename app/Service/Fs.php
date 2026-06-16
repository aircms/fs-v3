<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Front;
use Exception;
use Imagick;
use ImagickDraw;
use Mimey\MimeTypes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class Fs
{
  const string ROOT = '/';

  public static function createFolder(string $name, string $path = self::ROOT, bool $recursive = false): Item
  {
    if (!preg_match('/^[a-z0-9\/]+$/', $name)) {
      throw new Exception('Folder name must be "a-z0-9"');
    }
    try {
      mkdir(Item::instance($path)->realPath . '/' . $name, 0755, $recursive);
    } catch (Throwable) {
    }
    return Item::instance($path . '/' . $name);
  }

  public static function deleteFolder(string $path): void
  {
    $item = Item::instance($path);
    self::deleteFolderRecursively($item->realPath);
  }

  private static function deleteFolderRecursively(string $fullPath): void
  {
    foreach (glob($fullPath . '/*', GLOB_MARK) as $item) {
      if (is_dir($item)) {
        self::deleteFolderRecursively($item);
      } else {
        unlink($item);
      }
    }
    rmdir($fullPath);
  }

  public static function deleteFile(string $path): void
  {
    $item = Item::instance($path);
    foreach (glob($item->dirName . '/' . $item->name . '_mod_*') as $mod) {
      unlink($mod);
    }
    unlink($item->realPath);
    unlink($item->thumbnailRealPath);
  }

  private static function mapFiles(): array
  {
    return array_map(function ($name, $type, $tmpName, $error, $size) {
      return [
        'name'    => $name,
        'type'    => $type,
        'tmpName' => $tmpName,
        'error'   => $error,
        'size'    => $size
      ];

    }, $_FILES['files']['name'],
      $_FILES['files']['type'],
      $_FILES['files']['tmp_name'],
      $_FILES['files']['error'],
      $_FILES['files']['size']
    );
  }

  public static function uploadFile(string $path): array
  {
    $files = self::mapFiles();

    $errors = [];

    foreach ($files as $file) {
      try {
        $fileNameParts = explode('.', $file['name']);
        $fileName      = strtolower(md5(microtime()) . '.' . array_pop($fileNameParts));

        $item = Item::instance($path);

        copy($file['tmpName'], $item->realPath . '/' . $fileName);
      } catch (Throwable) {
        $errors[] = 'File "' . $file['name'] . '" was not uploaded.';
      }
    }

    return $errors;
  }

  public static function uploadFileApi(string $path): array
  {
    $config = Front::getInstance()->getConfig();
    $files  = [];

    foreach (self::mapFiles() as $file) {
      try {
        $fileNameParts = explode('.', $file['name']);
        $fileName      = md5(microtime()) . '.' . array_pop($fileNameParts);

        copy($file['tmpName'], realpath($config['fs']['path']) . $path . '/' . $fileName);

        $files[] = Item::instance($path . '/' . $fileName);

      } catch (Throwable) {
      }
    }
    return $files;
  }

  public static function uploadData(string $path, array $data): Item
  {
    if ($data['type'] === 'base64') {
      $fileContent = base64_decode(explode('base64,', $data['data'])[1]);
      $fileName    = explode(';', $data['data'])[0];
      $fileName    = explode('/', $fileName)[1];
      $fileName    = md5(microtime()) . '.' . trim($fileName);

      $filePath = Item::instance($path)->realPath . '/' . $fileName;

      file_put_contents($filePath, $fileContent);

    } else {
      $fileName = md5(microtime()) . '.' . $data['type'];
      $filePath = Item::instance($path)->realPath . '/' . $fileName;

      file_put_contents($filePath, $data['data']);
    }

    return Item::instance($path . '/' . $fileName);
  }

  public static function uploadByUrl(string $url, string $path = self::ROOT): Item
  {
    $file = file_get_contents($url, false, stream_context_create([
      'ssl' => [
        'ciphers'           => 'DEFAULT:!DH',
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
      ]
    ]));

    try {
      $item = Item::instance($path);
    } catch (Throwable) {
      $item = self::createFolder($path, self::ROOT, true);
    }

    $fileId = md5(microtime());
    $name   = $item->realPath . '/' . $fileId;

    file_put_contents($name, $file);

    $mimes     = new MimeTypes();
    $extension = $mimes->getExtension(mime_content_type($name));

    rename($name, $name . '.' . $extension);

    return Item::instance($path . '/' . $fileId . '.' . $extension);
  }

  public static function listFolder(string $path = self::ROOT): array
  {
    $items = [];

    foreach (glob($path . '/*') as $item) {
      if (
        str_contains(basename($item), '_mod_') ||
        str_contains(basename($item), '_thumbnail_') ||
        str_ends_with(basename($item), '.metadata')
      ) {
        continue;
      }

      $items[] = $item;
    }
    return self::prepareItems($items);
  }

  public static function search(string $query): array
  {
    $config = Front::getInstance()->getConfig();
    $path   = $config['fs']['path'];

    $it    = new RecursiveDirectoryIterator($path);
    $items = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator($it) as $file) {
      if (
        str_contains(strtolower(basename($file->getRealPath())), strtolower($query)) &&
        !str_contains(strtolower(basename($file->getRealPath())), '_mod_') &&
        !str_contains(strtolower(basename($file->getRealPath())), '_thumbnail_') &&
        !str_ends_with(strtolower(basename($file->getRealPath())), '.metadata')
      ) {
        $items[] = $file->getRealPath();
      }
    }
    return self::prepareItems($items);
  }

  /**
   * @param string[] $items
   * @return Item[]
   */
  public static function prepareItems(array $items): array
  {
    natcasesort($items);
    return array_map(fn(string $item) => Item::instance($item), $items);
  }

  public static function refactor(string $path, ?int $width = null, ?int $height = null, ?int $quality = null): void
  {
    $item = Item::instance($path);

    $imageProcessor = new ImageProcessor($item->realPath);

    if ($width && $height) {
      $imageProcessor->cropAndResize($width, $height);

    } elseif ($width || $height) {
      $imageProcessor->resizeToLongSide($width ?? $height);
    }

    $imageProcessor->save($item->realPath, quality: $quality);
  }

  public static function annotation(
    string $folder,
    string $fileName,
    string $title,
    string $backColor,
    string $frontColor
  ): Item
  {
    $im = new Imagick();
    $im->newImage(500, 500, '#' . $backColor);

    $textDraw = new ImagickDraw();
    $textDraw->setFontSize(70);
    $textDraw->setFillColor('#' . $frontColor);
    $textDraw->setGravity(Imagick::ALIGN_CENTER);
    $im->annotateImage($textDraw, 0, 225, 0, $title);
    $im->setImageFormat("png");

    $config = Front::getInstance()->getConfig()['fs'];
    $im->writeImage($config['path'] . '/' . $folder . '/' . $fileName . '.png');
    $im->destroy();

    return Item::instance('/' . $folder . '/' . $fileName . '.png');
  }
}