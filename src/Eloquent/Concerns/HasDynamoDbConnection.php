<?php

namespace MatteoMeloni\DynamoDb\Eloquent\Concerns;

use Aws\DynamoDb\DynamoDbClient;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait HasDynamoDbConnection
{
    /**
     * Set the connection associated with the model.
     */
    protected function setConnection()
    {
        $this->connection = new DynamoDbClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY')
            ]
        ]);
    }

    /**
     * Perform a model insert operation.
     *
     * @return \Aws\Result
     */
    protected function performInsert()
    {
        $this->attributes[$this->primaryKey] = Str::uuid()->toString();
        $this->attributes[self::CREATED_AT] = Carbon::now()->toDateTimeString();

        $result = $this->connection->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem($this->attributes)
        ]);

        $this->original = $this->attributes;

        $this->exists = true;

        return $result;
    }

    /**
     * Perform a model update operation.
     *
     * @return \Aws\Result
     */
    protected function performUpdate()
    {
        $attributes = [];
        $this->attributes[self::UPDATED_AT] = Carbon::now()->toDateTimeString();

        foreach ($this->attributes as $field => $value) {
            if ($field == $this->primaryKey) {
                continue;
            }
            $attributes[$field] = [
                'Value' => $this->marshaler->marshalValue($value)
            ];
        }

        $result = $this->connection->updateItem([
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $this->attributes[$this->primaryKey]
            ]),
            'AttributeUpdates' => $attributes
        ]);

        $this->original = $this->attributes;

        $this->exists = true;

        return $result;
    }

    /**
     * Perform a model delete operation.
     *
     * @return bool
     */
    protected function performDelete()
    {
        $result = $this->connection->deleteItem([
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $this->attributes[$this->primaryKey]
            ])
        ]);

        return $result->toArray()['@metadata']['statusCode'] === 200;
    }
}
