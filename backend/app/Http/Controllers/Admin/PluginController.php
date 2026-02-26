<?php

namespace App\Http\Controllers\Admin;

use App\Services\Plugin\PluginManager;
use Illuminate\Http\Request;

class PluginController extends BaseController
{
    public function __construct(
        protected PluginManager $pluginManager,
    ) {
        parent::__construct();
    }

    /**
     * 已安装插件列表
     */
    public function installed(): void
    {
        $plugins = $this->pluginManager->getInstalledPlugins();

        $this->success(['plugins' => $plugins]);
    }

    /**
     * 检查所有插件更新
     */
    public function checkUpdates(): void
    {
        $updates = $this->pluginManager->checkUpdates();

        $this->success(['updates' => $updates]);
    }

    /**
     * 安装插件（远程安装或上传安装）
     */
    public function install(Request $request): void
    {
        // 上传安装
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            if ($file->getSize() > 100 * 1024 * 1024) {
                $this->error('文件大小超过限制（最大 100MB）');
            }

            if ($file->getClientOriginalExtension() !== 'zip') {
                $this->error('仅支持 ZIP 格式');
            }

            $zipPath = $file->store('', ['disk' => 'local']);
            $fullPath = storage_path("app/$zipPath");

            try {
                $result = $this->pluginManager->installFromZip($fullPath);
                $this->success($result);
            } finally {
                @unlink($fullPath);
            }
        }

        // 远程安装
        $name = $request->input('name');
        if (! $name) {
            $this->error('请指定插件名称或上传 ZIP 文件');
        }

        $releaseUrl = $request->input('release_url');
        $version = $request->input('version');

        $result = $this->pluginManager->install($name, $releaseUrl, $version);

        $this->success($result);
    }

    /**
     * 更新插件
     */
    public function update(Request $request): void
    {
        $name = $request->input('name');
        if (! $name) {
            $this->error('请指定插件名称');
        }

        $version = $request->input('version');
        $result = $this->pluginManager->update($name, $version);

        $this->success($result);
    }

    /**
     * 卸载插件
     */
    public function uninstall(Request $request): void
    {
        $name = $request->input('name');
        if (! $name) {
            $this->error('请指定插件名称');
        }

        $removeData = (bool) $request->input('remove_data', false);
        $result = $this->pluginManager->uninstall($name, $removeData);

        $this->success($result);
    }
}
