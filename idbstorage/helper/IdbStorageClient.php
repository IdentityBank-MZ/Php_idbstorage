<?php
# * ********************************************************************* *
# *                                                                       *
# *   Driver for IDB Storage                                              *
# *   This file is part of idbstorage. This project may be found at:      *
# *   https://github.com/IdentityBank/Php_idbstorage.                     *
# *                                                                       *
# *   Copyright (C) 2020 by Identity Bank. All Rights Reserved.           *
# *   https://www.identitybank.eu - You belong to you                     *
# *                                                                       *
# *   This program is free software: you can redistribute it and/or       *
# *   modify it under the terms of the GNU Affero General Public          *
# *   License as published by the Free Software Foundation, either        *
# *   version 3 of the License, or (at your option) any later version.    *
# *                                                                       *
# *   This program is distributed in the hope that it will be useful,     *
# *   but WITHOUT ANY WARRANTY; without even the implied warranty of      *
# *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the        *
# *   GNU Affero General Public License for more details.                 *
# *                                                                       *
# *   You should have received a copy of the GNU Affero General Public    *
# *   License along with this program. If not, see                        *
# *   https://www.gnu.org/licenses/.                                      *
# *                                                                       *
# * ********************************************************************* *

################################################################################
# Namespace                                                                    #
################################################################################

namespace idb\idbstorage;

################################################################################
# Include(s)                                                                   #
################################################################################

include_once 'simplelog.inc';

################################################################################
# Use(s)                                                                       #
################################################################################

use DateTime;
use Exception;
use idbyii2\helpers\Localization;
use xmz\simplelog\SimpleLogLevel;
use xmz\simplelog\SNLog as Log;
use function xmz\simplelog\registerLogger;

################################################################################
# Class(es)                                                                    #
################################################################################

abstract class IdbStorageClient
{

    private static $logPath = "/var/log/p57b/";
    protected $idbStorageName = null;
    protected $page = null;
    protected $pageSize = 25;
    private $errors = [];
    private $maxBufferSize = 4096;
    private $host = null;
    private $port = null;
    private $configuration = null;

    /* ---------------------------- General --------------------------------- */

    /**
     * IdbStorageClient constructor.
     *
     * @param $idbStorageName
     */
    public function __construct($idbStorageName)
    {
        $this->idbStorageName = $idbStorageName;
    }

    /**
     * @param $idbStorageName
     * @return \idb\idbstorage\IdbStorageClient|null
     */
    public static abstract function model($idbStorageName);

    /**
     * @param $host
     * @param $port
     * @param null $configuration
     */
    public function setConnection($host, $port, $configuration = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->configuration = $configuration;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $pageSize
     *
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        if (!empty($pageSize) && is_int($pageSize) && ($pageSize > 0)) {
            $this->pageSize = $pageSize;
        }

        return $this;
    }

    /**
     * @param $page
     *
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @param      $page
     * @param null $pageSize
     *
     * @return $this
     */
    public function setPagination($page, $pageSize = null)
    {
        $this->page = $page;
        if (!empty($pageSize) && is_int($pageSize) && ($pageSize > 0)) {
            $this->pageSize = $pageSize;
        }

        return $this;
    }
    /* --------------------------- Storage API ------------------------------ */

    public abstract function parseResponse($response);

