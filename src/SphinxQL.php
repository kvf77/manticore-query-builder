<?php

declare(strict_types=1);

namespace Kvf77\Manticore;

use Kvf77\Manticore\Concerns\HasLimitOffset;
use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Exception\ConnectionException;
use Kvf77\Manticore\Exception\DatabaseException;
use Kvf77\Manticore\Exception\SphinxQLException;

/**
 * Query Builder class for SphinxQL statements.
 *
 * @phpstan-consistent-constructor
 */
class SphinxQL
{
    use HasLimitOffset;

    /**
     * A non-static connection for the current SphinxQL object
     *
     * @var ConnectionInterface|null
     */
    protected ?ConnectionInterface $connection;

    /**
     * The last result object.
     *
     * @var ResultSetInterface|MultiResultSetInterface|null
     */
    protected ResultSetInterface|MultiResultSetInterface|null $last_result = null;

    /**
     * The last compiled query.
     *
     * @var string
     */
    protected string $last_compiled = '';

    /**
     * The last chosen method (select, insert, replace, update, delete).
     *
     * @var string|null
     */
    protected ?string $type = null;

    /**
     * An SQL query that is not yet executed or "compiled"
     *
     * @var string|null
     */
    protected ?string $query = null;

    /**
     * Array of select elements that will be comma separated.
     *
     * @var array
     */
    protected array $select = array();

    /**
     * From in SphinxQL is the list of indexes that will be used
     *
     * @var array|\Closure|SphinxQL
     */
    protected array|\Closure|SphinxQL $from = [];

    /**
     * The list of where and parenthesis, must be inserted in order
     *
     * @var array
     */
    protected array $where = array();

    /**
     * The list of matches for the MATCH function in SphinxQL
     *
     * @var array
     */
    protected array $match = array();

    /**
     * GROUP BY array to be comma separated
     *
     * @var array
     */
    protected array $group_by = array();

    /**
     * When not null changes 'GROUP BY' to 'GROUP N BY'
     *
     * @var null|int
     */
    protected ?int $group_n_by = null;

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected array $within_group_order_by = array();

    /**
     * The list of where and parenthesis, must be inserted in order
     *
     * @var array
     */
    protected array $having = array();

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected array $order_by = array();

    /**
     * Value of INTO query for INSERT or REPLACE
     *
     * @var null|string
     */
    protected ?string $into = null;

    /**
     * Array of columns for INSERT or REPLACE
     *
     * @var array
     */
    protected array $columns = array();

    /**
     * Array OF ARRAYS of values for INSERT or REPLACE
     *
     * @var array
     */
    protected array $values = array();

    /**
     * Array arrays containing column and value for SET in UPDATE
     *
     * @var array
     */
    protected array $set = array();

    /**
     * Array of OPTION specific to SphinxQL
     *
     * @var array
     */
    protected array $options = array();

    /**
     * Array of FACETs
     *
     * @var Facet[]
     */
    protected array $facets = array();

    /**
     * The reference to the object that queued itself and created this object
     *
     * @var null|SphinxQL
     */
    protected ?SphinxQL $queue_prev = null;

    /**
     * An array of escaped characters for escapeMatch()
     * @var array
     */
    protected array $escape_full_chars = array(
        '\\' => '\\\\',
        '(' => '\(',
        ')' => '\)',
        '|' => '\|',
        '-' => '\-',
        '!' => '\!',
        '@' => '\@',
        '~' => '\~',
        '"' => '\"',
        '&' => '\&',
        '/' => '\/',
        '^' => '\^',
        '$' => '\$',
        '=' => '\=',
        '<' => '\<',
    );

    /**
     * An array of escaped characters for fullEscapeMatch()
     * @var array
     */
    protected array $escape_half_chars = array(
        '\\' => '\\\\',
        '(' => '\(',
        ')' => '\)',
        '!' => '\!',
        '@' => '\@',
        '~' => '\~',
        '&' => '\&',
        '/' => '\/',
        '^' => '\^',
        '$' => '\$',
        '=' => '\=',
        '<' => '\<',
    );

