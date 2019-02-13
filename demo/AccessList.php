<?php

namespace common\models;

use common\overrides\web\ScealException;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Class AccessList
 * @package common\models
 */
class AccessList extends ActiveRecord
{
    const REPORT_TYPE = 'report';
    const REPORT_SECTION_TYPE = 'report_section';
    const REPORT_PAGE_TYPE = 'report_page';
    const REPORT_COMPONENT_TYPE = 'report_component';
    const METRIC_TYPE = 'metric';
    const TASK_TYPE = 'task';
    const METRIC_VIEW_TYPE = 'metric_view';
    const COMMENT_TYPE = 'comment';
    const EVIDENCE_TYPE = 'evidence';

    const TYPES = [
        self::REPORT_TYPE,
        self::REPORT_SECTION_TYPE,
        self::REPORT_PAGE_TYPE,
        self::REPORT_COMPONENT_TYPE,
        self::METRIC_TYPE,
        self::TASK_TYPE,
        self::METRIC_VIEW_TYPE,
        self::COMMENT_TYPE,
        self::EVIDENCE_TYPE
    ];

    const ACTION_UPDATE = 'update_action';
    const ACTION_CREATE = 'create_action';
    const ACTION_DELETE = 'delete_action';

    const ACTIONS_ADMIN = [
        self::ACTION_CREATE,
        self::ACTION_DELETE,
        self::ACTION_UPDATE
    ];

    /**
     * @param $userId
     * @param $object
     * @param $roleId
     * @param $parentId
     * @param null $revisionId
     * @param null $objectRoleId
     * @internal param $revisionId
     */
    public static function grant($userId, $object, $roleId, $parentId = null, $revisionId = null, $objectRoleId = null)
    {
        if ($object instanceof Report) {
            static::grantReport($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof ReportSection) {
            static::grantReportSection($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof ReportPage) {
            static::grantReportPage($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof ReportComponent) {
            static::grantReportComponent($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof Task) {
            static::grantTask($userId, $object, $roleId, $parentId, false, $revisionId, $objectRoleId);
        } elseif ($object instanceof Metric) {
            static::grantMetric($userId, $object, $roleId, $parentId);
        } elseif ($object instanceof MetricViewTemplate) {
            static::grantMetricView($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof Comment) {
            static::grantComment($userId, $object, $roleId, $parentId, false, $revisionId);
        } elseif ($object instanceof Evidence) {
            static::grantEvidence($userId, $object, $roleId, $parentId, false, $revisionId);
        } else {
            throw new ScealException(ScealException::BAD_DATA, 'wrong object');
        }
    }

    /**
     * @param $userId
     * @param Report $report
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     */
    private static function grantReport(
        $userId,
        Report $report,
        $roleId,
        $parentId = null,
        $dependant = false,
        $revisionId = null
    ) {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::REPORT_TYPE,
            'object_id' => $report->id,
            'parent_id' => $parentId,
            'revision_id' => $revisionId
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::REPORT_TYPE,
                'object_id' => $report->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
                'revision_id' => $revisionId
            ]);
        } else {
            $currentAccess->role_id=$roleId;
        }
        $currentAccess->save();

        if (!$dependant || ($dependant==='task')) {
            if (!empty($report->sections)) {
                foreach ($report->sections as $section) {
                    static::grantReportSection($userId, $section, $roleId, $parentId ? $parentId : $currentAccess->id, true, $revisionId);
                }
            }
            if (!empty($report->page)) {
                foreach ($report->page as $page) {
                    static::grantReportPage($userId, $page, $roleId, $parentId ? $parentId : $currentAccess->id, true, $revisionId);
                }
            }
            if (!empty($report->components)) {
                foreach ($report->components as $component) {
                    static::grantReportComponent($userId, $component, $roleId, $parentId ? $parentId : $currentAccess->id, true, $revisionId);
                }
            }
        }
    }

    /**
     * @param $userId
     * @param ReportSection $reportSection
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     */
    private static function grantReportSection(
        $userId,
        ReportSection $reportSection,
        $roleId,
        $parentId = null,
        $dependant = false,
        $revisionId = null
    ) {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::REPORT_SECTION_TYPE,
            'object_id' => $reportSection->id,
            'parent_id' => $parentId,
            'revision_id' => $revisionId
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::REPORT_SECTION_TYPE,
                'object_id' => $reportSection->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
                'revision_id' => $revisionId
            ]);
        } else {
            $currentAccess->role_id = $roleId;
        }
        $currentAccess->save();

