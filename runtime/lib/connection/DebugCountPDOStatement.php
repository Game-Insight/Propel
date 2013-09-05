<?php

class DebugCountPDOStatement extends PDOStatement
{
    /**
     * The PDO connection from which this instance was created.
     *
     * @var PropelPDO
     */
    protected $pdo;

    protected function __construct(PropelPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Executes a prepared statement.  Returns a boolean value indicating success.
     * Overridden for query counting.
     *
     * @param  string  $input_parameters
     * @return boolean
     */
    public function execute($input_parameters = null)
    {
        $return	= parent::execute($input_parameters);
        $this->pdo->incrementQueryCount();

        return $return;
    }
}
