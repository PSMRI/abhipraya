<?php
$path = __DIR__ . '/../api/masters/dept_id_1.json';
$rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$types = [1=>'rating',2=>'duration',3=>'binary',4=>'rating',5=>'binary',6=>'binary',7=>'binary',8=>'rating',9=>'rating',10=>'rating',11=>'category',12=>'category',13=>'category',14=>'rating',15=>'numeric',16=>'category',17=>'binary',18=>'rating',19=>'text',20=>'binary'];
foreach ($rows as &$row) { $qn=(int)($row['qn']??0); if (isset($types[$qn]) && !isset($row['report_type'])) $row['report_type']=$types[$qn]; }
unset($row);
file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL);
