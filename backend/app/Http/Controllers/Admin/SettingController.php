<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Setting\GetIdsRequest;
use App\Http\Requests\Setting\StoreRequest;
use App\Http\Requests\Setting\UpdateRequest;
use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Support\Facades\Config;

class SettingController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取当前设置配置
     */
    public function getConfig(): void
    {
        $config = [
            'locked' => Config::get('settings.locked', false),
        ];

        $this->success($config);
    }

    /**
     * 获取所有设置
     */
    public function index(): void
    {
        // 获取所有设置组及其设置项
        $groups = SettingGroup::with(['settings' => function ($query) {
            $query->orderBy('weight', 'asc')->orderBy('id', 'asc');
        }])
            ->orderBy('weight')
            ->orderBy('id')
            ->get();

        $this->success([
            'groups' => $groups,
        ]);
    }

    /**
     * 获取指定组的设置项
     */
    public function getByGroup($groupId): void
    {
        $group = SettingGroup::with(['settings' => function ($query) {
            $query->orderBy('weight', 'asc')->orderBy('id', 'asc');
        }])->find($groupId);

        if (! $group) {
            $this->error('设置组不存在');
        }

        $this->success([
            'group' => $group,
        ]);
    }

    /**
     * 添加设置
     */
    public function store(StoreRequest $request): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止添加新设置');
        }

        $setting = Setting::create($request->validated());

        if (! $setting->exists) {
            $this->error('添加失败');
        }

        $this->success(['id' => $setting->id]);
    }

    /**
     * 获取设置资料
     */
    public function show($id): void
    {
        $setting = Setting::find($id);
        if (! $setting) {
            $this->error('设置不存在');
        }

        $this->success($setting->toArray());
    }

    /**
     * 批量获取设置资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $settings = Setting::whereIn('id', $ids)->get();
        if ($settings->isEmpty()) {
            $this->error('设置不存在');
        }

        $this->success($settings->toArray());
    }

    /**
     * 批量更新设置
     */
    public function batchUpdate(UpdateRequest $request): void
    {
        $settings = $request->validated('settings');

        if (! is_array($settings) || empty($settings)) {
            $this->error('设置数据不能为空');
        }

        foreach ($settings as $settingData) {
            if (! isset($settingData['id']) || ! isset($settingData['value'])) {
                continue;
            }

            $setting = Setting::find($settingData['id']);
            if (! $setting) {
                continue;
            }

            // 只更新值字段
            $setting->value = $settingData['value'];
            $setting->save();
        }

        $this->success();
    }

    /**
     * 更新设置资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $setting = Setting::find($id);
        if (! $setting) {
            $this->error('设置不存在');
        }

        // 如果设置已锁定，只允许更新value字段
        if (Config::get('settings.locked', false)) {
            $validated = $request->validated();
            $setting->value = $validated['value'] ?? $setting->value;
            $setting->save();
            $this->success();
        }

        $setting->fill($request->validated());
        $setting->save();

        $this->success();
    }

    /**
     * 删除设置
     */
    public function destroy($id): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止删除设置');
        }

        $setting = Setting::find($id);
        if (! $setting) {
            $this->error('设置不存在');
        }

        $setting->delete();
        $this->success();
    }

    /**
     * 批量删除设置
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止删除设置');
        }

        $ids = $request->validated('ids');

        $settings = Setting::whereIn('id', $ids)->get();
        if ($settings->isEmpty()) {
            $this->error('设置不存在');
        }

        Setting::destroy($ids);
        $this->success();
    }

    /**
     * 清除所有设置缓存
     */
    public function clearCache(): void
    {
        Setting::clearAllCache();
        $this->success();
    }
}
