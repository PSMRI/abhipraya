<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class ResponseRepository{
 public function __construct(private PDO $pdo){}
 public function paginate(array $filters,int $page=1,int $limit=20):array{}
 public function findById(int $id):?array{}
 public function delete(int $id):bool{}
 public function analytics(array $filters=[]):array{}
 public function timeline(array $filters=[]):array{}
 public function summary(array $filters=[]):array{}
}
