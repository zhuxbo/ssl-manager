<?php

/*
 * Copyright (c) 2017-2025 Tencent. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace App\Services\Delegation\Sdk\TencentCloud\Dnspod\V20210323\Models;

use App\Services\Delegation\Sdk\TencentCloud\Common\AbstractModel;

/**
 * 批量删除记录详情
 *
 * @method int getDomainId() 获取域名 ID
 * @method void setDomainId(integer $DomainId) 设置域名 ID
 * @method string getDomain() 获取域名
 * @method void setDomain(string $Domain) 设置域名
 * @method string getError() 获取错误信息
 * @method void setError(string $Error) 设置错误信息
 * @method string getStatus() 获取删除状态
 * @method void setStatus(string $Status) 设置删除状态
 * @method string getOperation() 获取操作
 * @method void setOperation(string $Operation) 设置操作
 * @method string getRecordList() 获取解析记录列表
 * @method void setRecordList(string $RecordList) 设置解析记录列表
 */
class DeleteRecordBatchDetail extends AbstractModel
{
    /**
     * @var int 域名 ID
     */
    public $DomainId;

    /**
     * @var string 域名
     */
    public $Domain;

    /**
     * @var string 错误信息
     */
    public $Error;

    /**
     * @var string 删除状态
     */
    public $Status;

    /**
     * @var string 操作
     */
    public $Operation;

    /**
     * @var string 解析记录列表
     */
    public $RecordList;

    public function __construct() {}

    /**
     * For internal only. DO NOT USE IT.
     */
    public function deserialize($param)
    {
        if ($param === null) {
            return;
        }
        if (array_key_exists('DomainId', $param) and $param['DomainId'] !== null) {
            $this->DomainId = $param['DomainId'];
        }

        if (array_key_exists('Domain', $param) and $param['Domain'] !== null) {
            $this->Domain = $param['Domain'];
        }

        if (array_key_exists('Error', $param) and $param['Error'] !== null) {
            $this->Error = $param['Error'];
        }

        if (array_key_exists('Status', $param) and $param['Status'] !== null) {
            $this->Status = $param['Status'];
        }

        if (array_key_exists('Operation', $param) and $param['Operation'] !== null) {
            $this->Operation = $param['Operation'];
        }

        if (array_key_exists('RecordList', $param) and $param['RecordList'] !== null) {
            $this->RecordList = $param['RecordList'];
        }
    }
}
