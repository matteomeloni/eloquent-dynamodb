<?php

namespace MatteoMeloni\DynamoDb\Eloquent;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use MatteoMeloni\DynamoDb\Exception\ModelNotFoundException;
use ReflectionClass;

class Model
{
    use Concerns\HasAttributes,
        Concerns\HasDynamoDbConnection,
        Concerns\HasTimestamps;

    /**
     * The connection name for the model.
     *
     * @var DynamoDbClient
     */
    protected $connection;

    /**
     * Instance of Aws\DynamoDb\Marshaler
     *
     * @var Marshaler
     */
    protected $marshaler;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    private $scanFilter = [];

    private $traits = [];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    const COMPARISON_OPERATOR = [
        '=' => 'EQ',
        '!=' => 'NE',
        '<>' => 'NE',
        '<=' => 'LE',
        '<' => 'LT',
        '>=' => 'GE',
        '>' => 'GT',
        'like' => 'CONTAINS',
        'not like' => 'NOT_CONTAINS',
    ];

    /**
     * Create a new DynamoDb model instance.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->marshaler = new Marshaler();

        $this->traits = (new ReflectionClass($this))->getTraitNames();

        $this->table = $this->getTable();

        $this->setConnection();
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string
     * @param string|null $operator
     * @param mixed $value
     * @return $this
     */
    public function where($column, $operator = NULL, $value = NULL)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->scanFilter[$column] = [
            'AttributeValueList' => [$this->marshaler->marshalValue($value)],
            'ComparisonOperator' => self::COMPARISON_OPERATOR[$operator]
        ];

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param string $column
     * @param bool $not
     * @return $this
     */
    public function whereNull(string $column, $not = false)
    {
        $type = $not ? 'NOT_NULL' : 'NULL';

        $this->scanFilter[$column] = ['ComparisonOperator' => $type];

        return $this;
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function whereNotNull(string $column)
    {
        return $this->whereNull($column, true);
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function whereIn(string $column, array $values)
    {
        $this->scanFilter[$column] = [
            'AttributeValueList' => $this->marshaler->marshalItem($values),
            'ComparisonOperator' => 'IN'
        ];

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereBetween(string $column, array $values)
    {
        $this->scanFilter[$column] = [
            'AttributeValueList' => $this->marshaler->marshalItem($values),
            'ComparisonOperator' => 'BETWEEN'
        ];

        return $this;
    }

    /**
     * Add a where BEGINS_WITH statement to the query.
     *
     * @param string $column
     * @param  $value
     * @return $this
     */
    public function whereBeginsWith(string $column, $value)
    {
        $this->scanFilter[$column] = [
            'AttributeValueList' => [$this->marshaler->marshalValue($value)],
            'ComparisonOperator' => 'BEGINS_WITH'
        ];

        return $this;
    }

    /**
     * Get all of the models from the database.
     *
     * @param array|mixed $columns
     * @return \Illuminate\Support\Collection
     */
    public static function all($columns = ['*'])
    {
        return (new static)->get(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    /**
     * Get the first item from the collection.
     *
     * @param array $columns
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        return $this->get($columns)->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array $columns
     * @return Model|static
     *
     * @throws ModelNotFoundException
     */
    public function firstOrFail($columns = ['*'])
    {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this));
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public function firstOrCreate(array $attributes, array $values)
    {
        foreach ($attributes as $field => $value) {
            $this->where($field, '=', $value);
        }

        if (is_null($instance = $this->first())) {
            $this->attributes = $attributes + $values;
            $this->save();

            $instance = $this;
        }

        return $instance;
    }

    /**
     * Get the last item from the collection.
     *
     * @param array $columns
     * @return mixed
     */
    public function last($columns = ['*'])
    {
        return $this->get($columns)->last();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $options = [
            'TableName' => $this->table,
            'ScanFilter' => $this->scanFilter
        ];

        $options = $this->setAttributesToGet($columns, $options);

        $result = $this->connection->scan($options)->toArray();

        return $this->makeCollection($result['Items']);
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param $id
     * @param array $columns
     * @return mixed|static
     */
    public function find($id, $columns = ['*'])
    {
        $options = [
            'TableName' => $this->table,
            'Key' => $this->marshaler->marshalItem([
                $this->primaryKey => $id
            ])
        ];

        $options = $this->setAttributesToGet($columns, $options);

        $result = $this->connection->getItem($options)->toArray();

        if ($result['@metadata']['statusCode'] == 200 and isset($result['Item'])) {
            return $this->makeModel($result['Item']);
        } else {
            return NULL;
        }
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param $id
     * @param array $columns
     * @return Model|static
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);

        if (!is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException())->setModel(get_class($this), $id);
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save()
    {
        foreach ($this->attributes as $key => $value) {
            if ($this->hasSetMutator($key)) {
                $this->attributes[$key] = $this->setAttribute($key, $value);
            }
        }
        if (!$this->exists) {
            $result = $this->performInsert();
        } else {
            $result = $this->performUpdate();
        }

        return $result->toArray()['@metadata']['statusCode'] === 200;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        if ($this->exists) {
            return $this->performDelete();
        } else {
            $items = $this->get();
            foreach ($items as $item) {
                $item->delete();
            }

            return true;
        }
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (!isset($this->table)) {
            return str_replace(
                '\\', '', Str::snake(Str::plural(class_basename($this)))
            );
        }

        return $this->table;
    }

    /**
     * Set the table associated with the model.
     *
     * @param string $table
     * @return $this
     */
    protected function setTable(string $table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set attributes to get
     *
     * @param $columns
     * @param $options
     * @return mixed
     */
    protected function setAttributesToGet($columns, $options)
    {
        if ($columns[0] != '*') {
            $options['AttributesToGet'] = $columns;
        }
        return $options;
    }

    /**
     * Make a Collection
     *
     * @param $response
     * @return \Illuminate\Support\Collection
     */
    private function makeCollection($items)
    {
        $rows = [];

        foreach ($items as $i => $row) {
            $rows[$i] = $this->makeModel($row);
        }

        return collect($rows);
    }

    /**
     * Make Model
     *
     * @param $row
     * @return mixed
     */
    private function makeModel($row)
    {
        $model = eval('return new ' . get_called_class() . '();');
        $model->connection = $this->connection;
        $row = $this->marshaler->unmarshalItem($row);

        foreach ($row as $attribute => $value) {
            if ($this->hasGetMutator($attribute)) {
                $value = $this->mutateAttribute($attribute, $value);
            }
            $model->attributes[$attribute] = $value;
            $model->original[$attribute] = $value;
            $model->exists = TRUE;
        }

        return $model;
    }


    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string|null $operator
     * @param bool $useDefault
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif (!isset(self::COMPARISON_OPERATOR[$operator])) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }
}
