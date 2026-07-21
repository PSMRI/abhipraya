<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class DepartmentRepository{
 public function __construct(private PDO $pdo){}
 public function paginate(array $filters,int $page=1,int $limit=20):array{}
 public function findById(int $id):?array{}
 public function create(array $data):int{}
 public function update(int $id,array $data):bool{}
 public function delete(int $id):bool{}
 public function activate(int $id):bool{}
 public function deactivate(int $id):bool{}
 public function assignSurvey(int $departmentId,int $surveyId):bool{}
 public function removeSurvey(int $departmentId,int $surveyId):bool{}
 public function hierarchy():array{}
}
