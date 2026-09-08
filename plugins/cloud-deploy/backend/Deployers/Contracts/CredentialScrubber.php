<?php

namespace Plugins\CloudDeploy\Deployers\Contracts;

/** 各云厂商错误文案的凭证兜底脱敏；保留普通业务原因。 */
class CredentialScrubber
{
    /**
     * 每条 pattern 都用一个安全占位整体替换命中段（含「键=值」一并抹掉，避免只删值留键仍暴露语义）。
     *
     * @var list<string>
     */
    private const PATTERNS = [
        // 签名诊断可能回显整个请求；从标记处起丢弃剩余内容。
        '/\b(?:String\s*To\s*Sign|Canonical\s*Request)\b.*$/is',
        // 完整 PEM 连正文一起移除；缺少结束标记时丢弃余下内容。
        '/-----BEGIN ([A-Z0-9][A-Z0-9 _]*)-----.*?(?:-----END \1-----|\z)/s',
        // 阿里/AWS 风格 AccessKeyId 字面量（AKIA + 16 位）
        '/AKIA[0-9A-Z]{16}/',
        // 阿里云真实 AccessKeyId 前缀 LTAI（长度不定，取到非字母数字为止；至少 6 位防误伤普通词）
        '/LTAI[0-9A-Za-z]{6,}/',
        // 认证头可能含空格、逗号及多个签名字段，不能只删第一个单词。
        '~\bAuthorization["\']?\s*[:=]\s*(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\r\n]+)~i',
        '~\b(?:Bearer|Basic)\s+[^\s,"\'&]+~i',
        // JSON、查询串及普通键值对；带引号的值允许空格和转义字符。
        '~(?<![\w-])["\']?(?:(?:OSS)?Access[_-]?Key(?:[_-]?(?:Id|Secret))?|Secret[_-]?Access[_-]?Key|Secret[_-]?(?:Id|Key)|X-Amz-(?:Credential|Signature|Security-Token)|X-Auth-Key|Signature|Api[_-]?(?:Key|Token|Secret)|(?:Access|Refresh|Auth|Security|Session)[_-]?Token|Token|Client[_-]?Secret|Private[_-]?Key|Password)["\']?\s*[=:]\s*(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s&,;"\'\]}]+)~i',
    ];

    /** 兜底扫描：命中任一凭证 pattern 即替换为占位。无命中时原样返回。 */
    public static function scrub(string $message): string
    {
        // 先按原始边界清除字段值，避免值内的 %26 / %22 解码后变成分隔符。
        $scrubbed = preg_replace(self::PATTERNS, '[redacted]', $message);
        if ($scrubbed === null) {
            return '[redacted]';
        }
        // 再检查整体 URL 编码的 PEM / 请求回显；无新增命中时保留原文编码。
        $decoded = urldecode($scrubbed);
        $decodedScrubbed = preg_replace(self::PATTERNS, '[redacted]', $decoded);
        if ($decodedScrubbed === null) {
            return '[redacted]';
        }

        return $decodedScrubbed !== $decoded ? $decodedScrubbed : $scrubbed;
    }
}
