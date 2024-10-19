<?php
/**
 * @doc https://github.com/Respect/Validation/blob/2.2/docs/custom-rules.md
 */
namespace SchemableValidator\Rules;

require SV_ROOT_DIR . "/vendor/autoload.php";

use Respect\Validation\Rules\AbstractRule;

final class FileExtension extends AbstractRule {
  protected $allowedExtensions;

  public function __construct(array $allowedExtensions) {
    $this->allowedExtensions = array_map('strtolower', $allowedExtensions);
  }

  public function validate($tmpName): bool {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmpName);

    finfo_close($finfo);

    return in_array($mime_type, $this->allowedExtensions, true);
  }
}
