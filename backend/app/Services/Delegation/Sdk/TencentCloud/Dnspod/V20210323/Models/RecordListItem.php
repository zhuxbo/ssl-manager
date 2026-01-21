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
 * 记录列表元素
 *
 * @method integer getRecordId() 获取记录Id
 * @method void setRecordId(integer $RecordId) 设置记录Id
 * @method string getValue() 获取记录值
 * @method void setValue(string $Value) 设置记录值
 * @method string getStatus() 获取记录状态
 * @method void setStatus(string $Status) 设置记录状态
 * @method string getUpdatedOn() 获取更新时间
 * @method void setUpdatedOn(string $UpdatedOn) 设置更新时间
 * @method string getName() 获取主机名
 * @method void setName(string $Name) 设置主机名
 * @method string getLine() 获取记录线路
 * @method void setLine(string $Line) 设置记录线路
 * @method string getLineId() 获取线路Id
 * @method void setLineId(string $LineId) 设置线路Id
 * @method string getType() 获取记录类型
 * @method void setType(string $Type) 设置记录类型
 * @method integer getWeight() 获取记录权重
 * @method void setWeight(integer $Weight) 设置记录权重
 * @method string getMonitorStatus() 获取记录监控状态
 * @method void setMonitorStatus(string $MonitorStatus) 设置记录监控状态
 * @method string getRemark() 获取记录备注说明
 * @method void setRemark(string $Remark) 设置记录备注说明
 * @method integer getTTL() 获取记录缓存时间
 * @method void setTTL(integer $TTL) 设置记录缓存时间
 * @method integer getMX() 获取MX值
 * @method void setMX(integer $MX) 设置MX值
 * @method boolean getDefaultNS() 获取是否是默认的ns记录
 * @method void setDefaultNS(boolean $DefaultNS) 设置是否是默认的ns记录
 */
class RecordListItem extends AbstractModel
{
    /**
     * @var integer 记录Id
     */
    public $RecordId;

    /**
     * @var string 记录值
     */
    public $Value;

    /**
     * @var string 记录状态
     */
    public $Status;

    /**
     * @var string 更新时间
     */
    public $UpdatedOn;

    /**
     * @var string 主机名
     */
    public $Name;

    /**
     * @var string 记录线路
     */
    public $Line;

    /**
     * @var string 线路Id
     */
    public $LineId;

    /**
     * @var string 记录类型
     */
    public $Type;

    /**
     * @var integer 记录权重
     */
    public $Weight;

    /**
     * @var string 记录监控状态
     */
    public $MonitorStatus;

    /**
     * @var string 记录备注说明
     */
    public $Remark;

    /**
     * @var integer 记录缓存时间
     */
    public $TTL;

    /**
     * @var integer MX值
     */
    public $MX;

    /**
     * @var boolean 是否是默认的ns记录
     */
    public $DefaultNS;

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
        if (array_key_exists("RecordId",$param) and $param["RecordId"] !== null) {
            $this->RecordId = $param["RecordId"];
        }

        if (array_key_exists("Value",$param) and $param["Value"] !== null) {
            $this->Value = $param["Value"];
        }

        if (array_key_exists("Status",$param) and $param["Status"] !== null) {
            $this->Status = $param["Status"];
        }

        if (array_key_exists("UpdatedOn",$param) and $param["UpdatedOn"] !== null) {
            $this->UpdatedOn = $param["UpdatedOn"];
        }

        if (array_key_exists("Name",$param) and $param["Name"] !== null) {
            $this->Name = $param["Name"];
        }

        if (array_key_exists("Line",$param) and $param["Line"] !== null) {
            $this->Line = $param["Line"];
        }

        if (array_key_exists("LineId",$param) and $param["LineId"] !== null) {
            $this->LineId = $param["LineId"];
        }

        if (array_key_exists("Type",$param) and $param["Type"] !== null) {
            $this->Type = $param["Type"];
        }

        if (array_key_exists("Weight",$param) and $param["Weight"] !== null) {
            $this->Weight = $param["Weight"];
        }

        if (array_key_exists("MonitorStatus",$param) and $param["MonitorStatus"] !== null) {
            $this->MonitorStatus = $param["MonitorStatus"];
        }

        if (array_key_exists("Remark",$param) and $param["Remark"] !== null) {
            $this->Remark = $param["Remark"];
        }

        if (array_key_exists("TTL",$param) and $param["TTL"] !== null) {
            $this->TTL = $param["TTL"];
        }

        if (array_key_exists("MX",$param) and $param["MX"] !== null) {
            $this->MX = $param["MX"];
        }

        if (array_key_exists("DefaultNS",$param) and $param["DefaultNS"] !== null) {
            $this->DefaultNS = $param["DefaultNS"];
        }
    }
}
