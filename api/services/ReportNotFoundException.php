<?php
declare(strict_types=1);
namespace App\Services;
final class ReportNotFoundException extends \RuntimeException{
public function __construct(string $m='Report not found.'){parent::__construct($m,404);}
}
