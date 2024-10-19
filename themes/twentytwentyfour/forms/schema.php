<?php
use Respect\Validation\Validator as v;

$get_schema = function ($data) {
  return [
    'type' => v::notEmpty()
                ->setTemplate('Please select an item')
                ->in(['option1', 'option2', 'option3']),
    'name' => v::stringType()->length(1, 50),
    'email' => v::email(),
    'email_confirm' => v::email()->equals($data['email']),
    'phone' => v::phone()->length(10, 15),
    'url' => v::url(),
    'address' => v::stringType()->length(1, 255),
    'body' => v::stringType()->length(1, 1000),
    'usage' => v::notEmpty()->in(['for_business', 'for_personal']),
    'docs' => v::key('error', v::equals(UPLOAD_ERR_OK))
      // ->key('tmp_name', v::fileExtension(['jpg', 'png'])),
      ->key('name', v::oneOf(
        v::extension('jpg'),
        v::extension('png'),
      ))
      ->key('tmp_name', v::fileSize('3MB')),
    'agreement' => v::trueVal()
  ];
};