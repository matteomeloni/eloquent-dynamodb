<?php

namespace MatteoMeloni\DynamoDb\Exception;

use Exception;
use MatteoMeloni\DynamoDb\Eloquent\Model;

class QueryBuilderException extends Exception
{
    public function invalidOperator($operator)
    {
        $this->code = 0;

        $this->message = 'Value ' . $operator .' is invalid comparison operator:
        Member must satisfy value set: [' . implode(', ', array_keys(Model::COMPARISON_OPERATOR)) . ' ]' ;

        return $this;
    }
}
