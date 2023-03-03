<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

use MongoDBTest\MongoHelper;

const MONGO_FIND_OPTION = [
  'projection' => [
    '_id' => 0
  ],
];

try {
  require(__DIR__ . '/vendor/autoload.php');
  $config = require(__DIR__ . '/config/mongo.php');

  $mongoHelper = new MongoHelper($config);

  echo "Start...\r\n";
  $start = hrtime(true);

  $data         = [
    'contact_id'   => 'LudKlcOeOjL1ESvkqcnx',
    'contact_type' => 'mc_chat',
    'product_id'   => 300023344,
  ];
  $checkContact = $mongoHelper->findOne($data, MONGO_FIND_OPTION);
  print_r($checkContact);
  $time = (hrtime(true) - $start) / 1e9;
  echo "Total time: {$time}s\r\n";
} catch (Exception $e) {
  print_r($e);
}