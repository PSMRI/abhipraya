<?php
namespace App\Services;
class QRValidationException extends \RuntimeException{public function __construct(string $m='Validation failed.',private array $errors=[]){parent::__construct($m,422);} public function errors():array{return $this->errors;}}