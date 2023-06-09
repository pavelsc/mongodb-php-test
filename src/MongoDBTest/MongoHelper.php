<?php

namespace MongoDBTest;


use Exception;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\DeleteResult;
use MongoDB\Driver\Cursor;
use MongoDB\InsertOneResult;
use MongoDB\Operation\FindOneAndUpdate;
use MongoDB\UpdateResult;

class MongoHelper
{
  public string $username;
  public string $password;
  public string|null $replica_set;
  public string $database;
  public array|string $host;
  public Database $contactsDb;
  private Collection $contactsCollection;

  protected string $contactsCollectionName = 'associatedContacts';
  protected string $countersCollectionName = 'counters';
  protected string $linksCollectionName = 'contactsLinks';

  /**
   * @param array $data
   */
  public function __construct(array $data)
  {
    $this->host        = $data['host'];
    $this->username    = $data['username'];
    $this->password    = $data['password'];
    $this->replica_set = $data['replica_set'] ?? null;
    $this->database    = $data['database'];
    $this->init();
  }

  /**
   * @return void
   */
  public function init(): void
  {
    $host = $this->host;
    $host = is_array($host) ? implode(',', $host) : $host;

    $username   = $this->username;
    $password   = $this->password;
    $replicaSet = $this->replica_set;

    if ($replicaSet) {
      $replicaSet = "replicaSet=" . $replicaSet;
    }

    $mongoDbAuth = '';
    if (!empty($username) && !empty($password)) {
      $mongoDbAuth = "{$username}:{$password}@";
    }

    try {
      $connStr = "mongodb://{$mongoDbAuth}{$host}/?{$replicaSet}";
      echo $connStr . "\r\n";
      $client                   = new Client($connStr);
      $this->contactsDb         = $client->{$this->database};
      $this->contactsCollection = $this->contactsDb->{$this->contactsCollectionName};
    } catch (Exception $e) {
      echo $e->getMessage();
      exit();
    }
  }


  /**
   * Insert Contact into DB
   * @param $data
   * @return InsertOneResult
   * @throws Exception
   */
  public function insert($data): InsertOneResult
  {
    if (!empty($data)) {
      return $this->contactsCollection->insertOne($data);
    } else {
      throw new Exception('Empty data', 400);
    }
  }

  /**
   * Find many contacts
   *
   * @param array $query
   * @param array $fields
   * @return Cursor
   */
  public function find(array $query = [], array $fields = []): Cursor
  {
    return $this->contactsCollection->find($query, $fields);
  }

  /**
   * @param array $query
   * @param array $fields
   * @return object|array|null
   */
  public function findOne(array $query = [], array $fields = []): object|array|null
  {
    return $this->contactsCollection->findOne($query, $fields);
  }

