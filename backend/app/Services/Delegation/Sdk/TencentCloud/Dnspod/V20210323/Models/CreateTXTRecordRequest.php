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
 * CreateTXTRecord请求参数结构体
 *
 * @method string getDomain() 获取域名
 * @method void setDomain(string $Domain) 设置域名
 * @method string getRecordLine() 获取记录线路
 * @method void setRecordLine(string $RecordLine) 设置记录线路
 * @method string getValue() 获取记录值
 * @method void setValue(string $Value) 设置记录值
 * @method integer getDomainId() 获取域名 ID
 * @method void setDomainId(integer $DomainId) 设置域名 ID
 * @method string getSubDomain() 获取主机记录
 * @method void setSubDomain(string $SubDomain) 设置主机记录
 * @method string getRecordLineId() 获取线路的 ID
 * @method void setRecordLineId(string $RecordLineId) 设置线路的 ID
 * @method integer getTTL() 获取TTL
 * @method void setTTL(integer $TTL) 设置TTL
 * @method string getStatus() 获取记录初始状态
 * @method void setStatus(string $Status) 设置记录初始状态
 * @method string getRemark() 获取备注
 * @method void setRemark(string $Remark) 设置备注
 * @method integer getGroupId() 获取记录分组 Id
 * @method void setGroupId(integer $GroupId) 设置记录分组 Id
 */
class CreateTXTRecordRequest extends AbstractModel
{
    /**
     * @var string 域名
     */
    public $Domain;

    /**
     * @var string 记录线路
     */
    public $RecordLine;

    /**
     * @var string 记录值
     */
    public $Value;

    /**
     * @var integer 域名 ID
     */
    public $DomainId;

    /**
     * @var string 主机记录
     */
    public $SubDomain;

    /**
     * @var string 线路的 ID
     */
    public $RecordLineId;

    /**
     * @var integer TTL
     */
    public $TTL;

    /**
     * @var string 记录初始状态
     */
    public $Status;

    /**
     * @var string 备注
     */
    public $Remark;

    /**
     * @var integer 记录分组 Id
     */
    public $GroupId;

    function __construct()
    {

    }

    /**
     * For internal only. DO NOT USE IT.
     */
    public function deserialize($param)
    {
        if ($param === null) {
            return;
        }
        if (array_key_exists("Domain",$param) and $param["Domain"] !== null) {
            $this->Domain = $param["Domain"];
        }

        if (array_key_exists("RecordLine",$param) and $param["RecordLine"] !== null) {
            $this->RecordLine = $param["RecordLine"];
        }

        if (array_key_exists("Value",$param) and $param["Value"] !== null) {
            $this->Value = $param["Value"];
        }

        if (array_key_exists("DomainId",$param) and $param["DomainId"] !== null) {
            $this->DomainId = $param["DomainId"];
        }

        if (array_key_exists("SubDomain",$param) and $param["SubDomain"] !== null) {
            $this->SubDomain = $param["SubDomain"];
        }

        if (array_key_exists("RecordLineId",$param) and $param["RecordLineId"] !== null) {
            $this->RecordLineId = $param["RecordLineId"];
        }

        if (array_key_exists("TTL",$param) and $param["TTL"] !== null) {
            $this->TTL = $param["TTL"];
        }

        if (array_key_exists("Status",$param) and $param["Status"] !== null) {
            $this->Status = $param["Status"];
        }

        if (array_key_exists("Remark",$param) and $param["Remark"] !== null) {
            $this->Remark = $param["Remark"];
        }

        if (array_key_exists("GroupId",$param) and $param["GroupId"] !== null) {
            $this->GroupId = $param["GroupId"];
        }
    }
}
