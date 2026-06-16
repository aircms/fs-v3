<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Front;

class Locale
{
  public static ?array $keys = null;
  public static ?string $lang = null;

  public static function setLang(string $lang): void
  {
    self::$lang = $lang;
  }

  public static function t(string $key): string
  {
    if (!self::$keys) {
      self::$keys = Front::getInstance()->getConfig()['locale'][self::$lang];
    }
    if (!isset(self::$keys[$key])) {
      return $key;
    }
    return self::$keys[$key];
  }

  public static function phrases(): array
  {
    return Front::getInstance()->getConfig()['locale'][self::$lang];
  }
}
