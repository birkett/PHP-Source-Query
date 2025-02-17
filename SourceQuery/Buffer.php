<?php

declare(strict_types=1);

/**
 * @author Pavel Djundik
 *
 * @see https://xpaw.me
 * @see https://github.com/xPaw/PHP-Source-Query
 *
 * @license GNU Lesser General Public License, version 2.1
 *
 * @internal
 */

namespace xPaw\SourceQuery;

use xPaw\SourceQuery\Exception\InvalidPacketException;

final class Buffer
{
    /**
     * Buffer.
     */
    private string $buffer = '';

    /**
     * Buffer length.
     */
    private int $length = 0;

    /**
     * Current position in buffer.
     */
    private int $position = 0;

    /**
     * Sets buffer.
     */
    public function set(string $buffer): void
    {
        $this->buffer = $buffer;
        $this->length = strlen($buffer);
        $this->position = 0;
    }

    /**
     * Get remaining bytes.
     *
     * @return int Remaining bytes in buffer
     */
    public function remaining(): int
    {
        return $this->length - $this->position;
    }

    public function isEmpty(): bool
    {
        return $this->remaining() <= 0;
    }

    /**
     * Gets data from buffer.
     *
     * @param int $length Bytes to read
     */
    public function get(int $length = -1): string
    {
        if (0 === $length) {
            return '';
        }

        $remaining = $this->remaining();

        if (-1 === $length) {
            $length = $remaining;
        } elseif ($length > $remaining) {
            return '';
        }

        $data = substr($this->buffer, $this->position, $length);

        $this->position += $length;

        return $data;
    }

    /**
     * Get byte from buffer.
     */
    public function getByte(): int
    {
        return ord($this->get(1));
    }

    /**
     * Get byte (boolean) from buffer.
     */
    public function getBool(): bool
    {
        return 1 === $this->getByte();
    }

    /**
     * Get byte (character) from buffer.
     */
    public function getChar(): string
    {
        return chr($this->getByte());
    }

    /**
     * Get short from buffer.
     *
     * @throws InvalidPacketException
     */
    public function getShort(): int
    {
        if ($this->remaining() < 2) {
            throw new InvalidPacketException('Not enough data to unpack a short.', InvalidPacketException::BUFFER_EMPTY);
        }

        $data = unpack('v', $this->get(2));

        if (!$data) {
            throw new InvalidPacketException('Empty data from packet.');
        }

        return (int) $data[1];
    }

    /**
     * Get long from buffer.
     *
     * @throws InvalidPacketException
     */
    public function getLong(): int
    {
        if ($this->remaining() < 4) {
            throw new InvalidPacketException('Not enough data to unpack a long.', InvalidPacketException::BUFFER_EMPTY);
        }

        $data = unpack('l', $this->get(4));

        if (!$data) {
            throw new InvalidPacketException('Empty data from packet.');
        }

        return (int) $data[1];
    }

    /**
     * Get float from buffer.
     *
     * @throws InvalidPacketException
     */
    public function getFloat(): float
    {
        if ($this->remaining() < 4) {
            throw new InvalidPacketException('Not enough data to unpack a float.', InvalidPacketException::BUFFER_EMPTY);
        }

        $data = unpack('f', $this->get(4));

        if (!$data) {
            throw new InvalidPacketException('Empty data from packet.');
        }

        return (float) $data[1];
    }

    /**
     * Get unsigned long from buffer.
     *
     * @throws InvalidPacketException
     */
    public function getUnsignedLong(): int
    {
        if ($this->remaining() < 4) {
            throw new InvalidPacketException('Not enough data to unpack an usigned long.', InvalidPacketException::BUFFER_EMPTY);
        }

        $data = unpack('V', $this->get(4));

        if (!$data) {
            throw new InvalidPacketException('Empty data from packet.');
        }

        return (int) $data[1];
    }

    /**
     * Read one string from buffer ending with null byte.
     */
    public function getString(): string
    {
        $zeroBytePosition = strpos($this->buffer, "\0", $this->position);

        if (false === $zeroBytePosition) {
            return '';
        }

        $string = $this->get($zeroBytePosition - $this->position);

        ++$this->position;

        return $string;
    }
}
