<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class ReportRepository{
 public function __construct(private PDO $pdo){}
 public function dashboard(array $filters=[]):array{}
 public function facility(array $filters=[]):array{}
 public function department(array $filters=[]):array{}
 public function survey(array $filters=[]):array{}
 public function question(array $filters=[]):array{}
 public function trends(array $filters=[]):array{}
 public function comparison(array $filters=[]):array{}
 public function geo(array $filters=[]):array{}
}
