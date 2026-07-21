<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class QRRepository{
 public function __construct(private PDO $pdo){}
 public function create(array $data):int{}
 public function regenerate(int $id):bool{}
 public function findByToken(string $token):?array{}
 public function paginate(array $filters,int $page=1,int $limit=20):array{}
 public function activate(int $id):bool{}
 public function deactivate(int $id):bool{}
 public function statistics(array $filters=[]):array{}
}
