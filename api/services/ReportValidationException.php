<?php
declare(strict_types=1);
namespace App\Services;
final class ReportValidationException extends \RuntimeException{
public function __construct(string $m='Validation failed.',private array $errors=[]){parent::__construct($m,422);}
public function errors():array{return $this->errors;}
}
