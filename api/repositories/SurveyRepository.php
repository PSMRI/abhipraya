<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class SurveyRepository{
 public function __construct(private PDO $pdo){}
 public function paginate(array $filters,int $page=1,int $limit=20):array{}
 public function findById(int $id):?array{}
 public function codeExists(string $code):bool{}
 public function create(array $data):int{}
 public function update(int $id,array $data):bool{}
 public function delete(int $id):bool{}
 public function createVersion(array $data):int{}
 public function getVersions(int $surveyId):array{}
 public function findVersionById(int $id):?array{}
 public function publishVersion(int $id):bool{}
}
