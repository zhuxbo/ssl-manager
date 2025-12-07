<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\File;
use ZipArchive;

trait ActionFileTrait
{
    use ApiResponse;

    /**
     * 批量下载证书
     */
    public function download(int|string|array $orderIds, string $type = 'all'): void
    {
        $type = in_array($type, ['all', 'apache', 'nginx', 'pem', 'iis', 'tomcat', 'txt']) ? $type : 'all';
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $orders = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'active'))
            ->whereIn('id', $orderIds)
            ->get();

        // 如果中间证书不存在，则过滤掉
        $orders = $orders->filter(function ($order) {
            return ! empty($order->latestCert->intermediate_cert);
        });

        // 如果为空则退出 因为在下载流程中 不能用 error 返回
        if ($orders->isEmpty()) {
            exit;
        }

        $random = sprintf('%04x%04x', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));
        $tempDir = storage_path('temp-certs/'.$random);

        mkdir($tempDir, 0755, true);

        $zip = new ZipArchive;
        $suffix = $type == 'all' ? '' : '_'.$type;
        $filename = count($orders) == 1
            ? str_replace('*', 'STAR', $orders[0]->latestCert->common_name).$suffix.'.zip'
            : 'certs-'.count($orders).'-'.$random.$suffix.'.zip';

        $zip->open($tempDir.'/'.$filename, ZipArchive::CREATE);

        $commonNames = [];
        foreach ($orders as $order) {
            $this->addCertToZip($order, $zip, $tempDir, $commonNames, $type);
            $commonNames[] = $order->latestCert->common_name;
        }

        $zip->close();

        $this->downFlow($tempDir.'/'.$filename, $tempDir);
    }

    /**
     * 下载验证文件
     */
    public function downloadValidateFile(int $orderId): void
    {
        $order = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->whereIn('status', ['unpaid', 'pending', 'processing']))
            ->where('id', $orderId)
            ->first();
        if (empty($order->latestCert->dcv['file'])) {
            $this->error('没有可以下载的验证文件');
        }

        $file = $order->latestCert->dcv['file'];

        if ($file) {
            $random = sprintf('%04x%04x', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));
            $tempDir = storage_path('temp-certs/'.$random);
            mkdir($tempDir, 0755, true);

            $zip = new ZipArchive;
            $filename = '订单'.$orderId.'-请放到网站根目录解压.zip';
            $zip->open($tempDir.'/'.$filename, ZipArchive::CREATE);
            $zip->addFromString('.well-known/pki-validation/'.($file['name'] ?? 'error.txt'), $file['content'] ?? '');
            $zip->close();

            $this->downFlow($tempDir.'/'.$filename, $tempDir);
        }
    }

    /**
     * 添加证书文件到zip
     */
    protected function addCertToZip(
        Order $order,
        ZipArchive $zip,
        string $tempDir,
        array $domains = [],
        string $type = 'all'
    ): void {
        $commonName = $order->latestCert->common_name ?? '';
        $cert = $order->latestCert->cert ?? '';
        $privateKey = $order->latestCert->private_key ?? '';
        $intermediateCert = $order->latestCert->intermediate_cert ?? '';

        $certName = str_replace('*', 'STAR', $commonName);
        $random = sprintf('%04x%04x', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));
        $certPath = in_array($commonName, $domains) ? $certName.'-'.$random.'/' : $certName.'/';

        $password = '123456';
        $keyMatched = $privateKey && openssl_x509_check_private_key($cert, $privateKey);

        if ($type == 'all' || $type == 'apache') {
            $zip->addFromString($certPath.'apache/'.$certName.'.crt', $cert);
            $zip->addFromString($certPath.'apache/'.$certName.'-ca-bundle.crt', $intermediateCert);
            $keyMatched && $zip->addFromString($certPath.'apache/'.$certName.'.key', $privateKey);
        }

        if ($type == 'all' || $type == 'nginx') {
            $zip->addFromString($certPath.'nginx/'.$certName.'.crt', $cert.PHP_EOL.$intermediateCert);
            $keyMatched && $zip->addFromString($certPath.'nginx/'.$certName.'.key', $privateKey);
        }

        if ($type == 'all' || $type == 'pem') {
            $zip->addFromString($certPath.'pem/'.$certName.'.pem', $cert.PHP_EOL.$intermediateCert);
            $keyMatched && $zip->addFromString($certPath.'pem/'.$certName.'.key', $privateKey);
        }

        if ($type == 'iis' || $type == 'tomcat') {
            $privateKey || $this->error('私钥不存在');
            $keyMatched || $this->error('私钥与证书不匹配');
        }

        if ($type == 'tomcat' && ! $this->checkJdk()) {
            $this->error('JDK 未安装');
        }

        if (($type == 'all' || $type == 'iis' || $type == 'tomcat') && $privateKey && $keyMatched) {
            $chain = array_filter(explode('-----END CERTIFICATE-----', trim($intermediateCert)));

            $args['extracerts'] = [];
            foreach ($chain as $k => $v) {
                $args['extracerts'][$k] = $v.'-----END CERTIFICATE-----';
            }
            $args['friendly_name'] = $commonName;

            $pfx = $tempDir.'/temp.pfx';
            openssl_pkcs12_export_to_file($cert, $pfx, $privateKey, $password, $args);

            if ($type == 'all' || $type == 'iis') {
                $zip->addFile($pfx, $certPath.'iis/'.$certName.'.pfx');
                $zip->addFromString($certPath.'iis/password.txt', $password);
            }

            if (($type == 'all' || $type == 'tomcat') && $this->checkJdk()) {
                $jks = $tempDir.'/temp.jks';
                $cmd = 'keytool -importkeystore -srckeystore '.escapeshellarg($pfx)." -srcstoretype PKCS12 -srcstorepass $password -deststoretype jks -deststorepass $password -destkeystore ".escapeshellarg($jks);
                @exec("$cmd > /dev/null 2>&1");
                $zip->addFile($jks, $certPath.'tomcat/'.$certName.'.jks');
                $zip->addFromString($certPath.'tomcat/password.txt', $password);
            }
        }

        if ($type == 'all' || $type == 'txt') {
            $zip->addFromString($certPath.'txt/nginx/'.$certName.'.crt.txt', $cert.PHP_EOL.$intermediateCert);
            $keyMatched && $zip->addFromString($certPath.'txt/nginx/'.$certName.'.key.txt', $privateKey);
            $zip->addFromString($certPath.'txt/apache/'.$certName.'.crt.txt', $cert);
            $zip->addFromString($certPath.'txt/apache/'.$certName.'-ca-bundle.crt.txt', $intermediateCert);
            $keyMatched && $zip->addFromString($certPath.'txt/apache/'.$certName.'.key.txt', $privateKey);
        }

        // 生成 RSA 传统格式私钥，兼容不同 OpenSSL 版本
        if ($type == 'all' && $keyMatched) {
            $keyDetails = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
            if (isset($keyDetails['type']) && ($keyDetails['type'] === OPENSSL_KEYTYPE_RSA)) {
                $key = $tempDir.'/'.$certName.'.key';
                $keyFile = fopen($key, 'w', true);
                fwrite($keyFile, $privateKey);
                fclose($keyFile);
                $rsaKey = $tempDir.'/'.$certName.'-rsa.key';

                // 首先尝试使用 -traditional 参数
                $cmd = 'openssl pkcs8 -in '.escapeshellarg($key).' -out '.escapeshellarg($rsaKey).' -nocrypt -traditional';
                @exec("$cmd 2>&1", $output, $returnCode);

                // 如果 -traditional 参数失败，使用 RSA 命令转换
                if ($returnCode !== 0) {
                    $cmd = 'openssl rsa -in '.escapeshellarg($key).' -out '.escapeshellarg($rsaKey).' -traditional';
                    @exec("$cmd > /dev/null 2>&1", $output, $returnCode);
                }

                // 只有转换成功才添加到zip
                if ($returnCode === 0 && file_exists($rsaKey)) {
                    $zip->addFile($rsaKey, $certPath.'rsa_key/'.$certName.'-rsa.key');
                }
            }
        }
    }

    /**
     * 下载流
     */
    private function downFlow(string $zipFile, string $tempDir): void
    {
        if (! file_exists($zipFile)) {
            $this->error('下载失败');
        }

        // 获取文件名
        $filename = basename($zipFile);

        // 手动设置所有头信息，包括跨域支持
        $origin = request()->header('Origin');
        if ($origin) {
            header('Access-Control-Allow-Origin: '.$origin);
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        header('Access-Control-Expose-Headers: Content-Disposition');

        // 文件下载头信息
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.urlencode($filename).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: '.filesize($zipFile));

        // 清理输出缓冲并发送文件
        ob_clean();
        flush();
        readfile($zipFile);

        // 清理临时目录
        File::deleteDirectory($tempDir);

        exit;
    }

    /**
     * 检测jdk
     */
    private function checkJdk(): bool
    {
        // java -version 输出到 stderr，需要重定向到 stdout
        @exec('java -version 2>&1', $output, $returnCode);

        // 检查命令执行是否成功（返回码为0表示成功）
        if ($returnCode !== 0) {
            return false;
        }

        // 检查输出中是否包含版本信息
        $versionOutput = implode(' ', $output);
        if (empty($versionOutput) || ! preg_match('/version\s+["\']?(\d+[.\d]*)/i', $versionOutput)) {
            return false;
        }

        return true;
    }
}
