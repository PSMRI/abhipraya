<?php
declare(strict_types=1);
namespace App\Services;
final class ReportService{
 public function dashboard(array $f=[]):array{}
 public function summary(array $f=[]):array{}
 public function facility(array $f=[]):array{}
 public function department(array $f=[]):array{}
 public function survey(array $f=[]):array{}
 public function question(array $f=[]):array{}
 public function trends(array $f=[]):array{}
 public function comparison(array $f=[]):array{}
 public function geo(array $f=[]):array{}
 public function export(array $f,string $fmt='csv'):array{}
 public function scheduled(array $f=[]):array{}
 public function metadata():array{}
}
