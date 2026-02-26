<?php

namespace App\Http\Controllers;

class PluginManifestController extends Controller
{
    /**
     * 公共端点：返回 bundle 路径供前端加载
     */
    public function index(): void
    {
        $manifests = app('plugin.public_manifests');

        $this->success(['plugins' => $manifests]);
    }

    /**
     * 管理端点：返回完整插件信息
     */
    public function admin(): void
    {
        $manifests = app('plugin.admin_manifests');

        $this->success(['plugins' => $manifests]);
    }
}