  /**
   * Get incremental id
   * @param $id
   * @return int
   */
  public function getNextSequenceValue($id): int
  {
    $sequenceCollection = null;
    foreach ($this->contactsDb->listCollections() as $collectionInfo) {
      if ($collectionInfo->getName() == $this->countersCollectionName) {
        $sequenceCollection = $this->contactsDb->{$this->countersCollectionName};
        break;
      }
    }

    if ($sequenceCollection === null) {
      $this->contactsDb->{$this->countersCollectionName}->insertOne(
        [
          '_id'      => $id,
          'sequence' => 0
        ]
      );
      $sequenceCollection = $this->contactsDb->{$this->countersCollectionName};
    }

    $sequenceDoc = $sequenceCollection->findOneAndUpdate(
      ['_id' => $id],
      ['$inc' => ['sequence' => 1]],
      ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
    return $sequenceDoc->bsonSerialize()->sequence;
  }


  /**
   * @param array $condition
   * @param array $action
   * @param array $options
   * @return UpdateResult
   * @throws Exception
   */
  public function updateContacts(array $condition = [], array $action = [], array $options = []): UpdateResult
  {
    if (empty($condition) || empty($action)) {
      throw new Exception('Empty data', 400);
    }

    return $this->contactsCollection->updateMany($condition, $action, $options);
  }


  /**
   * @param int $internalId1
   * @param int $internalId2
   * @param int $productId
   * @return UpdateResult
   * @throws Exception
   */
  public function addLink(int $internalId1, int $internalId2, int $productId): UpdateResult
  {
    $linksCollection = $this->contactsDb->{$this->linksCollectionName};
    if (!empty($internalId1) and !empty($internalId2)) {
      return $linksCollection->updateOne(
        [
          'id1' => $internalId1,
          'id2' => $internalId2
        ],
        [
          '$set' => [
            'id1'           => $internalId1,
            'id2'           => $internalId2,
            'product_id'    => $productId,
            'db_link_exist' => true,
          ],
        ],
        [
          'upsert' => true,
        ]
      );
    } else {
      throw new Exception('Empty data', 400);
    }
  }


  /**
   * @param $link
   * @param $updateArr
   * @return UpdateResult
   * @throws Exception
   */
  public function updateLink($link, $updateArr): UpdateResult
  {
    $linksCollection = $this->contactsDb->{$this->linksCollectionName};
    if (!empty($link)) {
      return $linksCollection->updateOne(
        [
          'product_id' => $link['product_id'],
          'id1'        => $link['id1'],
          'id2'        => $link['id2']
        ],
        [
          '$set' => $updateArr,
        ]
      );
    } else {
      throw new Exception('Empty data', 400);
    }
  }

  /**
   * @param int $internalId1
   * @param int $internalId2
   * @return bool|array
   * @throws Exception
   */
  public function getRealLink(int $internalId1, int $internalId2): bool|array
  {
    $linksCollection = $this->contactsDb->{$this->linksCollectionName};
    if (!empty($internalId1) and !empty($internalId2)) {
      $data = $linksCollection->findOne(
        [
          '$or' => [
            [
              'id1' => $internalId1,
              'id2' => $internalId2,
            ],
            [
              'id2' => $internalId1,
              'id1' => $internalId2,
            ],
          ]
        ]
      );

      if ($data) {
        return (array)$data->bsonSerialize();
      } else {
        return false;
      }
    } else {
      throw new Exception('Empty data', 400);
    }
  }


  /**
   * @param int $id
   * @param int $productId
   * @return array|null
   * @throws Exception
   */
  public function getLinkData(int $id, int $productId): ?array
  {
    if (empty($id) || empty($productId)) {
      throw new Exception('Empty data', 400);
    }

    $linksCollection = $this->contactsDb->{$this->linksCollectionName};

    $data = $linksCollection->findOne(
      [
        '$or'        => [
          ['id1' => $id],
          ['id2' => $id],
        ],
        'product_id' => $productId
      ]
    );

    return $data ? (array)$data->bsonSerialize() : null;
  }

  /**
   * @param int $internalID
   * @param int $productId
   * @return array
   * @throws Exception
   */
  public function getContactByInternalId(int $internalID, int $productId): array
  {
    $contactResult = $this->contactsCollection->findOne([
      'internal_id' => $internalID,
      'product_id'  => $productId
    ]);

    if (empty($contactResult)) {
      throw new Exception('Contact not found by internal ID', 4001);
    }

    return (array)$contactResult->bsonSerialize();
  }

  /**
   * @param int $internalId
   * @param array $internalIds
   *
   * @return DeleteResult
   * @throws Exception
   */
  public function deleteManyLinks(int $internalId, array $internalIds): DeleteResult
  {
    if (empty($internalId) || empty($internalIds)) {
      throw new Exception('Empty data', 400);
    }

    $linksCollection = $this->contactsDb->{$this->linksCollectionName};

    return $linksCollection->deleteMany([
      '$or' => [
        [
          'id1' => $internalId,
          'id2' => [
            '$in' => $internalIds,
          ],
        ],
        [
          'id1' => [
            '$in' => $internalIds,
          ],
          'id2' => $internalId,
        ],
      ]
    ]);
  }

  /**
   * @param int $internalId1
   * @param int $internalId2
   * @return DeleteResult
   * @throws Exception
   */
  public function deleteLink(int $internalId1, int $internalId2): DeleteResult
  {
    if (empty($internalId1) || empty($internalId2)) {
      throw new Exception('Empty data', 400);
    }

    $linksCollection = $this->contactsDb->{$this->linksCollectionName};

    return $linksCollection->deleteOne([
      '$or' => [
        [
          'id1' => $internalId1,
          'id2' => $internalId2,
        ],
        [
          'id2' => $internalId1,
          'id1' => $internalId2,
        ],
      ]
    ]);
  }


  /**
   * @param $data
   * @return DeleteResult
   * @throws Exception
   */
  public function deleteContactNode($data): DeleteResult
  {
    if (empty($data)) {
      throw new Exception('Empty data', 400);
    }

    return $this->contactsCollection->deleteOne($data);
  }


  /**
   * @param $data
   * @return DeleteResult
   * @throws Exception
   */
  public function deleteContactNodes($data): DeleteResult
  {
    if (empty($data)) {
      throw new Exception('Empty data', 400);
    }

    return $this->contactsCollection->deleteMany($data);
  }


  /**
   * @param $query
   * @param $options
   * @return Cursor
   * @throws Exception
   */
  public function findLinks($query, $options): Cursor
  {
    if (empty($query) || empty($options)) {
      throw new Exception('Empty data', 400);
    }

    $linksCollection = $this->contactsDb->{$this->linksCollectionName};

    return $linksCollection->find($query, $options);
  }

  public function __call($name, $arguments)
  {
    return call_user_func_array([$this->contactsCollection, $name], $arguments);
  }

  /**
   * @param $data
   * @return void
   */
  public function cleanAll($data): void
  {
    $linksCollection = $this->contactsDb->{$this->linksCollectionName};
    $linksCollection->deleteMany($data);
    $this->contactsCollection->deleteMany($data);
  }
}