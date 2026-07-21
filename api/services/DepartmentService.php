<?php
declare(strict_types=1);
namespace App\Services;
final class DepartmentService{
 public function list(array $f=[]):array{}
 public function get(int $id):array{}
 public function create(array $d):array{}
 public function update(int $id,array $d):array{}
 public function delete(int $id):bool{}
 public function activate(int $id):bool{}
 public function deactivate(int $id):bool{}
 public function assignSurvey(int $dept,int $survey):bool{}
 public function removeSurvey(int $dept,int $survey):bool{}
 public function hierarchy():array{}
}
