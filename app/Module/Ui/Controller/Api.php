<?php

declare(strict_types=1);

namespace App\Module\Ui\Controller;

use App\Service\Fs;
use App\Service\Item;
use Throwable;

class Api extends Base
{
  public function init(): void
  {
    parent::init();

    $this->getView()->setLayoutEnabled(false);
    $this->getView()->setAutoRender(false);
  }

  public function deleteFolder(string $path): void
  {
    $folder = Item::instance($path);
    Fs::deleteFolder($folder->realPath);
  }

  public function info(array $paths): array
  {
    $files = [];
    foreach ($paths as $path) {
      $files[] = Item::instance($path)->toArray();
    }
    return $files;
  }

  public function uploadByUrl(string $url, string $path = Fs::ROOT): array
  {
    return Fs::uploadByUrl($url, $path)->toArray();
  }

  public function deleteFile(string $path): void
  {
    Fs::deleteFile($path);
  }

  public function uploadFile(string $path): array
  {
    Fs::createFolder($path, Fs::ROOT, true);

    $files = [];
    foreach (Fs::uploadFileApi($path) as $file) {
      $files[] = $file->toArray();
    }
    return $files;
  }

  public function createFolder(string $name, string $path, bool $recursive = false): array
  {
    return Fs::createFolder($name, $path, $recursive)->toArray();
  }

  public function uploadDatum(string $path, array $datum): array
  {
    $files = [];
    foreach ($datum as $index => $data) {
      try {
        $files[$index] = Fs::uploadData($path, $data)->toArray();
      } catch (Throwable) {

      }
    }
    return $files;
  }

  public function refactor(string $path, ?int $width = null, ?int $height = null, ?int $quality = null): void
  {
    ini_set('memory_limit', '-1');
    Fs::refactor($path, $width, $height, $quality);
  }

  public function annotation(
    string $folder,
    string $fileName,
    string $title,
    string $backColor,
    string $frontColor
  ): array
  {
    return Fs::annotation($folder, $fileName, $title, $backColor, $frontColor)->toArray();
  }
}