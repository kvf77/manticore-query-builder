<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers\Mysqli;

use Kvf77\Manticore\Drivers\ConnectionBase;
use Kvf77\Manticore\Drivers\MultiResultSet;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSet;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Exception\ConnectionException;
use Kvf77\Manticore\Exception\DatabaseException;
use Kvf77\Manticore\Exception\SphinxQLException;

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * It also contains escaping and quoting functions.
 */
class Connection extends ConnectionBase
{
    /**
     * Internal Encoding
     *
     * @var string|null
     */
    protected ?string $internal_encoding = null;

    /**
     * Returns the internal encoding.
     *
     * @return string|null current multibyte internal encoding
     */
    public function getInternalEncoding(): ?string
    {
        return $this->internal_encoding;
    }

    /**
     * @inheritdoc
     */
    public function connect(): bool
    {
        $data = $this->getParams();
        $conn = mysqli_init();

        if (!empty($data['options'])) {
            foreach ($data['options'] as $option => $value) {
                $conn->options($option, $value);
            }
        }

        set_error_handler(static fn (int $errno, string $errstr): bool => true);
        try {
            if (!$conn->real_connect($data['host'], null, null, null, (int) $data['port'], $data['socket'])) {
                throw new ConnectionException('Connection Error: ['.$conn->connect_errno.']'.$conn->connect_error);
            }
        } finally {
            restore_error_handler();
        }

        $conn->set_charset('utf8');
        $this->connection = $conn;
        $this->mbPush();

        return true;
    }

    /**
     * Pings the Sphinx server.
     *
     * @return bool True if connected, false otherwise
     * @throws ConnectionException
     */
    public function ping(): bool
    {
        $this->ensureConnection();

        return $this->getConnection()->ping();
    }

    /**
     * @inheritdoc
     */
    public function close(): static
    {
        $this->mbPop();
        $this->getConnection()->close();

        return parent::close();
    }

    /**
     * @inheritdoc
     */
    public function query(string $query): ResultSetInterface
    {
        $this->ensureConnection();

        set_error_handler(static fn (int $errno, string $errstr): bool => true);
        try {
            /**
             * ManticoreSearch/Sphinx silence warnings thrown by php mysqli/mysqlnd
             *
             * unknown command (code=9) - status() command not implemented by Sphinx/ManticoreSearch
             * ERROR mysqli::prepare(): (08S01/1047): unknown command (code=22) - prepare() not implemented by Sphinx/Manticore
             */
            $resource = @$this->getConnection()->query($query);
        } finally {
            restore_error_handler();
        }

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.$query.']');
        }

        return new ResultSet(new ResultSetAdapter($this, $resource));
    }

    /**
     * @inheritdoc
     */
    public function multiQuery(array $queue): MultiResultSetInterface
    {
        $count = count($queue);

        if ($count === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $this->ensureConnection();

        $this->getConnection()->multi_query(implode(';', $queue));

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.implode(';', $queue).']');
        }

        return new MultiResultSet(new MultiResultSetAdapter($this));
    }

    /**
     * Escapes the input with \MySQLi::real_escape_string.
     * Based on FuelPHP's escaping function.
     * @inheritdoc
     */
    public function escape(string $value): string
    {
        $this->ensureConnection();

        if (($value = $this->getConnection()->real_escape_string($value)) === false) {
            // @codeCoverageIgnoreStart
            throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
            // @codeCoverageIgnoreEnd
        }

        return "'".$value."'";
    }

    /**
     * Enter UTF-8 multi-byte workaround mode.
     *
     * @return static
     */
    public function mbPush(): static
    {
        $this->internal_encoding = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        return $this;
    }

    /**
     * Exit UTF-8 multi-byte workaround mode.
     *
     * @return static
     */
    public function mbPop(): static
    {
        // TODO: add test case for #155
        if ($this->getInternalEncoding()) {
            mb_internal_encoding($this->getInternalEncoding());
            $this->internal_encoding = null;
        }

        return $this;
    }
}
