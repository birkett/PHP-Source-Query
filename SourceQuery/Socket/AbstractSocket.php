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

namespace xPaw\SourceQuery\Socket;

use xPaw\SourceQuery\Buffer;
use xPaw\SourceQuery\Exception\InvalidArgumentException;
use xPaw\SourceQuery\Exception\InvalidPacketException;
use xPaw\SourceQuery\Exception\SocketException;

abstract class AbstractSocket implements SocketInterface
{
    public string $address = '';

    public int $port = 0;

    /**
     * @var ?resource
     *
     * @psalm-var null|resource|closed-resource
     */
    public $socket;

    public int $timeout = 0;

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return resource
     *
     * @throws InvalidArgumentException
     */
    public function getSocket()
    {
        if (!is_resource($this->socket)) {
            throw new InvalidArgumentException('Socket not open.');
        }

        return $this->socket;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @throws SocketException
     */
    public function open(string $address, int $port, int $timeout): void
    {
        $this->timeout = $timeout;
        $this->port = $port;
        $this->address = $address;

        $socket = @fsockopen('udp://' . $address, $port, $errNo, $errStr, $timeout);

        if ($errNo || false === $socket) {
            throw new SocketException('Could not create socket: ' . $errStr, SocketException::COULD_NOT_CREATE_SOCKET);
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $timeout);
        stream_set_blocking($this->socket, true);
    }

    /**
     * Close.
     */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);

            $this->socket = null;
        }
    }

    /**
     * Reads from socket and returns Buffer.
     *
     * @return Buffer Buffer
     *
     * @throws InvalidPacketException
     * @throws SocketException
     */
    public function read(int $length = 1400): Buffer
    {
        if (!is_resource($this->socket)) {
            throw new InvalidPacketException('Socket not open.');
        }

        $buffer = new Buffer();
        $data = fread($this->socket, abs($length));

        if (!$data) {
            throw new SocketException('Failed to open socket.');
        }

        $buffer->set($data);

        $this->readInternal($buffer, abs($length), [$this, 'sherlock']);

        return $buffer;
    }

    /**
     * @throws InvalidPacketException
     */
    public function write(int $header, string $string = ''): bool
    {
        if (!is_resource($this->socket)) {
            throw new InvalidPacketException('Socket not open.');
        }

        $command = pack('ccccca*', 0xFF, 0xFF, 0xFF, 0xFF, $header, $string);
        $length = strlen($command);

        return $length === fwrite($this->socket, $command, $length);
    }

    /**
     * @throws InvalidPacketException
     */
    public function sherlock(Buffer $buffer, int $length): bool
    {
        if (!is_resource($this->socket)) {
            throw new InvalidPacketException('Socket not open.');
        }

        $data = fread($this->socket, abs($length));

        if (!$data) {
            throw new InvalidPacketException('Empty data from packet.');
        }

        if (strlen($data) < 4) {
            return false;
        }

        $buffer->set($data);

        return -2 === $buffer->getLong();
    }

    /**
     * Get packet data (count, number, checksum) from the buffer. Different for goldsrc/src.
     */
    abstract protected function readInternalPacketData(
        Buffer $buffer,
        int &$count,
        int &$number,
        bool &$isCompressed,
        ?int &$checksum
    ): void;

    /**
     * @throws InvalidPacketException
     */
    protected function readInternal(Buffer $buffer, int $length, callable $sherlockFunction): Buffer
    {
        if ($buffer->isEmpty()) {
            throw new InvalidPacketException('Failed to read any data from socket', InvalidPacketException::BUFFER_EMPTY);
        }

        $header = $buffer->getLong();

        // Single packet, do nothing.
        if (-1 === $header) {
            return $buffer;
        }

        if (-2 === $header) { // Split packet
            $packets = [];
            $packetCount = 0;
            $packetNumber = 0;
            $packetChecksum = null;

            do {
                $requestId = $buffer->getLong();
                $isCompressed = ($requestId & 0x80000000) !== 0;

                $this->readInternalPacketData(
                    $buffer,
                    $packetCount,
                    $packetNumber,
                    $isCompressed,
                    $packetChecksum
                );

                $packets[$packetNumber] = $buffer->get();

                $readMore = $packetCount > count($packets);
            } while ($readMore && $sherlockFunction($buffer, $length));

            $data = implode('', $packets);

            // TODO: Test this
            if ($isCompressed) {
                $data = bzdecompress($data);

                if (!is_string($data) || crc32($data) !== $packetChecksum) {
                    throw new InvalidPacketException('CRC32 checksum mismatch of uncompressed packet data.', InvalidPacketException::CHECKSUM_MISMATCH);
                }
            }

            $buffer->set(substr($data, 4));
        } else {
            throw new InvalidPacketException('Socket read: Raw packet header mismatch. (0x' . dechex($header) . ')', InvalidPacketException::PACKET_HEADER_MISMATCH);
        }

        return $buffer;
    }
}
