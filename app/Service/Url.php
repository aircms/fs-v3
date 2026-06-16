<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Front;
use Exception;

class Url
{
  protected static function parseModifiedImageUrl(string $url, string $storageRoot): array
  {
    $width   = null;
    $height  = null;
    $quality = null;
    $source  = null;

    $pi     = pathinfo($url);
    $format = $pi['extension'];

    if (!str_contains($pi['filename'], '_mod_')) {
      throw new Exception();
    }

    $settings = explode('_', explode('_mod_', $pi['filename'])[1]);
    foreach ($settings as $setting) {
      $value = intval(substr($setting, 1));

      if (($setting[0] ?? false) === 'w') {
        $width = $value;

      } else if (($setting[0] ?? false) === 'h') {
        $height = $value;

      } else if (($setting[0] ?? false) === 'q') {
        $quality = $value;
      }
    }

    $dirname  = array_filter(explode('/', $pi['dirname']));
    $dirname  = implode('/', $dirname);
    $filePath = dirname(realpath($storageRoot)) . '/' . $dirname . '/' . explode('_mod_', $pi['filename'])[0];
    $dest     = dirname(realpath($storageRoot)) . '/' . $dirname . '/' . $pi['basename'];

    foreach (ImageProcessor::SUPPORTED_IMAGE_FORMATS as $ext) {
      $candidate = $filePath . '.' . $ext;
      if (file_exists($candidate)) {
        $source = $candidate;
        break;
      }
    }

    if (!$source) {
      throw new Exception('Candidate not found');
    }

    return [
      'width'   => $width,
      'height'  => $height,
      'quality' => $quality ?? 80,
      'format'  => $format,
      'source'  => $source,
      'dest'    => $dest,
    ];
  }

  public static function normalize(string $url): string
  {
    $shortUrl = array_values(array_filter(explode('/', $url)));
    return '/' . implode('/', $shortUrl);
  }

  public static function propagateImage(): string
  {
    $front = Front::getInstance();

    $params = self::parseModifiedImageUrl(
      self::normalize($front->getRequest()->getUri()),
      realpath($front->getConfig()['fs']['path'])
    );

    $image = new ImageProcessor((string)$params['source']);

    if ($params['width'] && $params['height']) {
      $image->cropAndResize($params['width'], $params['height']);

    } elseif ($params['width'] || $params['height']) {
      $image->resizeToLongSide($params['width'] ?? $params['height']);
    }

    $image->save($params['dest'], quality: $params['quality']);

    return Front::getInstance()->getConfig()['fs']['host'] . $front->getRequest()->getUri();
  }
}