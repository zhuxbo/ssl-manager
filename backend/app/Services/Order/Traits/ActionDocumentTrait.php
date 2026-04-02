<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\OrderDocument;
use App\Models\OrderVerificationReport;
use App\Services\Order\Utils\FindUtil;
use App\Traits\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ActionDocumentTrait
{
    use ApiResponse;

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xades'];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    private const DOCUMENT_TYPES = ['APPLICANT', 'ORGANIZATION', 'AUTHORIZATION', 'ADDITIONAL'];

    /**
     * 上传文档（文件方式）
     */
    public function uploadDocument(int $orderId, UploadedFile $file, string $type, string $uploadedBy): void
    {
        $order = FindUtil::Order($orderId);

        in_array($type, self::DOCUMENT_TYPES) || $this->error('不支持的文档类型');
        $file->getSize() > self::MAX_FILE_SIZE && $this->error('文件大小不能超过 5MB');

        $ext = strtolower($file->getClientOriginalExtension());
        in_array($ext, self::ALLOWED_EXTENSIONS) || $this->error('不支持的文件格式，仅支持 PDF/JPG/PNG/DOC/DOCX/XADES');

        $fileName = basename($file->getClientOriginalName());
        $storageName = Str::uuid().".$ext";
        $relativePath = "verification/$orderId/$storageName";

        $file->storeAs("verification/$orderId", $storageName, 'local');

        OrderDocument::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'type' => $type,
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);

        $this->success();
    }

    /**
     * 上传文档（base64 方式，用于 V2 API 接收）
     */
    public function uploadDocumentFromBase64(int $orderId, string $type, string $fileName, string $base64Content): void
    {
        $order = FindUtil::Order($orderId);

        in_array($type, self::DOCUMENT_TYPES) || $this->error('不支持的文档类型');

        $content = base64_decode($base64Content, true);
        $content === false && $this->error('base64 解码失败');
        strlen($content) > self::MAX_FILE_SIZE && $this->error('文件大小不能超过 5MB');

        $fileName = basename($fileName);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        in_array($ext, self::ALLOWED_EXTENSIONS) || $this->error('不支持的文件格式');

        $storageName = Str::uuid().".$ext";
        $relativePath = "verification/$orderId/$storageName";
        $fullPath = storage_path("app/$relativePath");

        $dir = dirname($fullPath);
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents($fullPath, $content);

        OrderDocument::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'type' => $type,
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'file_size' => strlen($content),
            'uploaded_by' => 'api',
        ]);

        $this->success();
    }

    /**
     * 获取文档列表
     */
    public function getDocuments(int $orderId): void
    {
        FindUtil::Order($orderId);

        $documents = OrderDocument::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();

        $this->success($documents->toArray());
    }

    /**
     * 预览文档
     */
    public function previewDocument(int $docId): BinaryFileResponse
    {
        $document = OrderDocument::find($docId);
        ! $document && $this->error('文档不存在');

        $fullPath = storage_path("app/$document->file_path");
        ! file_exists($fullPath) && $this->error('文件不存在');

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xades' => 'application/xml',
        ];

        $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        $safeName = rawurlencode(basename($document->file_name));

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename*=UTF-8''$safeName",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * 更新文档信息
     */
    public function updateDocument(int $docId, string $fileName, string $type): void
    {
        $document = OrderDocument::findOrFail($docId);
        $document->update([
            'file_name' => $fileName,
            'type' => $type,
        ]);
        $this->success();
    }

    /**
     * 删除文档
     */
    public function deleteDocument(int $docId): void
    {
        $document = OrderDocument::find($docId);
        ! $document && $this->error('文档不存在');

        $fullPath = storage_path("app/$document->file_path");
        file_exists($fullPath) && unlink($fullPath);

        $document->delete();
        $this->success();
    }

    /**
     * 提交文档到上游
     */
    public function submitDocuments(int $orderId): void
    {
        $order = FindUtil::Order($orderId);
        $apiId = $order->latestCert->api_id;
        ! $apiId && $this->error('订单尚未提交到上游，无法提交文档');

        $documents = OrderDocument::where('order_id', $orderId)
            ->where('submitted', 0)
            ->get();

        $documents->isEmpty() && $this->error('没有待提交的文档');

        $failed = [];
        foreach ($documents as $document) {
            $fullPath = storage_path("app/$document->file_path");
            if (! file_exists($fullPath)) {
                $failed[] = ['file_name' => $document->file_name, 'msg' => '文件不存在'];

                continue;
            }

            $base64Content = base64_encode(file_get_contents($fullPath));

            try {
                $result = $this->api->uploadDocument($orderId, [
                    'order_id' => $apiId,
                    'type' => $document->type,
                    'fileName' => $document->file_name,
                    'document_content' => $base64Content,
                ]);

                if (($result['code'] ?? 0) === 1) {
                    $document->update(['submitted' => 1]);
                } else {
                    $failed[] = ['file_name' => $document->file_name, 'msg' => $result['msg'] ?? '提交失败'];
                }
            } catch (\Throwable $e) {
                $failed[] = ['file_name' => $document->file_name, 'msg' => $e->getMessage()];
            }
        }

        ! empty($failed) && $this->error('部分文档提交失败', $failed);
        $this->success();
    }

    /**
     * 获取验证报告
     */
    public function getVerificationReport(int $orderId): void
    {
        $order = FindUtil::Order($orderId);
        $order->load(['product:id,validation_type', 'latestCert:id,common_name']);

        $report = OrderVerificationReport::where('order_id', $orderId)->first();

        // 尝试从联系人模型获取证件号码
        $contact = $order->contact ?? [];
        if (! empty($contact['email'])) {
            $contactModel = \App\Models\Contact::where('user_id', $order->user_id)
                ->where('email', $contact['email'])
                ->first();
            if ($contactModel?->identification_number) {
                $contact['identification_number'] = $contactModel->identification_number;
            }
        }

        $this->success([
            'report' => $report?->toArray(),
            'prefill' => [
                'organization' => $order->organization,
                'contact' => $contact,
            ],
        ]);
    }

    /**
     * 保存验证报告
     */
    public function saveVerificationReport(int $orderId, array $reportData): void
    {
        $order = FindUtil::Order($orderId);
        $reportData = $this->trimRecursive($reportData);

        OrderVerificationReport::updateOrCreate(
            ['order_id' => $orderId],
            ['user_id' => $order->user_id, 'report_data' => $reportData, 'submitted' => 0]
        );

        $this->success();
    }

    /**
     * 提交验证报告到上游
     */
    public function submitVerificationReport(int $orderId): void
    {
        $order = FindUtil::Order($orderId);
        $apiId = $order->latestCert->api_id;
        ! $apiId && $this->error('订单尚未提交到上游，无法提交验证报告');

        $report = OrderVerificationReport::where('order_id', $orderId)->first();
        ! $report && $this->error('验证报告不存在');

        $result = $this->api->submitVerificationReport($orderId, [
            'order_id' => $apiId,
            'report_data' => $report->report_data,
        ]);

        if (($result['code'] ?? 0) !== 1) {
            $this->error($result['msg'] ?? '提交失败');
        }

        $report->update(['submitted' => 1]);
        $this->success();
    }

    private function trimRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->trimRecursive($value);
            }
        }

        return $data;
    }
}
