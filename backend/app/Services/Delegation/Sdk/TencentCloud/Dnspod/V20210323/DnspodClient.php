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

namespace App\Services\Delegation\Sdk\TencentCloud\Dnspod\V20210323;

use App\Services\Delegation\Sdk\TencentCloud\Common\AbstractClient;
use App\Services\Delegation\Sdk\TencentCloud\Common\Profile\ClientProfile;
use App\Services\Delegation\Sdk\TencentCloud\Common\Credential;
use App\Services\Delegation\Sdk\TencentCloud\Dnspod\V20210323\Models as Models;

/**
 * @method Models\CreateTXTRecordResponse CreateTXTRecord(Models\CreateTXTRecordRequest $req) 添加TXT记录
 * @method Models\DeleteRecordResponse DeleteRecord(Models\DeleteRecordRequest $req) 删除记录
 * @method Models\DeleteRecordBatchResponse DeleteRecordBatch(Models\DeleteRecordBatchRequest $req) 批量删除解析记录
 * @method Models\DescribeRecordListResponse DescribeRecordList(Models\DescribeRecordListRequest $req) 获取某个域名下的解析记录列表
 */

class DnspodClient extends AbstractClient
{
    /**
     * @var string
     */
    protected $endpoint = "dnspod.tencentcloudapi.com";

    /**
     * @var string
     */
    protected $service = "dnspod";

    /**
     * @var string
     */
    protected $version = "2021-03-23";

    /**
     * @param Credential $credential
     * @param string $region
     * @param ClientProfile|null $profile
     */
    function __construct($credential, $region, $profile=null)
    {
        parent::__construct($this->endpoint, $this->version, $credential, $region, $profile);
    }

    public function returnResponse($action, $response)
    {
        $respClass = "App\\Services\\Delegation\\Sdk\\TencentCloud\\Dnspod\\V20210323\\Models\\".ucfirst($action)."Response";
        $obj = new $respClass();
        $obj->deserialize($response);
        return $obj;
    }
}
