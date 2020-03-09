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
# Use(s)                                                                       #
################################################################################

use Exception;

################################################################################
# Class(es)                                                                    #
################################################################################

/**
 * Class BusinessIdbStorageClient
 *
 * @package idb\idbstorage
 */
class BusinessIdbStorageClient extends IdbStorageClient
{
    /**
     * @param $idbStorageName
     * @return IdbStorageClient|null
     */
    public static function model($idbStorageName)
    {
        if (!empty($idbStorageName)) {
            return new self($idbStorageName);
        }

        return null;
    }

    /**
     * @param $businessId
     * @return bool|string|null
     * @throws Exception
     */
    public function initObject($businessId)
    {
        $query = [
            "service" => "storage",
            "query" => "initObject",
            "requester" => $businessId
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $response
     *
     * @return mixed|null |null
     * @throws Exception
     */
    public function parseResponse($response)
    {
        if ($response == null) {
            throw new Exception('Query returned null');
        }
        $response = json_decode($response, true);

        switch ($response['statusCode']) {
            case 200:
                return json_decode($response['result'], true);
            default:
                return null;
        }
    }

    /**
     * @param $id
     * @return bool|string|null
     * @throws Exception
     */
    public function deleteStorageObject($id)
    {
        $query = [
            "query" => "deleteStorageObject",
            "service" => "storage",
            "objectId" => $id
        ];

        return $this->parseResponse($this->execute($query));
    }

    /**
     * @param $vault
     * @return mixed
     */
    public function findItemByVault($vault)
    {
        $exp = [
            'o' => '=',
            'l' => '#col',
            'r' => ':col'
        ];

        return $this->findStorageItems(
            $exp,
            ['#col' => 'uid'],
            [':col' => $vault]
        );
    }

    /**
     * @param $oid
     * @return mixed
     */
    public function findItemByOid($oid)
    {
        $exp = [
            'o' => 'AND',
            'l' => [
                'o' => '=',
                'l' => '#col',
                'r' => ':col'
            ],
            'r' => [
                'o' => '=',
                'l' => '#col2',
                'r' => ':col2'
            ]
        ];

        return $this->findStorageItems(
            $exp,
            ['#col' => 'oid', '#col2' => 'owner'],
            [':col' => $oid, ':col2' => false]
        );
    }

    /**
     * @param $oid
     * @return mixed
     */
    public function findItemOwnerByOid($oid)
    {
        $exp = [
            'o' => 'AND',
            'l' => [
                'o' => '=',
                'l' => '#col',
                'r' => ':col'
            ],
            'r' => [
                'o' => '=',
                'l' => '#col2',
                'r' => ':col2'
            ]
        ];

        return $this->findStorageItems(
            $exp,
            ['#col' => 'oid', '#col2' => 'owner'],
            [':col' => $oid, ':col2' => true]
        );
    }

    /**
     * @param $id
     * @return mixed
     */
    public function findStorageObjectById($id)
    {
        $exp = [
            'o' => '=',
            'l' => '#col',
            'r' => ':col'
        ];

        return $this->findStorageObjects(
            $exp,
            ['#col' => 'oid'],
            [':col' => $id]
        );
    }

    /**
     * @param $id
     * @return mixed
     */
    public function findStorageItemById($id)
    {
        $exp = [
            'o' => '=',
            'l' => '#col',
            'r' => ':col'
        ];

        return $this->findStorageItems(
            $exp,
            ['#col' => 'id'],
            [':col' => $id]
        );
    }

    /**
     * @param $objectId
     * @return mixed|null
     * @throws Exception
     */
    public function deleteStorageItemByObjectId($objectId)
    {
        $query = [
            "query" => "deleteStorageItemsByObjectId",
            "service" => "storage",
            "objectId" => $objectId
        ];

        return $this->parseResponse($this->execute($query));
    }


    public function deleteStorageItem($itemId)
    {
        $query = [
            "query" => "deleteStorageItem",
            "service" => "storage",
            "itemId" => intval($itemId)
        ];

        return $this->parseResponse($this->execute($query));
    }
}

################################################################################
#                                End of file                                   #
################################################################################
