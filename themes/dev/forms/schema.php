<?php
use Respect\Validation\Validator as v;

$get_schema = function ($data) {
  return [
    'type' => v::notEmpty()->in(['option1', 'option2', 'option3']),
    'name' => v::stringType()->length(1, 50),
    'email' => v::email(),
    'email_confirm' => v::email()->equals($data['email']),
    'phone' => v::phone()->length(10, 15),
    'url' => v::url(),
    'address' => v::stringType()->length(1, 255),
    'message' => v::stringType()->length(1, 1000),
    'usage' => v::notEmpty()->in(['for_business', 'for_personal']),
    'agreement' => v::trueVal()
  ];
};