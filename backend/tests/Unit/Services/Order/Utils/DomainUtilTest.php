<?php

namespace Tests\Unit\Services\Order\Utils;

use App\Services\Order\Utils\DomainUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainUtilTest extends TestCase
{
    // ==================== getRootDomain ====================

    #[DataProvider('rootDomainProvider')]
    public function test_get_root_domain(string $domain, string $expected): void
    {
        $this->assertEquals($expected, DomainUtil::getRootDomain($domain));
    }

    public static function rootDomainProvider(): array
    {
        return [
            '标准域名' => ['example.com', 'example.com'],
            '子域名' => ['sub.example.com', 'example.com'],
            '多级子域名' => ['a.b.c.example.com', 'example.com'],
            '通配符域名' => ['*.example.com', 'example.com'],
            '通配符子域名' => ['*.sub.example.com', 'example.com'],
            'www子域名' => ['www.example.com', 'example.com'],
            '.org域名' => ['example.org', 'example.org'],
            '.net域名' => ['sub.example.net', 'example.net'],
            '.cn域名' => ['example.cn', 'example.cn'],
            '.com.cn域名' => ['example.com.cn', 'example.com.cn'],
            '.co.uk域名' => ['example.co.uk', 'example.co.uk'],
            '子域名.co.uk' => ['sub.example.co.uk', 'example.co.uk'],
        ];
    }

    public function test_get_root_domain_ipv4_returns_empty(): void
    {
        $this->assertEquals('', DomainUtil::getRootDomain('192.168.1.1'));
    }

    public function test_get_root_domain_ipv6_returns_empty(): void
    {
        $this->assertEquals('', DomainUtil::getRootDomain('::1'));
        $this->assertEquals('', DomainUtil::getRootDomain('2001:db8::1'));
    }

    // ==================== isValidDomain ====================

    #[DataProvider('validDomainProvider')]
    public function test_is_valid_domain_returns_true(string $domain): void
    {
        $this->assertTrue(DomainUtil::isValidDomain($domain));
    }

    public static function validDomainProvider(): array
    {
        return [
            '标准域名' => ['example.com'],
            '子域名' => ['sub.example.com'],
            '多级子域名' => ['a.b.c.example.com'],
            '通配符域名' => ['*.example.com'],
            '数字开头' => ['123example.com'],
            '包含连字符' => ['my-example.com'],
            '下划线域名' => ['_dmarc.example.com'],
            '短域名' => ['a.co'],
            '最大长度标签' => [str_repeat('a', 63).'.com'],
        ];
    }

    #[DataProvider('invalidDomainProvider')]
    public function test_is_valid_domain_returns_false(string $domain): void
    {
        $this->assertFalse(DomainUtil::isValidDomain($domain));
    }

    public static function invalidDomainProvider(): array
    {
        return [
            '空字符串' => [''],
            '连字符开头' => ['-example.com'],
            '连字符结尾' => ['example-.com'],
            '超长标签' => [str_repeat('a', 64).'.com'],
            '总长度超过253' => [str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.com'],
            '无效字符' => ['exam ple.com'],
            '双点' => ['example..com'],
        ];
    }

    // ==================== getType ====================

    #[DataProvider('domainTypeProvider')]
    public function test_get_type(string $input, ?string $expected): void
    {
        $this->assertEquals($expected, DomainUtil::getType($input));
    }

    public static function domainTypeProvider(): array
    {
        return [
            '标准域名' => ['example.com', 'standard'],
            '子域名' => ['sub.example.com', 'standard'],
            '通配符域名' => ['*.example.com', 'wildcard'],
            'IPv4' => ['192.168.1.1', 'ipv4'],
            'IPv4 localhost' => ['127.0.0.1', 'ipv4'],
            'IPv6 短格式' => ['::1', 'ipv6'],
            'IPv6 长格式' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', 'ipv6'],
            '无效输入' => ['not a domain', null],
            '空字符串' => ['', null],
            '无效通配符' => ['*invalid', null],
        ];
    }

    // ==================== convertToAscii / convertToUnicode ====================

    public function test_convert_to_ascii(): void
    {
        $this->assertEquals('xn--fiq228c.com', DomainUtil::convertToAscii('中文.com'));
        $this->assertEquals('example.com', DomainUtil::convertToAscii('example.com'));
    }

    public function test_convert_to_unicode(): void
    {
        $this->assertEquals('中文.com', DomainUtil::convertToUnicode('xn--fiq228c.com'));
        $this->assertEquals('example.com', DomainUtil::convertToUnicode('example.com'));
    }

    public function test_convert_to_ascii_domains(): void
    {
        $this->assertEquals('xn--fiq228c.com,example.com', DomainUtil::convertToAsciiDomains('中文.com,example.com'));
    }

    public function test_convert_to_unicode_domains(): void
    {
        $this->assertEquals('中文.com,example.com', DomainUtil::convertToUnicodeDomains('xn--fiq228c.com,example.com'));
    }

    // ==================== isWildcardMatched ====================

    #[DataProvider('wildcardMatchedProvider')]
    public function test_is_wildcard_matched(string $domain, string $baseDomain, bool $expected): void
    {
        $this->assertEquals($expected, DomainUtil::isWildcardMatched($domain, $baseDomain));
    }

    public static function wildcardMatchedProvider(): array
    {
        return [
            '一级子域名匹配' => ['sub.example.com', 'example.com', true],
            'www匹配' => ['www.example.com', 'example.com', true],
            '基础域名本身不匹配' => ['example.com', 'example.com', false],
            '多级子域名不匹配' => ['a.b.example.com', 'example.com', false],
            '不同域名不匹配' => ['sub.other.com', 'example.com', false],
            '通配符域名不匹配' => ['*.example.com', 'example.com', false],
        ];
    }

    // ==================== removeGiftDomain ====================

    #[DataProvider('removeGiftDomainProvider')]
    public function test_remove_gift_domain(string $domains, string $expected): void
    {
        $result = DomainUtil::removeGiftDomain($domains);
        // 排序后比较，因为顺序可能不同
        $resultArray = array_filter(explode(',', $result));
        $expectedArray = array_filter(explode(',', $expected));
        sort($resultArray);
        sort($expectedArray);
        $this->assertEquals($expectedArray, $resultArray);
    }

    public static function removeGiftDomainProvider(): array
    {
        return [
            '通配符移除一级子域名' => ['*.example.com,www.example.com,sub.example.com', '*.example.com'],
            '通配符移除基础域名' => ['*.example.com,example.com', '*.example.com'],
            '保留多级子域名' => ['*.example.com,a.b.example.com', '*.example.com,a.b.example.com'],
            '保留www版本' => ['example.com,www.example.com', 'www.example.com'],
            '无通配符保留所有' => ['example.com,sub.example.com', 'example.com,sub.example.com'],
            '多个通配符' => ['*.a.com,*.b.com,www.a.com,www.b.com', '*.a.com,*.b.com'],
        ];
    }

    // ==================== addGiftDomain ====================

    #[DataProvider('addGiftDomainProvider')]
    public function test_add_gift_domain(string $domains, string $expected): void
    {
        $result = DomainUtil::addGiftDomain($domains);
        $resultArray = array_filter(explode(',', $result));
        $expectedArray = array_filter(explode(',', $expected));
        sort($resultArray);
        sort($expectedArray);
        $this->assertEquals($expectedArray, $resultArray);
    }

    public static function addGiftDomainProvider(): array
    {
        return [
            '根域名补齐www' => ['example.com', 'example.com,www.example.com'],
            'www补齐根域名' => ['www.example.com', 'example.com,www.example.com'],
            '通配符补齐基础域名' => ['*.example.com', '*.example.com,example.com'],
            '子域名不补齐' => ['sub.example.com', 'sub.example.com'],
            '已有不重复' => ['example.com,www.example.com', 'example.com,www.example.com'],
        ];
    }

    // ==================== removeWildcardBaseDomains ====================

    public function test_remove_wildcard_base_domains(): void
    {
        $domains = ['example.com', 'www.example.com', 'other.com'];
        $wildcards = ['*.example.com'];

        $result = DomainUtil::removeWildcardBaseDomains($domains, $wildcards);

        $this->assertEquals(['other.com'], array_values($result));
    }

    // ==================== removeWildcardMatchedDomains ====================

    public function test_remove_wildcard_matched_domains(): void
    {
        $domains = ['www.example.com', 'sub.example.com', 'a.b.example.com', 'other.com'];
        $wildcards = ['*.example.com'];

        $result = DomainUtil::removeWildcardMatchedDomains($domains, $wildcards);

        $this->assertEquals(['a.b.example.com', 'other.com'], array_values($result));
    }

    // ==================== 边界情况 ====================

    public function test_empty_domains_input_remove(): void
    {
        // 空输入返回空结果
        $removeResult = DomainUtil::removeGiftDomain('');
        $this->assertEmpty(array_filter(explode(',', $removeResult)));
    }

    public function test_single_empty_string_add(): void
    {
        // addGiftDomain 处理空字符串时会尝试获取根域名
        // 这个测试验证不会抛出异常
        $addResult = DomainUtil::addGiftDomain('');
        $this->assertIsString($addResult);
    }

    public function test_domains_with_spaces(): void
    {
        $result = DomainUtil::removeGiftDomain(' example.com , www.example.com ');
        $this->assertStringContainsString('example.com', $result);
    }
}