        if (!$dependant || $dependant === 'task') {
            static::grantReport(
                $userId,
                $reportSection->report,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $currentAccess->id,
                true,
                $revisionId
            );
            if (!empty($reportSection->pages)) {
                foreach ($reportSection->pages as $page) {
                    static::grantReportPage(
                        $userId,
                        $page,
                        $roleId,
                        $parentId ? $parentId : $currentAccess->id,
                        true,
                        $revisionId
                    );
                }
            }
        }
    }


    /**
     * @param $userId
     * @param ReportPage $reportPage
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     */
    private static function grantReportPage(
        $userId,
        ReportPage $reportPage,
        $roleId,
        $parentId = null,
        $dependant = false,
        $revisionId = null
    ) {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::REPORT_PAGE_TYPE,
            'object_id' => $reportPage->id,
            'parent_id' => $parentId,
            'revision_id' => $revisionId
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::REPORT_PAGE_TYPE,
                'object_id' => $reportPage->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
                'revision_id' => $revisionId
            ]);
        } else {
            $currentAccess->role_id=$roleId;
        }
        $currentAccess->save();

        if (!$dependant || $dependant === 'task') {
            static::grantReport(
                $userId,
                $reportPage->report,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $parentId ? $parentId : $currentAccess->id,
                true,
                $revisionId
            );
            static::grantReportSection(
                $userId,
                $reportPage->reportSection,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $parentId ? $parentId : $currentAccess->id,
                true,
                $revisionId
            );
            if (!empty($reportPage->components)) {
                foreach ($reportPage->components as $component) {
                    static::grantReportComponent(
                        $userId,
                        $component,
                        $roleId,
                        $parentId ? $parentId : $currentAccess->id,
                        true,
                        $revisionId
                    );
                }
            }
        }
    }

    /**
     * @param $userId
     * @param ReportComponent $reportComponent
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param $revisionId
     */
    private static function grantReportComponent(
        $userId,
        ReportComponent $reportComponent,
        $roleId,
        $parentId = null,
        $dependant = false,
        $revisionId = null
    ) {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::REPORT_COMPONENT_TYPE,
            'object_id' => $reportComponent->id,
            'parent_id' => $parentId,
            'revision_id' => $revisionId
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::REPORT_COMPONENT_TYPE,
                'object_id' => $reportComponent->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
                'revision_id' => $revisionId
            ]);
        } else {
            $currentAccess->role_id=$roleId;
        }
        $currentAccess->save();

        if ($reportComponent->component_type === 'metric') {
            if ($reportComponent->metricType != 'metric-grid') {
                if ($reportComponent->metric) {
                    $metricId = $reportComponent->metric->id;
                    $metric = Metric::findOne($metricId);
                    if ($metric) {
                        $where= [
                            'metric_id' => $metricId,
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            'revision_id' => $metric->values_revision_id
                        ];

                        if (!MetricUser::find()->where($where)->exists()) {
                            $metricUser = new MetricUser([
                                'metric_id' => $metricId,
                                'user_id' => $userId,
                                'role_id' => $roleId,
                                'revision_id' => $revisionId ? $metric->values_revision_id : null
                            ]);
                            $metricUser->save();
                        }
                    }
                }
            } else {
                $dataArray = json_decode($reportComponent->options);
                if (is_array($dataArray) && array_key_exists('grid', $dataArray)) {
                    $metricView = MetricViewTemplate::findOne(['name' => $dataArray['grid']['id']]);
                    static::grantMetricView($userId, $metricView, $roleId, $parentId ? $parentId : $currentAccess->id);
                }
            }
        }
        if (!$dependant || $dependant === 'task') {
            $reportPage = ReportPage::findOne($reportComponent->report_page_id);
            $reportSection = ReportSection::findOne($reportPage->report_section_id);
            static::grantReportPage(
                $userId,
                $reportPage,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $parentId ? $parentId : $currentAccess->id,
                true,
                $revisionId
            );
            static::grantReportSection(
                $userId,
                $reportSection,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $parentId ? $parentId : $currentAccess->id,
                true,
                $revisionId
            );
            static::grantReport(
                $userId,
                $reportComponent->report,
                $dependant === 'task' ? Role::TASK_USER : Role::USER,
                $parentId ? $parentId : $currentAccess->id,
                true,
                $revisionId
            );
        }
    }

    /**
     * @param $userId
     * @param MetricViewTemplate $metricView
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     */
    private static function grantMetricView(
        $userId,
        MetricViewTemplate $metricView,
        $roleId,
        $parentId = null,
        $dependant = false,
        $revisionId = null
    ) {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::METRIC_VIEW_TYPE,
            'object_id' => $metricView->id,
            'parent_id' => $parentId,
            'role_id' => $roleId,
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::METRIC_VIEW_TYPE,
                'object_id' => $metricView->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
            ]);
            $currentAccess->save();
        }
        if (!$dependant) {
            $metrics = [];
            $grid = ArrayHelper::toArray($metricView);
            $options = json_decode($grid['options']);
            foreach ($options->grids as $grid) {
                foreach ($grid->metricOptions->grid->layout as $layout) {
                    foreach ($layout as $metricIds) {
                        $metrics[] = $metricIds;
                    }
                }
            }
            $metrics = array_unique($metrics);
            foreach ($metrics as $metricId) {
                $metric = Metric::findOne($metricId);
                static::grantMetric($userId, $metric, $roleId, $currentAccess->id);
            }
        }
    }

    /**
     * @param $userId
     * @param Metric $metric
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     */
    private static function grantMetric($userId, Metric $metric, $roleId, $parentId = null, $dependant = false)
    {
        $currentAccess = MetricUser::findOne([
            'user_id' => $userId,
            'metric_id' => $metric->id
        ]);
        if (null === $currentAccess) {
            $currentAccess = new MetricUser([
                'user_id' => $userId,
                'metric_id' => $metric->id,
                'role_id' => $roleId
            ]);
            $currentAccess->save();
        }
    }

    /**
     * @param $userId
     * @param Task $task
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param $revisionId
     * @param null $roleForObjects
     */
    private static function grantTask($userId, Task $task, $roleId, $parentId = null, $dependant = false, $revisionId = null, $roleForObjects = null)
    {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::TASK_TYPE,
            'object_id' => $task->id,
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::TASK_TYPE,
                'object_id' => $task->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
                'revision_id'=>$revisionId,
            ]);
        } else {
            $revisionId=$currentAccess->revision_id;
            $currentAccess->role_id=$roleId;
        }
        $currentAccess->save();

        if (!$dependant) {
            if ($roleForObjects) {
                $role = $roleForObjects;
            } else {
                if (in_array(
                    mb_strtolower($task->type, 'utf-8'),
                    [Task::TYPE_SEND, Task::TYPE_REVIEW, Task::TYPE_EVIDENCE, Task::TYPE_READ]
                )) {
                    $role = Role::TASK_USER;
                } else {
                    $role = $roleId;
                }
            }
            if (!empty($task->reportComponent)) {
                static::grantReportComponent(
                    $userId,
                    $task->reportComponent,
                    $role,
                    $currentAccess->id,
                    'task',
                    $revisionId
                );
            } elseif (!empty($task->reportPage)) {
                static::grantReportPage(
                    $userId,
                    $task->reportPage,
                    $role,
                    $currentAccess->id,
                    'task',
                    $revisionId
                );
            } elseif (!empty($task->reportSection)) {
                static::grantReportSection(
                    $userId,
                    $task->reportSection,
                    $role,
                    $currentAccess->id,
                    'task',
                    $revisionId
                );
            } elseif (!empty($task->reports)) {
                static::grantReport($userId, $task->reports, $role, $currentAccess->id, 'task', $revisionId);
            }
        }
    }

    /**
     * @param $userId
     * @param Comment $comment
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     * @return AccessList $currentAccess|null
     */
    private static function grantComment($userId, Comment $comment, $roleId, $parentId = null, $dependant = false, $revisionId = null)
    {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::COMMENT_TYPE,
            'object_id' => $comment->id,
            'parent_id' => $parentId,
            'role_id' => $roleId,
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::COMMENT_TYPE,
                'object_id' => $comment->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
            ]);
            if (!$currentAccess->save()) {
                return false;
            }
        }
        return $currentAccess;
    }


    /**
     * @param $userId
     * @param Evidence $comment
     * @param $roleId
     * @param null $parentId
     * @param bool $dependant
     * @param null $revisionId
     * @return AccessList $currentAccess|null
     */
    private static function grantEvidence($userId, Evidence $comment, $roleId, $parentId = null, $dependant = false, $revisionId = null)
    {
        $currentAccess = AccessList::findOne([
            'user_id' => $userId,
            'object' => static::EVIDENCE_TYPE,
            'object_id' => $comment->id,
            'parent_id' => $parentId,
            'role_id' => $roleId,
        ]);
        if (null === $currentAccess) {
            $currentAccess = new AccessList([
                'user_id' => $userId,
                'object' => static::EVIDENCE_TYPE,
                'object_id' => $comment->id,
                'role_id' => $roleId,
                'parent_id' => $parentId,
            ]);
            if (!$currentAccess->save()) {
                return false;
            }
        }
        return $currentAccess;
    }


    /**
     * @param $userId
     * @param $object
     * @param null $roleId
     * @param null $parentId
     * @throws ScealException
     */
    public static function revoke($userId, $object, $roleId = null, $parentId = null)
    {
        $metric = false;
        if ($object instanceof Report) {
            $type = static::REPORT_TYPE;
        } elseif ($object instanceof ReportSection) {
            $type = static::REPORT_SECTION_TYPE;
        } elseif ($object instanceof ReportPage) {
            $type = static::REPORT_PAGE_TYPE;
        } elseif ($object instanceof ReportComponent) {
            $type = static::REPORT_COMPONENT_TYPE;
        } elseif ($object instanceof Metric) {
            $metric = true;
        } elseif ($object instanceof Task) {
            $type = static::TASK_TYPE;
        } elseif ($object instanceof Comment) {
            $type = static::COMMENT_TYPE;
        } else {
            throw new ScealException(ScealException::BAD_DATA, 'wrong object');
        }
        if (!$metric) {
            $currentAcccess = AccessList::find()->andWhere([
                'user_id' => $userId,
                'object' => $type,
                'object_id' => $object->id,
                'parent_id' => $parentId
            ]);
            if (!empty($roleId)) {
                $currentAcccess->andWhere(['role_id' => $roleId]);
            }
            $currentAcccess = $currentAcccess->one();
            if (null !== $currentAcccess) {
                AccessList::deleteAll(['parent_id' => $currentAcccess->id]);
                $currentAcccess->delete();
            }
        } else {
            $metricAccess = MetricUser::findOne([
                'user_id' => $userId,
                'metric_id' => $metric->id
            ]);
            if ($metricAccess) {
                $metricAccess->delete();
            }
        }
    }

    /**
     * @param $type
     * @param $id
     * @throws ScealException
     */
    public static function revokeObjects($type, $id)
    {
        if (!in_array($type, self::TYPES)) {
            throw new ScealException(ScealException::BAD_DATA, 'wrong object');
        }

        switch ($type) {
            case self::TASK_TYPE:
                AccessList::deleteAll([
                    'object_id' => $id,
                    'object' => $type,
                ]);
                break;
            case self::REPORT_TYPE:
                self::revokeObject($type, $id);

                $sections = ArrayHelper::getColumn(
                    ReportSection::find()
                        ->select('id')
                        ->where(['report_id' => $id])
                        ->asArray()->all(),
                    'id'
                );
                self::revokeObject(self::REPORT_SECTION_TYPE, $sections);

                $pages = ArrayHelper::getColumn(ReportPage::find()
                    ->select('id')
                    ->where(['report_section_id' => $sections])
                    ->asArray()->all(), 'id');
                self::revokeObject(self::REPORT_SECTION_TYPE, $pages);

                $components = ArrayHelper::getColumn(ReportComponent::find()
                    ->select('id')
                    ->where(['report_id' => $id])
                    ->asArray()->all(), 'id');
                self::revokeObject(self::REPORT_COMPONENT_TYPE, $components);

                break;
            case self::REPORT_SECTION_TYPE:
                self::revokeObject($type, $id);

                $components = ArrayHelper::getColumn(ReportComponent::find()
                    ->alias('rc')
                    ->select('rc.id')
                    ->innerJoin(
                        ['rp' => ReportPage::tableName()],
                        'rp.report_section_id=:section_id and rp.id=rc.report_page_id',
                        ['section_id' => $id]
                    )->asArray()->all(), 'id');
                self::revokeObject(self::REPORT_COMPONENT_TYPE, $components);

                break;
            case self::REPORT_COMPONENT_TYPE:
                self::revokeObject($type, $id);
                break;
            case self::METRIC_TYPE:
                MetricUser::deleteAll([
                    'metric_id' => $id
                ]);
                break;
        }
    }

    /**
     * @param $type
     * @param $id
     * @throws ScealException
     */
    public static function revokeObject($type, $id)
    {
        if (!in_array($type, self::TYPES)) {
            throw new ScealException(ScealException::BAD_DATA, 'wrong object');
        }

        $ids = [];

        $list = AccessList::find()
            ->select('al.id, pal.object as p_object, pal.id as p_id')
            ->alias('al')
            ->leftJoin(['pal' => AccessList::tableName()], 'pal.id=al.parent_id')
            ->where([
                'al.object_id' => $id,
                'al.object' => $type,
            ])->asArray()->all();

        foreach ($list as $item) {
            if ($item['p_object'] == self::TASK_TYPE) {
                $ids[] = $item['p_id'];
            } else {
                $ids[] = $item['id'];
            }
        }

        AccessList::deleteAll(['id' => $ids]);
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%access_list}}';
    }

    /**
     * @param $user
     * @param $object
     * @param $action
     * @return bool
     */
    public static function can($user, $object, $action)
    {
        if ($object instanceof Report) {
            return static::canReport($user, $object, $action);
        } elseif ($object instanceof ReportSection) {
            return static::canReportSection($user, $object, $action);
        } elseif ($object instanceof ReportPage) {
            return static::canReportPage($user, $object, $action);
        } elseif ($object instanceof ReportComponent) {
            return static::canReportComponent($user, $object, $action);
        } elseif ($object instanceof Task) {
            return static::canTask($user, $object, $action);
        } elseif ($object instanceof Metric) {
            return static::canMetric($user, $object, $action);
        } elseif ($object instanceof MetricViewTemplate) {
            return static::canMetricView($user, $object, $action);
        }

        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param Report $report
     * @param $action
     * @return bool
     */
    private static function canReport(\common\overrides\web\User $user, Report $report, $action)
    {
        if (in_array($action, static::ACTIONS_ADMIN)) {
            $accessForCreator = ($report->creator_id === $user->id);
            if ($accessForCreator) {
                return true;
            }
            $access = static::findOne([
                'object' => static::REPORT_TYPE,
                'object_id' => $report->id,
                'user_id' => $user->id,
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
            if ($access) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param ReportSection $reportSection
     * @param $action
     * @return bool
     */
    private static function canReportSection(\common\overrides\web\User $user, ReportSection $reportSection, $action)
    {
        $accessForCreator = ($reportSection->creator_id === $user->id);
        if ($accessForCreator) {
            return true;
        }
        $accessQuery = static::find()
            ->andWhere([
                'or',
                ['and', ['object' => static::REPORT_TYPE], ['object_id' => $reportSection->report_id]],
                ['and', ['object' => static::REPORT_SECTION_TYPE], ['object_id' => $reportSection->id]],
                'user_id' => $user->id,
            ]);

        if (in_array($action, static::ACTIONS_ADMIN)) {
            $accessQuery->andWhere([
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
        }

        if ($accessQuery->one()) {
            return true;
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param ReportPage $reportPage
     * @param $action
     * @return bool
     */
    private static function canReportPage(\common\overrides\web\User $user, ReportPage $reportPage, $action)
    {
        $accessQuery = static::find()
            ->leftJoin('report_section rs', 'rs.id=:report_section_id', ['report_section_id' => $reportPage->report_section_id])
            ->andWhere([
                'or',
                ['and', ['object' => static::REPORT_TYPE], 'object_id=rs.report_id'],
                ['and', ['object' => static::REPORT_SECTION_TYPE], ['object_id' => $reportPage->report_section_id]],
                ['and', ['object' => static::REPORT_PAGE_TYPE], ['object_id' => $reportPage->id]],
                'user_id' => $user->id,
            ]);

        if (in_array($action, static::ACTIONS_ADMIN)) {
            $accessQuery->andWhere([
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
        }

        $accessForCreator = ($reportPage->creator_id === $user->id);
        if ($accessQuery->one() || $accessForCreator) {
            return true;
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param ReportComponent $reportComponent
     * @param $action
     * @return bool
     */
    private static function canReportComponent(\common\overrides\web\User $user, ReportComponent $reportComponent, $action)
    {
        $accessQuery = static::find()
            ->leftJoin('report_page rp', 'rp.id=:report_page_id', ['report_page_id'=>$reportComponent->report_page_id])
            ->andWhere([
                'or',
                ['and', ['object' => static::REPORT_TYPE], ['object_id'=>$reportComponent->report_id]],
                ['and', ['object' => static::REPORT_SECTION_TYPE], 'object_id=rp.report_section_id'],
                ['and', ['object' => static::REPORT_PAGE_TYPE], 'object_id=rp.id'],
                ['and', ['object' => static::REPORT_COMPONENT_TYPE], ['object_id' => $reportComponent->id]],
                'user_id' => $user->id,
            ]);

        if (in_array($action, static::ACTIONS_ADMIN)) {
            $accessQuery->andWhere([
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
        }

        $accessForCreator = ($reportComponent->creator_id === $user->id);
        if ($accessQuery->one() || $accessForCreator) {
            return true;
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param Task $task
     * @param $action
     * @return bool
     */
    private static function canTask(\common\overrides\web\User $user, Task $task, $action)
    {
        if (in_array($action, static::ACTIONS_ADMIN)) {
            $access = static::findOne([
                'object' => static::TASK_TYPE,
                'object_id' => $task->id,
                'user_id' => $user->id,
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
            if ($access || static::isAdmin($user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @return bool
     */
    public static function isAdmin(\common\overrides\web\User $user)
    {
        return static::isCompanyAdmin($user) || static::isGlobalAdmin($user);
    }

    /**
     * @param \common\overrides\web\User $user
     * @return bool
     */
    private static function isCompanyAdmin(\common\overrides\web\User $user)
    {
        $companyId = $user->getCompanyId() ?: 1;
        $companyUser = CompanyUser::findOne([
            'user_id' => $user->id,
            'company_id' => $companyId
        ]);
        if (null !== $companyUser && $companyUser->role_id === Role::ADMIN) {
            return true;
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @return bool
     */
    private static function isGlobalAdmin(\common\overrides\web\User $user)
    {
        $companyUser = CompanyUser::findOne([
            'user_id' => $user->id,
            'company_id' => 1
        ]);
        if (null !== $companyUser && $companyUser->role_id === Role::ADMIN) {
            return true;
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param Metric $metric
     * @param $action
     * @return bool
     */
    public static function canMetric(\common\overrides\web\User $user, Metric $metric, $action)
    {
        if (in_array($action, static::ACTIONS_ADMIN)) {
            $access = MetricUser::findOne([
                'user_id' => $user->id,
                'metric_id' => $metric->id,
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
            if ($access || static::isAdmin($user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \common\overrides\web\User $user
     * @param MetricViewTemplate $metricView
     * @param $action
     * @return bool
     */
    public static function canMetricView(\common\overrides\web\User $user, MetricViewTemplate $metricView, $action)
    {
        if (in_array($action, static::ACTIONS_ADMIN)) {
            $access = static::findOne([
                'object' => static::METRIC_VIEW_TYPE,
                'object_id' => $metricView->id,
                'user_id' => $user->id,
                'role_id' => [Role::ADMIN, Role::TASK_ADMIN]
            ]);
            if ($access || static::isAdmin($user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public static function getEnumForType()
    {
        return 'ENUM('
            . implode(
                ', ',
                array_map(
                    function ($value) {
                        return '"' . $value . '"';
                    },
                    static::TYPES
                )
            )
            . ') NOT NULL DEFAULT "' . static::REPORT_TYPE . '"';
    }

    /**
     * @param Report $report
     * @param \common\overrides\web\User $user
     * @param null $revisionId
     * @return array
     */
    public static function getReportRoles(Report $report, \common\overrides\web\User $user, $revisionId = null)
    {
        if ($report->creator_id===$user->id) {
            return [Role::OWNER];
        }
        $query = static::find()->select('role_id')->distinct()
            ->andWhere([
                'object' => static::REPORT_TYPE,
                'object_id' => $report->id,
                'user_id' => $user->id,
            ]);
        if (!empty($revisionId)) {
            $query->andWhere(['revision_id' => $revisionId]);
        }
        return $query->column();
    }

    /**
     * @param Report $report
     * @return mixed
     */
    public static function getReportUsers(Report $report)
    {
        $r['owner'][] = User::find()->alias('u')
            ->select(['u.id', 'u.display_name', 'u.email'])
            ->where(['u.id'=>$report->creator_id])->asArray()->one();

        $users = AccessList::find()->alias('al')
            ->select(['al.role_id','u.id', 'u.display_name', 'u.email'])
            ->innerJoin('user u', 'u.id=al.user_id')
            ->andWhere(['al.object'=>'report', 'al.object_id'=>$report])
            ->andWhere(['al.role_id'=>[Role::TO, Role::CC, Role::BCC]])
            ->asArray()->all();

        foreach ($users as $user) {
            switch ($user['role_id']) {
                case Role::TO:
                    $r['to'][] = $user;
                    break;
                case Role::CC:
                    $r['cc'][] = $user;
                    break;
                case Role::BCC:
                    $r['bcc'][] = $user;
                    break;
            }
        }
        return $r;
    }
}