    /**
     * @param $query
     *
     * @return bool|string|null
     */
    protected function execute($query)
    {
        try {
            $this->logRequestQuery($query);

            $query = json_encode($query);
            if (!empty($this->host)) {
                $this->host = gethostbyname($this->host);
            }

            if (empty($query) || empty($this->host) || empty($this->port)) {
                return null;
            }

            // ***
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            // ***

            if ($socket === false) {
                $this->errors[] = "socket_create() failed: reason: " . socket_strerror(socket_last_error());
            }

            // ***
            $result = socket_connect($socket, $this->host, $this->port);
            // ***

            if ($result === false) {
                $this->errors[] = "socket_connect() failed. Reason: ($result) " . socket_strerror(
                        socket_last_error($socket)
                    );
            }

            if (empty($this->configuration['Security'])) {
                $queryResult = $this->executeRequestNone($socket, $query);
            } elseif (strtoupper($this->configuration['Security']['type']) === 'TOKEN') {
                $queryResult = $this->executeRequestToken($socket, $query);
            } elseif (strtoupper($this->configuration['Security']['type']) === 'CERTIFICATE') {
                $queryResult = $this->executeRequestCertificate($socket, $query);
            } else {
                $queryResult = $this->executeRequestNone($socket, $query);
            }

            // ***
            socket_close($socket);
            // ***

            if (empty($this->errors)) {
                return $queryResult;
            }
        } catch (Exception $e) {
            error_log('Problem processing your query.');
            error_log(json_encode(['host' => $this->host, 'port' => $this->port]));
            if (!empty($e) and !empty($e->getMessage())) {
                error_log($e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param $query
     */
    private function logRequestQuery($query)
    {
        if (SimpleLogLevel::get() < SimpleLogLevel::DEBUG) {
            return;
        }
        $logName = "p57b.idstorage.query.log";
        $logPath = self::$logPath . $logName;
        registerLogger($logName, $logPath);

        $pid = getmypid();
        Log::debug(
            $logName,
            "$pid - " .
            "[Q|" . json_encode($query) . "]"
        );
    }

    /**
     * @param $socket
     * @param $query
     *
     * @return string|null
     */
    public function executeRequestNone($socket, $query)
    {
        $queryResult = null;
        try {
            socket_write($socket, $query, strlen($query));

            $queryResult = '';
            while ($result = socket_read($socket, $this->maxBufferSize)) {
                $queryResult .= $result;
            }
        } catch (Exception $e) {
            $queryResult = null;
        }

        return $queryResult;
    }

    /**
     * @param $socket
     * @param $query
     *
     * @return bool|string|null
     */
    public function executeRequestToken($socket, $query)
    {
        $queryResult = null;
        if (!empty($this->configuration['socketTimeout'])) {
            socket_set_option(
                $socket,
                SOL_SOCKET,
                SO_RCVTIMEO,
                ['sec' => $this->configuration['socketTimeout'], 'usec' => 0]
            );
            socket_set_option(
                $socket,
                SOL_SOCKET,
                SO_SNDTIMEO,
                ['sec' => $this->configuration['socketTimeout'], 'usec' => 0]
            );
        }
        try {

            $dataChecksum = md5($query);
            $dataLength = strlen($query);
            $dataChecksumLength = strlen($dataChecksum);
            $size = $dataLength + $dataChecksumLength;
            $size = pack('P', $size);
            $protocolVersion = 1;
            $protocolVersion = pack('V', $protocolVersion);
            $token = $this->configuration['Security']['token'];
            $token = str_pad($token, $this->configuration['Security']['tokenSizeBytes'], " ", STR_PAD_RIGHT);
            $id = time();
            $id = pack('P', $id);
            $dataChecksumType = str_pad('MD5', 8);

            socket_write($socket, $protocolVersion, strlen($protocolVersion));
            socket_write($socket, $token, strlen($token));
            socket_write($socket, $size, strlen($size));
            socket_write($socket, $id, strlen($id));
            socket_write($socket, $dataChecksumType, strlen($dataChecksumType));
            socket_write($socket, $dataChecksum, strlen($dataChecksum));
            socket_write($socket, $query, strlen($query));

            $version = '';
            while ($result = socket_read($socket, 4)) {
                $version .= $result;
                if (4 <= strlen($version)) {
                    break;
                }
            }
            if (!empty($version)) {
                $version = unpack('V', $version);
                $version = intval($version);
            }
            if ($version != 1) {
                return null;
            }
            $token = '';
            while ($result = socket_read($socket, $this->configuration['Security']['tokenSizeBytes'])) {
                $token .= $result;
                if ($this->configuration['Security']['tokenSizeBytes'] <= strlen($token)) {
                    break;
                }
            }
            $size = '';
            while ($result = socket_read($socket, 8)) {
                $size .= $result;
                if (8 <= strlen($size)) {
                    break;
                }
            }
            if (!empty($size)) {
                $size = unpack('P', $size);
            }
            $id = '';
            while ($result = socket_read($socket, 8)) {
                $id .= $result;
                if (8 <= strlen($id)) {
                    break;
                }
            }
            if (!empty($id)) {
                $id = unpack('P', $id);
            }
            $checksumType = '';
            while ($result = socket_read($socket, 8)) {
                $checksumType .= $result;
                if (8 <= strlen($checksumType)) {
                    break;
                }
            }
            $checksumType = trim($checksumType);

            $queryResult = '';
            while ($result = socket_read($socket, $this->maxBufferSize)) {
                $queryResult .= $result;
                if ($size <= strlen($queryResult)) {
                    break;
                }
            }
            $checksum = substr($queryResult, 0, 32);
            $queryResult = substr($queryResult, 32);
            if (strtoupper($checksumType) === 'MD5') {
                $dataChecksum = md5($queryResult);
            } else {
                $dataChecksum = null;
            }
            if (strtolower($checksum) !== $dataChecksum) {
                $queryResult = null;
            }
        } catch (Exception $e) {
            $queryResult = null;
        }

        return $queryResult;
    }

    /* ---------------------------- Internal -------------------------------- */

    /**
     * @param $expression
     * @param $colNames
     * @param $colValues
     *
     * @return mixed
     */
    public function findStorageItems($expression, $colNames, $colValues)
    {
        $query = [
            "service" => "storage",
            "query" => 'findCountAllStorageItems',
            "FilterExpression" => $expression,
            "OrderByDataTypes" => ['createtime' => "DESC"],
            "ExpressionAttributeNames" => $colNames,
            "ExpressionAttributeValues" => $colValues,
            "PaginationConfig" => [
                'Page' => $this->page,
                'PageSize' => $this->pageSize,
            ]
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $expression
     * @param $colNames
     * @param $colValues
     *
     * @return mixed
     */
    public function findStorageObjects($expression, $colNames, $colValues)
    {
        $query = [
            "query" => 'findCountAllStorageObjects',
            "service" => "storage",
            "FilterExpression" => $expression,
            "ExpressionAttributeNames" => $colNames,
            "ExpressionAttributeValues" => $colValues,
            "PaginationConfig" => [
                'Page' => $this->page,
                'PageSize' => $this->pageSize,
            ]
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $id
     * @param $data
     *
     * @return mixed
     */
    public function updateStorageItem($id, $data)
    {
        $query = [
            "query" => "updateFile",
            "id" => $id,
            'data' => $data
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $id
     * @param $data
     *
     * @return mixed
     */
    public function updateStorageObject($id, $data)
    {
        $query = [
            "query" => "updateFileShare",
            "id" => $id,
            'data' => $data
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $data
     * @return mixed
     */
    public function addStorageItem($data)
    {
        $query = [
            "query" => "addStorageItem",
            "data" => $data
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $id
     * @param $data
     * @return bool|string|null
     */
    public function editStorageObject($id, $data)
    {
        $query = [
            "query" => "editStorageObject",
            "objectId" => $id,
            "service" => "storage",
            "data" => $data
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $objectId
     * @return mixed
     */
    public function downloadStorageObject($objectId, $filename)
    {
        $query = [
            "query"=> "downloadStorageObject",
            "service"=> "storage",
            "filename" => $filename,
            "objectId"=> $objectId
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $objectId
     * @return mixed
     */
    public function infoStorageObject($objectId)
    {
        $query = [
            "query" => "infoStorageObject",
            "service" => "storage",
            "objectId" => $objectId
        ];

        return $this->parseResponse($this->execute($query));
    }
}

################################################################################
#                                End of file                                   #
################################################################################
