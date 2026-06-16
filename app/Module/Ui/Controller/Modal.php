<?php

declare(strict_types=1);

namespace App\Module\Ui\Controller;

use App\Service\Item;

class Modal extends Base
{
  public function view(string $path): void
  {
    $this->getView()->assign('file', Item::instance($path));
  }

  public function remove(string $path): void
  {
    $this->getView()->assign('file', Item::instance($path));
  }

  public function removeFolder(string $path): void
  {
    $this->getView()->assign('folder', Item::instance($path));
  }

  public function createFolder(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  public function uploadFromComputer(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  public function uploadByUrl(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }
}