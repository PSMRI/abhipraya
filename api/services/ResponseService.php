<?php
declare(strict_types=1);
namespace App\Services;
final class ResponseService{
 public function listResponses(array $f=[]):array{}
 public function getResponse(int $id):array{}
 public function deleteResponse(int $id):bool{}
 public function exportResponses(array $f,string $fmt='csv'):array{}
 public function getAnalytics(array $f=[]):array{}
 public function getTimeline(array $f=[]):array{}
 public function findDuplicates(array $f=[]):array{}
 public function getDeviceHistory(string $device,array $f=[]):array{}
 public function getLocationHistory(int $facility,array $f=[]):array{}
 public function getSummary(array $f=[]):array{}
}
