<?php
declare(strict_types=1);
namespace App\Services;
final class QRService{
 public function generate(array $d):array{}
 public function regenerate(int $id):array{}
 public function list(array $f=[]):array{}
 public function preview(int $id):array{}
 public function download(int $id,string $fmt='png'):array{}
 public function validate(string $token):array{}
 public function activate(int $id):bool{}
 public function deactivate(int $id):bool{}
 public function statistics(array $f=[]):array{}
 public function bulkGenerate(int $facility):array{}
}
