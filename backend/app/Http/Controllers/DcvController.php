<?php

namespace App\Http\Controllers;

use App\Services\Delegation\DnsResolver;
use App\Services\Order\Utils\DomainUtil;
use App\Services\Order\Utils\VerifyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** 浏览器外部检测节点不可用时的本地回落，不再次请求 dnsTools。 */
class DcvController extends Controller
{
    private const DNS_NAME = '/\A(?=.{1,253}\z)[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+\.?\z/';

    public function query(Request $request, DnsResolver $resolver): never
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'type' => ['required', 'in:TXT,CNAME'],
        ]);
        $host = $this->dnsHost($data['domain']);
        $records = $resolver->queryRecords($host, $data['type']);
        if ($records === null) {
            $this->error('本地 DNS 检测服务不可用');
        }
        $this->success(['records' => $records]);
    }

    public function verify(Request $request, DnsResolver $resolver): never
    {
        $data = Validator::make(['items' => $request->json()->all()], [
            'items' => ['required', 'array', 'list', 'min:1', 'max:1'],
            'items.*' => ['required', 'array:domain,method,host,value,link,name,content'],
            'items.*.domain' => ['required', 'string', 'max:253'],
            'items.*.method' => ['required', 'in:txt,cname,file,http,https,email,admin'],
            'items.*.host' => ['nullable', 'string', 'max:253'],
            'items.*.value' => ['nullable', 'string', 'max:8192'],
            'items.*.link' => ['nullable', 'string', 'max:2048'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.content' => ['nullable', 'string', 'max:8192'],
        ])->validate();

        $results = [];
        foreach ($data['items'] as $item) {
            $domain = $item['domain'];
            $method = $item['method'];
            $result = ['domain' => $domain, 'matched' => 'unknown', 'value' => '', 'error' => '此验证方式需由 CA 确认'];
            if (in_array($method, ['txt', 'cname'], true)) {
                $host = $item['host'] ?? '@';
                $zone = preg_replace('/^\*\./', '', $domain);
                $host = $host === '@' ? $zone : (str_contains($host, '.') ? $host : "$host.$zone");
                $host = $this->dnsHost($host);
                $records = $resolver->queryRecords($host, strtoupper($method));
                $result['query'] = $host;
                $result['error'] = '本地 DNS 检测服务不可用';
                if ($records !== null) {
                    $values = array_column($records, 'value');
                    $expected = trim($item['value'] ?? '');
                    $normalize = $method === 'cname'
                        ? fn (string $value): string => strtolower(rtrim(DomainUtil::convertToAscii(trim($value)), '.'))
                        : fn (string $value): string => trim($value);
                    $matched = $expected !== '' && in_array($normalize($expected), array_map($normalize, $values), true);
                    $result['value'] = implode(', ', $values);
                    $result['matched'] = $matched ? 'true' : 'false';
                    $result['error'] = $matched ? '' : '验证未通过';
                }
            } elseif (in_array($method, ['file', 'http', 'https'], true)) {
                // 公开回落接口只接受 DCV 文件路径；公网、端口、重定向及大小限制复用原检测。
                $path = parse_url($item['link'] ?? '', PHP_URL_PATH);
                if (! is_string($path) || ! preg_match('~\A/\.well-known/pki-validation/[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z~', $path)) {
                    $this->error('验证文件路径不合法');
                }
                $matched = VerifyUtil::verifyFileValidationLocal($item, $method);
                $result['matched'] = $matched ? 'true' : 'false';
                $result['content'] = $matched ? ($item['content'] ?? '') : '';
                $result['link'] = $item['link'];
                $result['error'] = $matched ? '' : '验证文件未匹配或无法访问';
            }
            $results[$domain] = $result;
        }

        $this->success(['results' => $results]);
    }

    private function dnsHost(string $host): string
    {
        $host = DomainUtil::convertToAscii($host);
        if (! preg_match(self::DNS_NAME, $host)) {
            $this->error('DNS 查询主机格式错误');
        }

        return $host;
    }
}
