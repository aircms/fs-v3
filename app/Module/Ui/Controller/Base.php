<?php

declare(strict_types=1);

namespace App\Module\Ui\Controller;

use Air\Cookie;
use Air\Core\Controller;
use Air\Core\Front;
use App\Service\Locale;
use Exception;

class Base extends Controller
{
  public function init(): void
  {
    parent::init();

    $key = Front::getInstance()->getConfig()['key'];

    if ($key === $this->getParam('key')) {
      Cookie::set('key', $this->getParam('key'));
    }

    if (!Cookie::get('key') || Cookie::get('key') !== $key) {
      throw new Exception('Not authorized');
    }

    $lang = $this->getParam('lang') ?? Cookie::get('lang') ?? 'en';

    Locale::setLang($lang);
    Cookie::set('lang', $lang);

    $this->getView()->assign('lang', $lang);
    $this->getView()->assign('theme', $this->getParam('theme'));
    $this->getView()->assign('select', $this->getParam('select'));

    if ($this->getRequest()->isAjax()) {
      $this->getView()->setLayoutEnabled(false);
    }
  }
}