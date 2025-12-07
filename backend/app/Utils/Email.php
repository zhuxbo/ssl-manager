<?php

namespace App\Utils;

use Illuminate\Support\Facades\App;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * 邮件类
 * 继承PHPMailer并初始化好了站点系统配置中的邮件配置信息
 */
class Email extends PHPMailer
{
    /**
     * 是否已在管理后台配置好邮件服务
     */
    public bool $configured = false;

    /**
     * 默认配置
     */
    public array $options = [
        'charset' => 'utf-8', // 编码格式
        'debug' => false, // 调式模式
        'lang' => 'zh_cn',
    ];

    /**
     * 构造函数
     *
     * @throws Throwable
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);

        parent::__construct($this->options['debug']);

        $langSet = App::getLocale();
        if ($langSet == 'zh-cn' || ! $langSet) {
            $langSet = 'zh_cn';
        }

        $this->options['lang'] = $this->options['lang'] ?: $langSet;

        $this->setLanguage($this->options['lang'],
            base_path('vendor'.DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR));
        $this->CharSet = $this->options['charset'];

        $sysMailConfig = get_system_setting('mail');

        $this->configured = true;
        foreach ($sysMailConfig as $item) {
            if (! $item) {
                $this->configured = false;
            }
        }
        if ($this->configured) {
            $this->Host = $sysMailConfig['server'];
            $this->SMTPAuth = true;
            $this->Username = $sysMailConfig['user'];
            $this->Password = $sysMailConfig['password'];
            $this->SMTPSecure = $sysMailConfig['port'] == 465 ? self::ENCRYPTION_SMTPS : self::ENCRYPTION_STARTTLS;
            $this->Port = $sysMailConfig['port'];

            $this->setFrom($sysMailConfig['senderMail'], $sysMailConfig['senderName']);
        }
    }

    public function setSubject($subject): void
    {
        $this->Subject = '=?utf-8?B?'.base64_encode($subject).'?=';
    }
}
