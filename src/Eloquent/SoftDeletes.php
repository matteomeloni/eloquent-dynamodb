<?php

namespace MatteoMeloni\DynamoDb\Eloquent;

use Carbon\Carbon;

trait SoftDeletes
{
    private $withTrashed = FALSE;
    private $onlyTrashed = FALSE;

    /**
     * Execute the query as a "select" statement without deleted at rows.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $this->withoutTrashed();

        return parent::get($columns);
    }

    /**
     * Add the with-trashed extension to Model.
     *
     * @return $this
     */
    public function withTrashed()
    {
        $this->withTrashed = TRUE;

        return $this;
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @return void
     */
    private function withoutTrashed()
    {
        if($this->withTrashed === FALSE and $this->onlyTrashed === FALSE) {
            $this->whereNull($this->getDeletedAtColumn());
        }
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        $this->onlyTrashed = TRUE;

        $this->whereNotNull($this->getDeletedAtColumn());

        return $this;
    }

    /**
     * Perform a soft-delete model operation.
     *
     * @return bool
     */
    public function performDelete()
    {
        $result = $this->connection->updateItem([
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $this->attributes[$this->primaryKey]
            ]),
            'AttributeUpdates' => [
                $this->getDeletedAtColumn() => [
                    'Value' => $this->marshaler->marshalValue(Carbon::now()->toDateTimeString())
                ]
            ]
        ]);

        return $result->toArray()['@metadata']['statusCode'] == 200;
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool|null
     */
    public function restore()
    {
        if($this->exists) {
            return $this->performRestore();
        } else {
            $items = $this->get();
            foreach ($items as $item) {
                $item->restore();
            }

            return true;
        }
    }

    /**
     * Perform a restore model operation.
     *
     * @return bool
     */
    public function performRestore()
    {
        $result = $this->connection->updateItem([
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $this->attributes[$this->primaryKey]
            ]),
            'AttributeUpdates' => [
                $this->getDeletedAtColumn() => [
                    'Action' => 'DELETE',
                ]
            ]
        ]);

        return $result->toArray()['@metadata']['statusCode'] == 200;
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function forceDelete()
    {
        if($this->exists) {
            return $this->performForceDelete();
        } else {
            $items = $this->get();
            foreach ($items as $item) {
                $item->performForceDelete();
            }

            return true;
        }
    }

    /**
     * Perform a hard delete on a soft deleted model.
     *
     * @return bool
     */
    public function performForceDelete()
    {
        $result = $this->connection->deleteItem([
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $this->attributes[$this->primaryKey]
            ])
        ]);

        return $result->toArray()['@metadata']['statusCode'] == 200;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }
}
