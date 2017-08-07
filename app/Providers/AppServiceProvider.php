<?php

namespace App\Providers;

use App\Repositories\Document;
use App\Repositories\Group;
use App\Repositories\Project;
use App\Repositories\Template;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->addProjectExistRules('project_exist');
        $this->addPageExistRules('page_exist');
        $this->addTemplateUniqueRules('template_unique');
        $this->addGroupExistRule('group_exist');

        // 在日志中输出sql历史
        \DB::listen(function (QueryExecuted $query) {
            \Log::debug('sql_execute', [
                'sql'   => $query->sql,
                'binds' => $query->bindings,
            ]);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * 检查页面是否存在
     *
     * 参数：项目ID,是否验证0值
     *
     * @param string $ruleName
     */
    private function addPageExistRules(string $ruleName)
    {
        $this->registerValidationRule(
            $ruleName,
            '参数 %s 对应的页面不存在',
            function ($attribute, $value, $parameters, $validator) {
                $projectID         = $parameters[0] ?? 0;
                $validateZeroValue = isset($parameters[1]) ? ($parameters[1] == 'true') : true;

                if (empty($value)) {
                    return !$validateZeroValue;
                }

                $conditions   = [];
                $conditions[] = ['id', $value];

                if (!empty($projectID)) {
                    $conditions[] = ['project_id', $projectID];
                }

                return Document::where($conditions)->exists();
            }
        );
    }

    /**
     * 添加检查项目是否存在的规则
     *
     * @param string $ruleName
     */
    private function addProjectExistRules(string $ruleName)
    {
        $this->registerValidationRule(
            $ruleName,
            '参数 %s 对应的项目不存在',
            function ($attribute, $value, $parameters, $validator) {
                return Project::where('id', $value)->exists();
            }
        );
    }

    /**
     * 添加检查分组名称是否存在的规则
     *
     * @param string $ruleName
     */
    private function addGroupExistRule(string $ruleName)
    {
        $this->registerValidationRule(
            $ruleName,
            '参数 %s 对应的用户组不存在',
            function ($attribute, $value, $parameters, $validator) {
                return Group::where('id', $value)->exists();
            }
        );
    }

    /**
     * 模板名称是否唯一
     *
     * @param string $ruleName
     */
    private function addTemplateUniqueRules(string $ruleName)
    {
        $this->registerValidationRule(
            $ruleName,
            '模板名称已经存在',
            function ($attribute, $value, $parameters, $validator) {
                return !Template::where('name', $value)->exists();
            }
        );
    }

    /**
     * 注册校验规则
     *
     * @param string   $ruleName
     * @param string   $validationMessage
     * @param \Closure $callback
     */
    private function registerValidationRule(
        string $ruleName,
        string $validationMessage,
        \Closure $callback
    ) {
        \Validator::extend($ruleName, $callback);
        \Validator::replacer($ruleName,
            function ($message, $attribute, $rule, $parameters) use (
                $ruleName,
                $validationMessage
            ) {
                if ($message == "validation.{$ruleName}") {
                    return sprintf($validationMessage, $attribute);
                }

                return $message;
            });
    }
}