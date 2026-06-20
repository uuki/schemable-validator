<?php

// Japanese locale presets, keyed by the engine-neutral rule vocabulary
// (see I18n/DefaultMessages.php). Resolved by MessageDict::resolve() with the
// same neutral ruleId the RespectExecutableValidator derives via describeViolations().
return [
  'string'    => '文字列で入力してください',
  'integer'   => '整数で入力してください',
  'number'    => '数値で入力してください',
  'boolean'   => '真偽値で入力してください',
  'minLength' => '最低{min}文字で入力してください',
  'maxLength' => '最大{max}文字まで入力できます',
  'minimum'   => '{min}以上で入力してください',
  'maximum'   => '{max}以下で入力してください',
  'email'     => '有効なメールアドレスを入力してください',
  'uri'       => '有効なURLを入力してください',
  'date'      => '有効な日付を入力してください',
  'date-time' => '有効な日時を入力してください',
  'time'      => '有効な時刻を入力してください',
  'uuid'      => '有効なUUIDを入力してください',
  'ipv4'      => '有効なIPv4アドレスを入力してください',
  'ipv6'      => '有効なIPv6アドレスを入力してください',
  'hostname'  => '有効なホスト名を入力してください',
  'pattern'   => '入力形式が正しくありません',
  'enum'      => '選択肢から選んでください',
  'required'  => '必須項目です',
];
