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
 * CreateTXTRecord返回参数结构体
 *
 * @method int getRecordId() 获取记录ID
 * @method void setRecordId(integer $RecordId) 设置记录ID
 * @method string getRequestId() 获取唯一请求 ID
 * @method void setRequestId(string $RequestId) 设置唯一请求 ID
 */
class CreateTXTRecordResponse extends AbstractModel
{
    /**
     * @var int 记录ID
     */
    public $RecordId;

    /**
     * @var string 唯一请求 ID
     */
    public $RequestId;

    public function __construct() {}

    /**
     * For internal only. DO NOT USE IT.
     */
    public function deserialize($param)
    {
        if ($param === null) {
            return;
        }
        if (array_key_exists('RecordId', $param) and $param['RecordId'] !== null) {
            $this->RecordId = $param['RecordId'];
        }

        if (array_key_exists('RequestId', $param) and $param['RequestId'] !== null) {
            $this->RequestId = $param['RequestId'];
        }
    }
}
