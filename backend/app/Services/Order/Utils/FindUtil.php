<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

use App\Models\Contact;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Traits\ApiResponseStatic;

class FindUtil
{
    use ApiResponseStatic;

    /**
     * 根据产品ID查找产品
     */
    public static function Product($id, $checkStatus = false): Product
    {
        $product = Product::find($id);

        if (! $product) {
            self::error('产品不存在');
        }

        if ($checkStatus && $product->status == 0) {
            self::error('产品已禁用');
        }

        return $product;
    }

    /**
     * 根据产品编码查找产品
     */
    public static function ProductByCode($code, $checkStatus = false): Product
    {
        $product = Product::where('code', $code)->first();

        if (! $product) {
            self::error('产品不存在');
        }

        if ($checkStatus && $product->status == 0) {
            self::error('产品已禁用');
        }

        return $product;
    }

    /**
     * 根据订单ID查找订单
     */
    public static function Order($id): Order
    {
        $order = Order::with(['latestCert'])->whereHas('latestCert')->find($id);

        if (! $order) {
            self::error('订单或相关数据不存在');
        }

        return $order;
    }

    /**
     * 根据证书ID查找证书
     */
    public static function Cert($id): Cert
    {
        $cert = Cert::with(['order'])->whereHas('order')->find($id);

        if (! $cert) {
            self::error('证书或相关数据不存在');
        }

        return $cert;
    }

    /**
     * 根据用户ID查找用户
     */
    public static function User($id, $checkStatus = false): User
    {
        $user = User::find($id);

        if (! $user) {
            self::error('用户不存在');
        }

        if ($checkStatus && $user->status == 0) {
            self::error('用户已禁用');
        }

        return $user;
    }

    /**
     * 根据用户邮箱查找用户
     */
    public static function UserByEmail($email, $checkStatus = false): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            self::error('用户不存在');
        }

        if ($checkStatus && $user->status == 0) {
            self::error('用户已禁用');
        }

        return $user;
    }

    /**
     * 根据用户手机号查找用户
     */
    public static function UserByPhone($phone, $checkStatus = false): User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            self::error('用户不存在');
        }

        if ($checkStatus && $user->status == 0) {
            self::error('用户已禁用');
        }

        return $user;
    }

    /**
     * 根据用户名查找用户
     */
    public static function UserByUsername($username, $checkStatus = false): User
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            self::error('用户不存在');
        }

        if ($checkStatus && $user->status == 0) {
            self::error('用户已禁用');
        }

        return $user;
    }

    /**
     * 根据组织ID和用户ID查找组织
     */
    public static function Organization(int $id, int $userId): Organization
    {
        $organization = Organization::where('user_id', $userId)->find($id);

        if (! $organization) {
            self::error('组织不存在');
        }

        return $organization;
    }

    /**
     * 根据联系人ID和用户ID查找联系人
     */
    public static function Contact(int $id, int $userId): Contact
    {
        $contact = Contact::where('user_id', $userId)->find($id);

        if (! $contact) {
            self::error('联系人不存在');
        }

        return $contact;
    }
}
