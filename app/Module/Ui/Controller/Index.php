<?php

declare(strict_types=1);

namespace App\Module\Ui\Controller;

use App\Service\Fs;
use App\Service\Item;
use Throwable;

class Index extends Base
{
  public function init(): void
  {
    parent::init();

    $this->getView()->setAutoRender(false);
  }

  public function index(?string $path = Fs::ROOT): void
  {
    $this->getView()->setAutoRender(true);

    $path = Item::instance($path);

    $this->getView()->assign('items', Fs::listFolder($path->realPath));
    $this->getView()->assign('workingPath', $path->path);
  }

  public function createFolder(string $name, string $path): array
  {
    try {
      $folder = Fs::createFolder($name, $path);
      return ['path' => $folder->path];

    } catch (Throwable $e) {
      $this->getResponse()->setStatusCode(400);
      return ['message' => $e->getMessage()];
    }
  }

  public function uploadFile(string $path): array
  {
    $errors = Fs::uploadFile($path);

    if (count($errors)) {
      $this->getResponse()->setStatusCode(400);
    }

    return ['errors' => $errors];
  }

  public function uploadByUrl(string $url, string $path): array
  {
    return ['path' => Fs::uploadByUrl($url, $path)->path];
  }

  public function search(string $query): void
  {
    $this->getView()->setAutoRender(true);
    $this->getView()->assign('items', Fs::search($query));
  }

  public function removeMultiple(array $paths): void
  {
    foreach ($paths as $path) {
      $item = Item::instance($path);
      $item->isFile() ? Fs::deleteFile($path) : Fs::deleteFolder($path);
    }
  }

  public function deleteFolder(string $path): void
  {
    Fs::deleteFolder($path);
  }

  public function deleteFile(string $path): void
  {
    Fs::deleteFile($path);
  }
}