    /**
     * @param  ConnectionInterface|null  $connection
     */
    public function __construct(?ConnectionInterface $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Sets Query Type
     *
     */
    public function setType(string $type): string
    {
        return $this->type = $type;
    }

    /**
     * Returns the currently attached connection
     *
     * @returns ConnectionInterface|null
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Avoids having the expressions escaped
     *
     * Examples:
     *    $query->where('time', '>', SphinxQL::expr('CURRENT_TIMESTAMP'));
     *    // WHERE time > CURRENT_TIMESTAMP
     *
     * @param  string  $string  The string to keep unaltered
     *
     * @return Expression The new Expression
     * @todo make non static
     */
    public static function expr(string $string = ''): Expression
    {
        return new Expression($string);
    }

    /**
     * Runs the query built
     *
     * @return ResultSetInterface The result of the query
     * @throws DatabaseException
     * @throws ConnectionException
     * @throws SphinxQLException
     */
    public function execute(): ResultSetInterface
    {
        // pass the object so execute compiles it by itself
        $result = $this->getConnection()->query($this->compile()->getCompiled());
        $this->last_result = $result;
        return $result;
    }

    /**
     * Executes a batch of queued queries
     *
     * @return MultiResultSetInterface The array of results
     * @throws SphinxQLException In case no query is in queue
     * @throws Exception\DatabaseException
     * @throws ConnectionException
     */
    public function executeBatch(): MultiResultSetInterface
    {
        if (count($this->getQueue()) == 0) {
            throw new SphinxQLException('There is no Queue present to execute.');
        }

        $queue = array();

        foreach ($this->getQueue() as $query) {
            $queue[] = $query->compile()->getCompiled();
        }

        $result = $this->getConnection()->multiQuery($queue);
        $this->last_result = $result;
        return $result;
    }

    /**
     * Enqueues the current object and returns a new one or the supplied one
     *
     * @param  SphinxQL|null  $next
     *
     * @return SphinxQL A new SphinxQL object with the current object referenced
     */
    public function enqueue(?SphinxQL $next = null): SphinxQL
    {
        if ($next === null) {
            $next = new static($this->getConnection());
        }

        $next->setQueuePrev($this);

        return $next;
    }

    /**
     * Returns the ordered array of enqueued objects
     *
     * @return SphinxQL[] The ordered array of enqueued objects
     */
    public function getQueue(): array
    {
        $queue = array();
        $curr = $this;

        do {
            if ($curr->type != null) {
                $queue[] = $curr;
            }
        } while ($curr = $curr->getQueuePrev());

        return array_reverse($queue);
    }

    /**
     * Gets the enqueued object
     *
     * @return SphinxQL|null
     */
    public function getQueuePrev(): ?SphinxQL
    {
        return $this->queue_prev;
    }

    /**
     * Sets the reference to the enqueued object
     *
     * @param  SphinxQL  $query  The object to set as previous
     *
     * @return static
     */
    public function setQueuePrev(SphinxQL $query): static
    {
        $this->queue_prev = $query;

        return $this;
    }

    /**
     * Returns the result of the last query
     *
     * @return ResultSetInterface|MultiResultSetInterface|null The result of the last query
     */
    public function getResult(): ResultSetInterface|MultiResultSetInterface|null
    {
        return $this->last_result;
    }

    /**
     * Returns the latest compiled query
     *
     * @return string The last compiled query
     */
    public function getCompiled(): string
    {
        return $this->last_compiled;
    }

    /**
     * Begins transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionBegin(): void
    {
        $this->getConnection()->query('BEGIN');
    }

    /**
     * Commits transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionCommit(): void
    {
        $this->getConnection()->query('COMMIT');
    }

    /**
     * Rollbacks transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionRollback(): void
    {
        $this->getConnection()->query('ROLLBACK');
    }

    /**
     * Runs the compile function
     *
     * @return static
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function compile(): static
    {
        switch ($this->type) {
            case 'select':
                $this->compileSelect();
                break;
            case 'insert':
            case 'replace':
                $this->compileInsert();
                break;
            case 'update':
                $this->compileUpdate();
                break;
            case 'delete':
                $this->compileDelete();
                break;
            case 'query':
                $this->compileQuery();
                break;
        }

        return $this;
    }

    /**
     * @return static
     */
    public function compileQuery(): static
    {
        $this->last_compiled = $this->query ?? '';

        return $this;
    }

    /**
     * Compiles the MATCH part of the queries
     * Used by: SELECT, DELETE, UPDATE
     *
     * @return string The compiled MATCH
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function compileMatch(): string
    {
        $query = '';

        if (!empty($this->match)) {
            $query .= 'WHERE MATCH(';

            $matched = array();

            foreach ($this->match as $match) {
                $pre = '';
                if ($match['column'] instanceof \Closure) {
                    $sub = new MatchBuilder($this);
                    call_user_func($match['column'], $sub);
                    $pre .= $sub->compile()->getCompiled();
                } elseif ($match['column'] instanceof MatchBuilder) {
                    $pre .= $match['column']->compile()->getCompiled();
                } elseif (empty($match['column'])) {
                    $pre .= '';
                } elseif (is_array($match['column'])) {
                    $pre .= '@('.implode(',', $match['column']).') ';
                } else {
                    $pre .= '@'.$match['column'].' ';
                }

                if ($match['half']) {
                    $pre .= $this->halfEscapeMatch($match['value']);
                } else {
                    $pre .= $this->escapeMatch($match['value']);
                }

                if ($pre !== '') {
                    $matched[] = '('.$pre.')';
                }
            }

            $matched = implode(' ', $matched);
            $query .= $this->getConnection()->escape(trim($matched)).') ';
        }

        return $query;
    }

    /**
     * Compiles the WHERE part of the queries
     * It interacts with the MATCH() and of course isn't usable stand-alone
     * Used by: SELECT, DELETE, UPDATE
     *
     * @return string The compiled WHERE
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileWhere(): string
    {
        $query = '';

        if (empty($this->where)) {
            return $query;
        }

        // MATCH() already emits the leading "WHERE ...", so the where conditions
        // that follow must be glued on with their boolean operator.
        if (empty($this->match)) {
            $query .= 'WHERE ';
        }

        $query .= $this->compileWhereConditions($this->where, !empty($this->match));

        return $query;
    }

    /**
     * Recursively compiles a list of where conditions, nested groups and regex filters.
     * The very first condition drops its leading boolean unless a MATCH() precedes it.
     *
     * @param  array  $conditions  The where list (each item carries its own 'boolean')
     * @param  bool  $forcePrefix  Whether the first condition must still be prefixed (e.g. after MATCH)
     *
     * @return string
     * @throws ConnectionException
     * @throws DatabaseException
     */
    protected function compileWhereConditions(array $conditions, bool $forcePrefix = false): string
    {
        $query = '';

        foreach (array_values($conditions) as $key => $condition) {
            if ($key > 0 || $forcePrefix) {
                $query .= ($condition['boolean'] ?? 'AND').' ';
            }

            $type = $condition['type'] ?? 'basic';

            if ($type === 'group') {
                $query .= '('.trim($this->compileWhereConditions($condition['group'])).') ';
            } elseif ($type === 'regex') {
                $query .= 'REGEX('.$condition['column'].", '".$condition['value']."') ";
            } else {
                $query .= $this->compileFilterCondition($condition);
            }
        }

        return $query;
    }

    /**
     * @param  array  $filter
     *
     * @return string
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileFilterCondition(array $filter): string
    {
        $query = '';

        if (!empty($filter)) {
            if (strtoupper($filter['operator']) === 'BETWEEN') {
                $query .= $filter['column'];
                $query .= ' BETWEEN ';
                $query .= $this->getConnection()->quote($filter['value'][0]).' AND '
                    .$this->getConnection()->quote($filter['value'][1]).' ';
            } else {
                // id can't be quoted!
                if ($filter['column'] === 'id') {
                    $query .= 'id ';
                } else {
                    $query .= $filter['column'].' ';
                }

                if (in_array(strtoupper($filter['operator']), array('IN', 'NOT IN'), true)) {
                    $query .= strtoupper($filter['operator']).' ('.implode(', ',
                            $this->getConnection()->quoteArr($filter['value'])).') ';
                } else {
                    $query .= $filter['operator'].' '.$this->getConnection()->quote($filter['value']).' ';
                }
            }
        }

        return $query;
    }

    /**
     * Compiles the statements for SELECT
     *
     * @return static
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function compileSelect(): static
    {
        $query = '';

        if ($this->type == 'select') {
            $query .= 'SELECT ';

            if (!empty($this->select)) {
                $query .= implode(', ', $this->select).' ';
            } else {
                $query .= '* ';
            }
        }

        if (!empty($this->from)) {
            if ($this->from instanceof \Closure) {
                $sub = new static($this->getConnection());
                call_user_func($this->from, $sub);
                $query .= 'FROM ('.$sub->compile()->getCompiled().') ';
            } elseif ($this->from instanceof SphinxQL) {
                $query .= 'FROM ('.$this->from->compile()->getCompiled().') ';
            } else {
                $query .= 'FROM '.implode(', ', $this->from).' ';
            }
        }

        $query .= $this->compileMatch().$this->compileWhere();

        if (!empty($this->group_by)) {
            $query .= 'GROUP ';
            if ($this->group_n_by !== null) {
                $query .= $this->group_n_by.' ';
            }
            $query .= 'BY '.implode(', ', $this->group_by).' ';
        }

        if (!empty($this->within_group_order_by)) {
            $query .= 'WITHIN GROUP ORDER BY ';

            $order_arr = array();

            foreach ($this->within_group_order_by as $order) {
                $order_sub = $order['column'];

                if ($order['direction'] !== null) {
                    $order_sub .= ' '.((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
                }

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        if (!empty($this->having)) {
            $query .= 'HAVING '.$this->compileFilterCondition($this->having);
        }

        if (!empty($this->order_by)) {
            $query .= 'ORDER BY ';

            $order_arr = array();

            foreach ($this->order_by as $order) {
                $order_sub = $order['column'];

                if ($order['direction'] !== null) {
                    $order_sub .= ' '.((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
                }

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        $query .= $this->compileLimitOffset();

        if (!empty($this->options)) {
            $options = array();

            foreach ($this->options as $option) {
                if ($option['value'] instanceof Expression) {
                    $option['value'] = $option['value']->value();
                } elseif (is_array($option['value'])) {
                    array_walk(
                        $option['value'],
                        function (&$val, $key) {
                            $val = $key.'='.$val;
                        }
                    );
                    $option['value'] = '('.implode(', ', $option['value']).')';
                } else {
                    $option['value'] = $this->getConnection()->quote($option['value']);
                }

                $options[] = $option['name'].' = '.$option['value'];
            }

            $query .= 'OPTION '.implode(', ', $options).' ';
        }

        if (!empty($this->facets)) {
            $facets = array();

            foreach ($this->facets as $facet) {
                // dynamically set the own SphinxQL connection if the Facet doesn't own one
                if ($facet->getConnection() === null) {
                    $facet->setConnection($this->getConnection());
                    $facets[] = $facet->getFacet();
                    // go back to the status quo for reuse
                    $facet->setConnection();
                } else {
                    $facets[] = $facet->getFacet();
                }
            }

            $query .= implode(' ', $facets);
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for INSERT or REPLACE
     *
     * @return static
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileInsert(): static
    {
        if ($this->type == 'insert') {
            $query = 'INSERT ';
        } else {
            $query = 'REPLACE ';
        }

        if ($this->into !== null) {
            $query .= 'INTO '.$this->into.' ';
        }

        if (!empty($this->columns)) {
            $query .= '('.implode(', ', $this->columns).') ';
        }

        if (!empty($this->values)) {
            $query .= 'VALUES ';
            $query_sub = array();

            foreach ($this->values as $value) {
                $query_sub[] = '('.implode(', ', $this->getConnection()->quoteArr($value)).')';
            }

            $query .= implode(', ', $query_sub);
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for UPDATE
     *
     * @return static
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileUpdate(): static
    {
        $query = 'UPDATE ';

        if ($this->into !== null) {
            $query .= $this->into.' ';
        }

        if (!empty($this->set)) {
            $query .= 'SET ';

            $query_sub = array();

            foreach ($this->set as $column => $value) {
                // MVA support
                if (is_array($value)) {
                    $query_sub[] = $column
                        .' = ('.implode(', ', $this->getConnection()->quoteArr($value)).')';
                } else {
                    $query_sub[] = $column
                        .' = '.$this->getConnection()->quote($value);
                }
            }

            $query .= implode(', ', $query_sub).' ';
        }

        $query .= $this->compileMatch().$this->compileWhere();

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for DELETE
     *
     * @return static
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileDelete(): static
    {
        $query = 'DELETE ';

        if (!empty($this->from)) {
            $query .= 'FROM '.$this->from[0].' ';
        }

        if (!empty($this->match)) {
            $query .= $this->compileMatch();
        }
        if (!empty($this->where)) {
            $query .= $this->compileWhere();
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Sets a query to be executed
     *
     * @param  string  $sql  A SphinxQL query to execute
     *
     * @return static
     */
    public function query(string $sql): static
    {
        $this->type = 'query';
        $this->query = $sql;

        return $this;
    }

    /**
     * Select the columns
     *
     * Gets the arguments passed as $sphinxql->select('one', 'two')
     * Using it without arguments equals to having '*' as argument
     * Using it with array maps values as column names
     *
     * Examples:
     *    $query->select('title');
     *    // SELECT title
     *
     *    $query->select('title', 'author', 'date');
     *    // SELECT title, author, date
     *
     *    $query->select(['id', 'title']);
     *    // SELECT id, title
     *
     * @param  array|string  $columns  Array or multiple string arguments containing column names
     *
     * @return static
     */
    public function select($columns = null): static
    {
        $this->reset();
        $this->type = 'select';

        if (is_array($columns)) {
            $this->select = $columns;
        } else {
            $this->select = \func_get_args();
        }

        return $this;
    }

    /**
     * Alters which arguments to select
     *
     * Query is assumed to be in SELECT mode
     * See select() for usage
     *
     * @param  array|string  $columns  Array or multiple string arguments containing column names
     *
     * @return static
     */
    public function setSelect($columns = null): static
    {
        if (is_array($columns)) {
            $this->select = $columns;
        } else {
            $this->select = \func_get_args();
        }

        return $this;
    }

    /**
     * Get the columns staged to select
     *
     * @return array
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * Activates the INSERT mode
     *
     * @return static
     */
    public function insert(): static
    {
        $this->reset();
        $this->type = 'insert';

        return $this;
    }

    /**
     * Activates the REPLACE mode
     *
     * @return static
     */
    public function replace(): static
    {
        $this->reset();
        $this->type = 'replace';

        return $this;
    }

    /**
     * Activates the UPDATE mode
     *
     * @param  string  $index  The index to update into
     *
     * @return static
     */
    public function update(string $index): static
    {
        $this->reset();
        $this->type = 'update';
        $this->into($index);

        return $this;
    }

    /**
     * Activates the DELETE mode
     *
     * @return static
     */
    public function delete(): static
    {
        $this->reset();
        $this->type = 'delete';

        return $this;
    }

    /**
     * FROM clause (Sphinx-specific since it works with multiple indexes)
     * func_get_args()-enabled
     *
     * @param  array|\Closure|SphinxQL|string|null  $array  An array of indexes to use
     *
     * @return static
     */
    public function from($array = null): static
    {
        if (is_string($array)) {
            $this->from = \func_get_args();
        }

        if (is_array($array) || $array instanceof \Closure || $array instanceof SphinxQL) {
            $this->from = $array;
        }

        return $this;
    }

    /**
     * MATCH clause (Sphinx-specific)
     *
     * @param  mixed  $column  The column name (can be array, string, Closure, or MatchBuilder)
     * @param  string  $value  The value
     * @param  bool  $half  Exclude ", |, - control characters from being escaped
     *
     * @return static
     */
    public function match($column, $value = null, bool $half = false): static
    {
        if ($column === '*' || (is_array($column) && in_array('*', $column))) {
            $column = array();
        }

        $this->match[] = array('column' => $column, 'value' => $value, 'half' => $half);

        return $this;
    }

    /**
     * WHERE clause
     *
     * Examples:
     *    $query->where('column', 'value');
     *    // WHERE column = 'value'
     *
     *    $query->where('column', '=', 'value');
     *    // WHERE column = 'value'
     *
     *    $query->where('column', '>=', 'value')
     *    // WHERE column >= 'value'
     *
     *    $query->where('column', 'IN', array('value1', 'value2', 'value3'));
     *    // WHERE column IN ('value1', 'value2', 'value3')
     *
     *    $query->where('column', 'BETWEEN', array('value1', 'value2'))
     *    // WHERE column BETWEEN 'value1' AND 'value2'
     *    // WHERE example BETWEEN 10 AND 100
     *
     *    // Nested group (Closure):
     *    $query->where('a', 1)->where(function ($q) {
     *        $q->where('b', 2)->orWhere('c', 3);
     *    });
     *    // WHERE a = 1 AND (b = 2 OR c = 3)
     *
     * @param  string|\Closure  $column  The column name, or a Closure for a nested group
     * @param  Expression|string|null|bool|array|int|float  $operator  The operator to use (if value is not null, you can
     *      use only string)
     * @param  Expression|string|null|bool|array|int|float  $value  The value to check against
     *
     * @return static
     */
    public function where($column, $operator = null, $value = null): static
    {
        return $this->addWhere($column, $operator, $value, 'AND');
    }

    /**
     * OR WHERE clause. Same signature as where(), but joins with OR.
     *
     * @param  string|\Closure  $column
     * @param  Expression|string|null|bool|array|int|float  $operator
     * @param  Expression|string|null|bool|array|int|float  $value
     *
     * @return static
     */
    public function orWhere($column, $operator = null, $value = null): static
    {
        return $this->addWhere($column, $operator, $value, 'OR');
    }

    /**
     * Adds a where condition or a nested group (when a Closure is given as the column)
     * to the where list, tagged with the boolean operator used to glue it to the previous one.
     *
     * @param  string|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean  AND|OR
     *
     * @return static
     */
    protected function addWhere($column, $operator, $value, string $boolean): static
    {
        if ($column instanceof \Closure && $operator === null) {
            $sub = new static($this->getConnection());
            $column($sub);

            $this->where[] = array(
                'type' => 'group',
                'group' => $sub->getWhere(),
                'boolean' => $boolean,
            );

            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = array(
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        );

        return $this;
    }

    /**
     * Adds a REGEX() filter to the WHERE clause (Manticore-specific).
     *
     * Example:
     *    $query->regex('title', '.*foo.*');
     *    // WHERE REGEX(title, '.*foo.*')
     *
     * @param  string  $column  The column to match against
     * @param  string  $pattern  The regular expression pattern
     * @param  string  $boolean  AND|OR — how to glue it to a preceding condition
     *
     * @return static
     */
    public function regex(string $column, string $pattern, string $boolean = 'AND'): static
    {
        $this->where[] = array(
            'type' => 'regex',
            'column' => $column,
            'value' => $pattern,
            'boolean' => $boolean,
        );

        return $this;
    }

    /**
     * Returns the staged where conditions (used internally for nested groups).
     *
     * @return array
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param  string  $column  A column to group by
     *
     * @return static
     */
    public function groupBy(string $column): static
    {
        $this->group_by[] = $column;

        return $this;
    }

    /**
     * GROUP N BY clause (SphinxQL-specific)
     * Changes 'GROUP BY' into 'GROUP N BY'
     *
     * @param  int  $n  Number of items per group
     *
     * @return static
     */
    public function groupNBy(int $n): static
    {
        $this->group_n_by = $n;

        return $this;
    }

    /**
     * WITHIN GROUP ORDER BY clause (SphinxQL-specific)
     * Adds to the previously added columns
     * Works just like a classic ORDER BY
     *
     * @param  string  $column  The column to group by
     * @param  string|null  $direction  The group by direction (asc/desc)
     *
     * @return static
     */
    public function withinGroupOrderBy(string $column, ?string $direction = null): static
    {
        $this->within_group_order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * HAVING clause
     *
     * Examples:
     *    $sq->having('column', 'value');
     *    // HAVING column = 'value'
     *
     *    $sq->having('column', '=', 'value');
     *    // HAVING column = 'value'
     *
     *    $sq->having('column', '>=', 'value')
     *    // HAVING column >= 'value'
     *
     *    $sq->having('column', 'IN', array('value1', 'value2', 'value3'));
     *    // HAVING column IN ('value1', 'value2', 'value3')
     *
     *    $sq->having('column', 'BETWEEN', array('value1', 'value2'))
     *    // HAVING column BETWEEN 'value1' AND 'value2'
     *    // HAVING example BETWEEN 10 AND 100
     *
     * @param  string  $column  The column name
     * @param  string  $operator  The operator to use
     * @param  string  $value  The value to check against
     *
     * @return static
     */
    public function having(string $column, string $operator, $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->having = array(
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        );

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param  string  $column  The column to order on
     * @param  string|null  $direction  The ordering direction (asc/desc)
     *
     * @return static
     */
    public function orderBy(string $column, ?string $direction = null): static
    {
        $this->order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * OPTION clause (SphinxQL-specific)
     * Used by: SELECT
     *
     * @param  string  $name  Option name
     * @param  Expression|array|string|int|bool|float|null  $value  Option value
     *
     * @return static
     */
    public function option(string $name, $value): static
    {
        $this->options[] = array('name' => $name, 'value' => $value);

        return $this;
    }

    /**
     * INTO clause
     * Used by: INSERT, REPLACE
     *
     * @param  string  $index  The index to insert/replace into
     *
     * @return static
     */
    public function into(string $index): static
    {
        $this->into = $index;

        return $this;
    }

    /**
     * Set columns
     * Used in: INSERT, REPLACE
     * func_get_args()-enabled
     *
     * @param  array  $array  The array of columns
     *
     * @return static
     */
    public function columns($array = array()): static
    {
        if (is_array($array)) {
            $this->columns = $array;
        } else {
            $this->columns = \func_get_args();
        }

        return $this;
    }

    /**
     * Set VALUES
     * Used in: INSERT, REPLACE
     * func_get_args()-enabled
     *
     * @param  array  $array  The array of values matching the columns from $this->columns()
     *
     * @return static
     */
    public function values($array): static
    {
        if (is_array($array)) {
            $this->values[] = $array;
        } else {
            $this->values[] = \func_get_args();
        }

        return $this;
    }

    /**
     * Returns the number of value rows staged for an INSERT/REPLACE.
     *
     * @return int
     */
    public function valuesAmount(): int
    {
        return count($this->values);
    }

    /**
     * Set column and relative value
     * Used in: INSERT, REPLACE
     *
     * @param  string  $column  The column name
     * @param  mixed  $value  The value
     *
     * @return static
     */
    public function value(string $column, $value): static
    {
        if ($this->type === 'insert' || $this->type === 'replace') {
            $this->columns[] = $column;
            $this->values[0][] = $value;
        } else {
            $this->set[$column] = $value;
        }

        return $this;
    }

    /**
     * Allows passing an array with the key as column and value as value
     * Used in: INSERT, REPLACE, UPDATE
     *
     * @param  array  $array  Array of key-values
     *
     * @return static
     */
    public function set(array $array): static
    {
        if ($this->columns === array_keys($array)) {
            $this->values($array);
        } else {
            foreach ($array as $key => $item) {
                $this->value($key, $item);
            }
        }

        return $this;
    }

    /**
     * Allows passing an array with the key as column and value as value
     * Used in: INSERT, REPLACE, UPDATE
     *
     * @param  Facet  $facet
     *
     * @return static
     */
    public function facet(Facet $facet): static
    {
        $this->facets[] = $facet;

        return $this;
    }

    /**
     * Sets the characters used for escapeMatch().
     *
     * @param  array  $array  The array of characters to escape
     *
     * @return static
     */
    public function setFullEscapeChars(array $array = array()): static
    {
        if (!empty($array)) {
            $this->escape_full_chars = $this->compileEscapeChars($array);
        }

        return $this;
    }

    /**
     * Sets the characters used for halfEscapeMatch().
     *
     * @param  array  $array  The array of characters to escape
     *
     * @return static
     */
    public function setHalfEscapeChars(array $array = array()): static
    {
        if (!empty($array)) {
            $this->escape_half_chars = $this->compileEscapeChars($array);
        }

        return $this;
    }

    /**
     * Compiles an array containing the characters and escaped characters into a key/value configuration.
     *
     * @param  array  $array  The array of characters to escape
     *
     * @return array An array of the characters and it's escaped counterpart
     */
    public function compileEscapeChars(array $array = array()): array
    {
        $result = array();
        foreach ($array as $character) {
            $result[$character] = '\\'.$character;
        }

        return $result;
    }

    /**
     * Escapes the query for the MATCH() function
     *
     * @param  mixed  $string  The string to escape for the MATCH
     *
     * @return string The escaped string
     */
    public function escapeMatch($string): string
    {
        if (is_null($string)) {
            return '';
        }

        if ($string instanceof Expression) {
            return $string->value();
        }

        return mb_strtolower(str_replace(array_keys($this->escape_full_chars), array_values($this->escape_full_chars),
            $string), 'utf8');
    }

    /**
     * Escapes the query for the MATCH() function
     * Allows some of the control characters to pass through for use with a search field: -, |, "
     * It also does some tricks to wrap/unwrap within " the string and prevents errors
     *
     * @param  mixed  $string  The string to escape for the MATCH
     *
     * @return string The escaped string
     */
    public function halfEscapeMatch($string): string
    {
        if ($string instanceof Expression) {
            return $string->value();
        }

        $string = str_replace(array_keys($this->escape_half_chars), array_values($this->escape_half_chars), $string);

        // this manages to lower the error rate by a lot
        if (mb_substr_count($string, '"', 'utf8') % 2 !== 0) {
            $string .= '"';
        }

        $string = preg_replace('/-[\s-]*-/u', '-', $string);

        $from_to_preg = array(
            '/([-|])\s*$/u' => '\\\\\1',
            '/\|[\s|]*\|/u' => '|',
            '/(\S+)-(\S+)/u' => '\1\-\2',
            '/(\S+)\s+-\s+(\S+)/u' => '\1 \- \2',
        );

        $string = mb_strtolower(preg_replace(array_keys($from_to_preg), array_values($from_to_preg), $string), 'utf8');

        return $string;
    }

    /**
     * Clears the existing query build for new query when using the same SphinxQL instance.
     *
     * @return static
     */
    public function reset(): static
    {
        $this->query = null;
        $this->select = array();
        $this->from = array();
        $this->where = array();
        $this->match = array();
        $this->group_by = array();
        $this->group_n_by = null;
        $this->within_group_order_by = array();
        $this->having = array();
        $this->order_by = array();
        $this->offset = null;
        $this->limit = null;
        $this->into = null;
        $this->columns = array();
        $this->values = array();
        $this->set = array();
        $this->options = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetWhere(): static
    {
        $this->where = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetMatch(): static
    {
        $this->match = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetGroupBy(): static
    {
        $this->group_by = array();
        $this->group_n_by = null;

        return $this;
    }

    /**
     * @return static
     */
    public function resetWithinGroupOrderBy(): static
    {
        $this->within_group_order_by = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetFacets(): static
    {
        $this->facets = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetHaving(): static
    {
        $this->having = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetOrderBy(): static
    {
        $this->order_by = array();

        return $this;
    }

    /**
     * @return static
     */
    public function resetOptions(): static
    {
        $this->options = array();

        return $this;
    }
}
