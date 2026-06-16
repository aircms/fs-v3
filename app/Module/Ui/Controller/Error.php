<?php

declare(strict_types=1);

namespace App\Module\Ui\Controller;

use Air\Core\ErrorController;
use Air\Core\Exception\ControllerClassWasNotFound;
use Air\Core\Front;
use App\Service\Url;

class Error extends ErrorController
{
  public function index(): array
  {
    $exception = $this->getException();

    if ($exception instanceof ControllerClassWasNotFound) {
      $this->redirect(Url::propagateImage());
    }

    if (Front::getInstance()->getConfig()['air']['exception']) {
      return [
        'error' => true,
        'message' => $this->getException()->getMessage(),
        'trace' => $this->getException()->getTrace(),
      ];
    }

    return [
      'error' => true,
      'message' => $this->getException()->getMessage()
    ];
  }
}