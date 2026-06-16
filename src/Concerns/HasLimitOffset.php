<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Concerns;

/**
 * Shared LIMIT / OFFSET handling for query builders (SphinxQL, Facet).
 */
trait HasLimitOffset
{
    /**
     * When not null it adds an offset.
     */
    protected ?int $offset = null;

    /**
     * When not null it adds a limit.
     */
    protected ?int $limit = null;

    /**
     * LIMIT clause. Supports also LIMIT offset, limit.
     *
     * @param int      $offset Offset if $limit is specified, else the limit
     * @param null|int $limit  The limit to set, null for no limit
     *
     * @return static
     */
    public function limit(int $offset, ?int $limit = null): static
    {
        if ($limit === null) {
            $this->limit = $offset;

            return $this;
        }

        $this->offset($offset);
        $this->limit = $limit;

        return $this;
    }

    /**
     * OFFSET clause.
     *
     * @param int $offset The offset
     *
     * @return static
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Compiles the "LIMIT <offset>, <limit> " fragment (with a trailing space),
     * or an empty string when neither limit nor offset is set.
     *
     * When only an offset is given, Manticore still requires a limit, so a very
     * large default is used. This method does not mutate the builder state.
     */
    protected function compileLimitOffset(): string
    {
        if ($this->limit === null && $this->offset === null) {
            return '';
        }

        $offset = $this->offset ?? 0;
        $limit = $this->limit ?? 9999999999999;

        return 'LIMIT '.$offset.', '.$limit.' ';
    }
}
