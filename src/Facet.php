<?php

declare(strict_types=1);

namespace Kvf77\Manticore;

use Kvf77\Manticore\Concerns\HasLimitOffset;
use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Exception\SphinxQLException;

/**
 * Query Builder class for Facet statements.
 * @author Vicent Valls
 */
class Facet
{
    use HasLimitOffset;

    /**
     * A non-static connection for the current Facet object
     *
     * @var ConnectionInterface|null
     */
    protected ?ConnectionInterface $connection;

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
    protected array $facet = array();

    /**
     * BY array to be comma separated
     *
     * @var array|string
     */
    protected array|string $by = array();

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected array $order_by = array();


    /**
     * @param  ConnectionInterface|null  $connection
     */
    public function __construct(?ConnectionInterface $connection = null)
    {
        $this->connection = $connection;
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
     * Sets the connection to be used
     *
     * @param  ConnectionInterface|null  $connection
     *
     * @return static
     */
    public function setConnection(?ConnectionInterface $connection = null): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Facet the columns
     *
     * Gets the arguments passed as $facet->facet('one', 'two')
     * Using it with array maps values as column names
     *
     * Examples:
     *    $query->facet('idCategory');
     *    // FACET idCategory
     *
     *    $query->facet('idCategory', 'year');
     *    // FACET idCategory, year
     *
     *    $query->facet(array('categories' => 'idCategory', 'year', 'type' => 'idType'));
     *    // FACET idCategory AS categories, year, idType AS type
     *
     * @param  array|string  $columns  Array or multiple string arguments containing column names
     *
     * @return static
     */
    public function facet($columns = null): static
    {
        if (!is_array($columns)) {
            $columns = \func_get_args();
        }

        foreach ($columns as $key => $column) {
            if (is_int($key)) {
                if (is_array($column)) {
                    $this->facet($column);
                } else {
                    $this->facet[] = array($column, null);
                }
            } else {
                $this->facet[] = array($column, $key);
            }
        }

        return $this;
    }

    /**
     * Facet a function
     *
     * Gets the function passed as $facet->facetFunction('FUNCTION', array('param1', 'param2', ...))
     *
     * Examples:
     *    $query->facetFunction('category');
     *
     * @param  string  $function  Function name
     * @param  array|string  $params  Array or multiple string arguments containing column names
     *
     * @return static
     */
    public function facetFunction(string $function, $params = null): static
    {
        if (is_array($params)) {
            $params = implode(',', $params);
        }

        $this->facet[] = new Expression($function.'('.$params.')');

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param  string  $column  A column to group by
     *
     * @return static
     */
    public function by(string $column): static
    {
        $this->by = $column;

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param  string  $column  The column to order on
     * @param  string  $direction  The ordering direction (asc/desc)
     *
     * @return static
     */
    public function orderBy(string $column, ?string $direction = null): static
    {
        $this->order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * Facet a function
     *
     * Gets the function passed as $facet->facetFunction('FUNCTION', array('param1', 'param2', ...))
     *
     * Examples:
     *    $query->facetFunction('category');
     *
     * @param  string  $function  Function name
     * @param  array  $params  Array  string arguments containing column names
     * @param  string  $direction  The ordering direction (asc/desc)
     *
     * @return static
     */
    public function orderByFunction(string $function, $params = null, $direction = null): static
    {
        if (is_array($params)) {
            $params = implode(',', $params);
        }

        $this->order_by[] = array('column' => new Expression($function.'('.$params.')'), 'direction' => $direction);

        return $this;
    }

    /**
     * Compiles the statements for FACET
     *
     * @return static
     * @throws SphinxQLException In case no column in facet
     */
    public function compileFacet(): static
    {
        $query = 'FACET ';

        if (!empty($this->facet)) {
            $facets = array();
            foreach ($this->facet as $array) {
                if ($array instanceof Expression) {
                    $facets[] = $array;
                } elseif ($array[1] === null) {
                    $facets[] = $array[0];
                } else {
                    $facets[] = $array[0].' AS '.$array[1];
                }
            }
            $query .= implode(', ', $facets).' ';
        } else {
            throw new SphinxQLException('There is no column in facet.');
        }

        if (!empty($this->by)) {
            $query .= 'BY '.$this->by.' ';
        }

        if (!empty($this->order_by)) {
            $query .= 'ORDER BY ';

            $order_arr = array();

            foreach ($this->order_by as $order) {
                $order_sub = $order['column'].' ';
                $order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        $query .= $this->compileLimitOffset();

        $this->query = trim($query);

        return $this;
    }

    /**
     * Get String with SQL facet
     *
     * @return string
     * @throws SphinxQLException
     */
    public function getFacet(): string
    {
        return $this->compileFacet()->query ?? '';
    }
}
