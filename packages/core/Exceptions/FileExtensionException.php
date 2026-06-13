<?php
/**
 * @doc https://github.com/Respect/Validation/blob/2.2/docs/custom-rules.md
 */
namespace SchemableValidator\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class FileExtensionException extends ValidationException {
  protected $defaultTemplates = [
    self::MODE_DEFAULT => [
        self::STANDARD => 'Illegal file extension.',
    ],
    self::MODE_NEGATIVE => [
        self::STANDARD => 'Illegal file extension.',
    ],
  ];
}