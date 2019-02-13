<?php

namespace app\controllers;

use Yii;
use app\helpers\{Logger, TaskManager, FolderHelper};
use app\helpers\export\Export2ExcelCsvBehavior;
use app\models\{Accounts, AdBase, ExpandedAdCopy, ExpandedAdCopySearch, ExpandedAdCopyTasks,
    ExpandedAdCopyGroups, AdExtension, AdExtensionCallout, AdExtensionCalloutSearch,
    AdExtensionCalloutTasks, AdExtensionSearch, AdExtensionSnippet, AdExtensionSnippetSearch,
    AdExtensionSnippetTasks, AdExtensionTasks, AdForm, DestinationUrl, ProductModifierLists,
    Proposition, Tasks, VersionsAdExtension, VersionsAdExtensionCallout, VersionsAdExtensionSnippet,
    VersionsExpandedAdCopy, User, Settings, FolderBase};
use app\widgets\MenuWidget;
use yii\filters\{AccessControl, VerbFilter};
use yii\helpers\ArrayHelper;
use yii\web\{ForbiddenHttpException, NotFoundHttpException, Response};
use yii\widgets\ActiveForm;


/**
 * AdBaseController implements the CRUD actions for ExpandedAdCopy / AdExtension models.
 */
class AdBaseController extends BaseAsynchronousController
{
    public $modelType = 1;
    public $title = 'Expanded Ad Copy';
    public $indexCreatePath = '@app/views/ad-copy/index-create';

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => User::hasBackendAccess()
                    ],
                ],
            ],
            'export2excel' => [
                'class' => Export2ExcelCsvBehavior::className(),
            ]
        ];
    }

    public function actions()
    {
        return [
            //new add download action
            'download' => [
                'class' => 'app\helpers\export\DownloadAction',
            ],
        ];
    }

    /**
     * Lists all models.
     * @return mixed
     */
    public function actionIndex()
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $searchModel =  new AdExtensionSearch();
                $this->title = AdBase::AD_EXTENSION_NAME;
                $model = new AdExtension();
                $folderType = FolderBase::TYPE_AD_EXTENSION;
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $searchModel =  new AdExtensionCalloutSearch();
                $this->title = AdBase::AD_EXTENSION_NAME_CALLOUT;
                $model = new AdExtensionCallout();
                $folderType = FolderBase::TYPE_AD_EXTENSION_CALLOUT;
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $searchModel =  new AdExtensionSnippetSearch();
                $this->title = AdBase::AD_EXTENSION_NAME_SNIPPET;
                $model = new AdExtensionSnippet();
                $folderType = FolderBase::TYPE_AD_EXTENSION_SNIPPET;
                break;
            default:
                $searchModel =  new ExpandedAdCopySearch();
                $this->title = AdBase::EXPANDED_AD_COPY_NAME;
                $model = new ExpandedAdCopy();
                $folderType = FolderBase::TYPE_EXPANDED_AD_COPY;
                break;
        }
        // columns
        $settings = Settings::getUserSettings();
        $menuWidget = new MenuWidget();
        $actionUrl = $menuWidget::getActionUrlFromUrl();
        if (isset($settings->settings[$settings::STNG_COLUMN][$actionUrl])) {
            $currentColumns =  $settings->settings[$settings::STNG_COLUMN][$actionUrl];
        } else {
            $currentButtons = $menuWidget->getCurrentButtonsFromUrl($actionUrl);
            $currentColumns = isset($currentButtons['Columns']['items']) ? $currentButtons['Columns']['items'] : [];
        }
        // search params
        $params = Yii::$app->request->queryParams;
        $dataProvider = $searchModel->search($params, true);
        // download
        if (isset($params['downloadSheet'])) {
            $dataProvider->pagination->setPageSize(1000);
            $this->downloadSheet($dataProvider, $currentColumns, $model);
        }
        // all ids
        if ($dataProvider->pagination && 1000 > $dataProvider->pagination->pageSize) {
            $dataProvider->pagination->setPageSize(1000);
            $foundIds = $dataProvider->getKeys();
            $dataProvider = $searchModel->search($params, true);
        } else {
            $foundIds = $dataProvider->getKeys();
        }
        // for create form
        $pmlsDd = $destinationUrl = $propositionsDd = false;
        $viewName = '@app/views/ad-copy/index';
        $viewArgs = [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'foundIds' => $foundIds,
            'title' => $this->title,
            'currentColumns' => $currentColumns,
            'pmlsDd' => $pmlsDd,
            'destinationUrl' => $destinationUrl ? $destinationUrl->url : '',
            'propositionsDd' => $propositionsDd,
            'model' => $model,
            'folderType' => $folderType,
            'indexCreateAsync' => Yii::$app->urlManager->createAbsoluteUrl([$this->id.'/index-create'])
        ];
        return $this->renderView($viewName, $viewArgs);
    }

    /**
     * @inheritdoc
     */
    public function actionIndexCreate()
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $model = new AdExtension();
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $model = new AdExtensionCallout();
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $model = new AdExtensionSnippet();
                break;
            default:
                $model = new ExpandedAdCopy();
                break;
                break;
        }
        if (AdBase::AD_EXTENSION_TYPE == $this->modelType || AdBase::EXPANDED_AD_COPY_TYPE == $this->modelType) {
            $pml = new ProductModifierLists();
            $pmlsDd = $pml->currentVersionsDd;
            $destinationUrl = DestinationUrl::find()->one();
            $query = Proposition::find();
            $propositions = $query->all();
            $propositionsDd = [];
            if ($propositions) {
                foreach ($propositions as $proposition) {
                    $propositionsDd[$proposition->id] = $proposition->name;
                }
            }
        } else {
            $pmlsDd = $destinationUrl = $propositionsDd = false;
        }
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $this->renderAjax(
                $this->indexCreatePath,
                [
                    'pmlsDd' => $pmlsDd,
                    'destinationUrl' => $destinationUrl ? $destinationUrl->url : '',
                    'propositionsDd' => $propositionsDd,
                    'model' => $model,
                ]
            );
        } else {
            return $this->redirect(['index']);
        }
    }

    /**
     * Lists all versions by id of any model in versions group.
     * @return mixed
     */
    public function actionVersions($id)
    {
        $this->title = 'Ad Extension Versions';
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $searchModel =  new AdExtensionSearch();
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $searchModel =  new AdExtensionCalloutSearch();
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $searchModel =  new AdExtensionSnippetSearch();
                break;
            default:
                $searchModel =  new ExpandedAdCopySearch();
                $this->title = 'Expanded Ad Copy Versions';
                break;
        }
        $dataProvider = $searchModel->searchVersions(Yii::$app->request->queryParams);
        // all ids
        if ($dataProvider->pagination && 1000 > $dataProvider->pagination->pageSize) {
            $dataProvider->pagination->setPageSize(1000);
            $foundIds = $dataProvider->getKeys();
            $dataProvider = $searchModel->searchVersions(Yii::$app->request->queryParams);
        } else {
            $foundIds = $dataProvider->getKeys();
        }
        $viewArgs = [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'id' => $id,
            'foundIds' => $foundIds
        ];
        return $this->renderView('@app/views/ad-copy/versions', $viewArgs);
    }

    /**
     * Sets version as current.
     * @return mixed
     */
    public function actionSetCurrent($id)
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $model =  AdExtension::findOne($id);
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $model =  AdExtensionCallout::findOne($id);
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $model =  AdExtensionSnippet::findOne($id);
                break;
            default:
                $model =  ExpandedAdCopy::findOne($id);
                break;
        }
        if ($model) {
            $version = $model->version;
            if (1 !== $version->current) {
                $version->setCurrent();
            }
            $this->redirect(['view', 'id' => $model->id]);
        } else {
            throw new NotFoundHttpException("No Record with ID #$id was found");
        }
    }

    /**
     * Displays a single model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $params = Yii::$app->request->queryParams;
        // download
        if (isset($params['downloadSheet'])) {
            return $this->redirect(['/downloads/expanded-adcopy', 'id' => $id]);
        }
        $ads = $adsSorted = [];
        $model = $this->findModel($id);
        $this->getAds($model, $ads, $adsSorted);
        $setLiveTask = $model->getSetLiveTask($model->id);
        // for create form
        if (AdBase::AD_EXTENSION_TYPE == $this->modelType || AdBase::EXPANDED_AD_COPY_TYPE == $this->modelType) {
            $pml = new ProductModifierLists();
            $pmlsDd = $pml->currentVersionsDd;
            $destinationUrl = DestinationUrl::find()->one();
            $query = Proposition::find();
            $propositions = $query->all();
            $propositionsDd = [];
            if ($propositions) {
                foreach ($propositions as $proposition) {
                    $propositionsDd[$proposition->id] = $proposition->name;
                }
            }
        } else {
            $pmlsDd = $destinationUrl = $propositionsDd = false;
        }
        $this->title = $model->name;
        $viewArgs = [
            'model' => $model,
            /**/
            'ads' => $ads,
            'adsSorted' => $adsSorted,
            'setLiveTask' => $setLiveTask,
            'quantity' => count($ads),
            /**/
            'pmlsDd' => $pmlsDd,
            'destinationUrl' => $destinationUrl ? $destinationUrl->url : '',
            'propositionsDd' => $propositionsDd
        ];
        return $this->renderView('@app/views/ad-copy/view', $viewArgs);
    }

    /**
     * Sorting ads
     *
     * @param object $model
     * @param array $ads
     * @param array $adsSorted
     */
    public function getAds($model, array &$ads, array &$adsSorted)
    {
        $count = 10;
        $i = 0;
        $allNewAds = [];
        $allErrorsAds = [];
        if ($model::EXPANDED_AD_COPY_TYPE == $model->modelType) {
            $adCopyAdsCount = $model->count;
        } else {
            $adCopyAdsCount = 1;
        }
        $all = $model->generateAds();
        $adsSorted = $model::sortAds($all);
        if (isset($adsSorted['new']['Google']) || isset($adsSorted['errors']['Google'])) {
            $search = 'Google';
        } else {
            $search = 'Bing';
        }
        if ($adCopyAdsCount == 1 || $adCopyAdsCount == 0) {
            $countAllAds = count($all);
            if ($count > $countAllAds) {
                $count =  $countAllAds;
            }
            if (Yii::$app->request->get('qty')) {
                $count = Yii::$app->request->get('qty');
                if ($count > $countAllAds) {
                    $count =  $countAllAds;
                }
            }
            if ($countAllAds > 0) {
                for ($i; $i < $count; $i++) {
                    $ads[] = $all[$i];
                }
            }
        }
        if (isset($adsSorted['new'][$search]) && !empty($adsSorted['new'][$search])) {
            foreach ($adsSorted['new'][$search] as $key => $account) {
                foreach ($account as $ad) {
                    $allNewAds[] = $ad;
                }
                if (count($adsSorted['new'][$search][$key]) > $i) {
                    $adsSorted['new'][$search][$key] = array_slice($allNewAds, 0, $i);
                } else {
                    break;
                }

            }
        }
        if (isset($adsSorted['errors'][$search]) && !empty($adsSorted['errors'][$search])) {
            foreach ($adsSorted['errors'][$search] as $key => $account) {
                foreach ($account as $ad) {
                    $allErrorsAds[] = $ad;
                }
                if (count($adsSorted['errors'][$search][$key]) > $i) {
                    $adsSorted['errors'][$search][$key] = array_slice($allErrorsAds, 0, $i);
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id = null)
    {
        $model = $version = $controllerUrl = false;
        $this->initBaseVariables($model, $version, $controllerUrl);
        $copyId = Yii::$app->request->get('copy_id');
        // creating ad copy
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $pmlsToLink = $adCopyGroups = [];
            if ($this->modelType == AdBase::EXPANDED_AD_COPY_TYPE) {
                // PMLs
                if ($this->modelType == AdBase::EXPANDED_AD_COPY_TYPE) {
                    $postModelKey = 'ExpandedAdCopy';
                } else {
                    $postModelKey = 'AdExtension';
                }
                if (isset($post[$postModelKey]['pmls'])) {
                    if (isset($post[$postModelKey]['pmls']) && !empty($post[$postModelKey]['pmls'])) {
                        $pmlsToAdd = $post[$postModelKey]['pmls'];
                        foreach ($pmlsToAdd as $pmlId) {
                            $pmlToLink = ProductModifierLists::findOne($pmlId);
                            if ($pmlToLink) {
                                $pmlsToLink[] = $pmlToLink;
                            }
                        }
                    }
                    if (empty($pmlsToLink) && ($this->modelType == AdBase::AD_EXTENSION_TYPE
                            || $this->modelType == AdBase::EXPANDED_AD_COPY_TYPE)) {
                        $errorMsg = 'No Product Modifier Lists were found. Please try again.';
                        if (Yii::$app->request->isAjax) {
                            Yii::$app->response->format = Response::FORMAT_JSON;
                            Yii::$app->response->statusCode = 300;
                            return $errorMsg;
                        } else {
                            Logger::log('danger', $errorMsg);
                            return $this->redirect(['create', 'id' => $id]);
                        }
                    }
                } else {
                    $errorMsg = 'Product Modifier Lists are required. Please specify at least one.';
                    if (Yii::$app->request->isAjax) {
                        Yii::$app->response->format = Response::FORMAT_JSON;
                        Yii::$app->response->statusCode = 300;
                        return $errorMsg;
                    } else {
                        Logger::log('danger', $errorMsg);
                        return $this->redirect(['create', 'id' => $id]);
                    }
                }
                // Expanded Ad Copy Groups
                if (isset($post['expandedAdCopyGroups'])) {
                    $adCopyGroupsIds = isset($post['expandedAdCopyGroups']) ? $post['expandedAdCopyGroups'] : [];
                    $adCopyGroups = ExpandedAdCopyGroups::findAll(['id' => $adCopyGroupsIds]);
                }
            }
            if ($model->load($post) && $model->save()) {
                $this->afterCreateModel($post, $model, $version, $id, $copyId, $pmlsToLink, $adCopyGroups);
                $successMsg = 'Expanded Ad Copy <b>ID#'.$model->id.'</b> has been successfully created!';
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return [
                        'success'   => $successMsg,
                        'id'        => $model->id,
                        'type'      => 'create',
                        'redirectUrl'   => '/'.$controllerUrl.'/view/'.$model->id
                    ];
                } else {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            } elseif ($model->hasErrors()) {
                $errorMsg = 'Model was not created. Please try again';
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return [
                        'errors' => $errorMsg,
                        'validationErrors' => $model->getErrors()
                    ];
                } else {
                    Logger::log('danger', $errorMsg);
                    return $this->redirect(['index']);
                }
            }
        } else {
            $modelId = $copyId ? $copyId : $id;
            if ($modelId) {
                $dataModel = $model->findOne($modelId);
                if ($dataModel) {
                    $model = $dataModel;
                }
            }
            if (AdBase::AD_EXTENSION_TYPE == $this->modelType || AdBase::EXPANDED_AD_COPY_TYPE == $this->modelType) {
                $pml = new ProductModifierLists();
                $pmlsDd = $pml->currentVersionsDd;
                $destinationUrl = DestinationUrl::find()->one();
                $query = Proposition::find();
                $propositions = $query->all();
                $propositionsDd = [];
                if ($propositions) {
                    foreach ($propositions as $proposition) {
                        $propositionsDd[$proposition->id] = $proposition->name;
                    }
                }
            } else {
                $pmlsDd = $destinationUrl = $propositionsDd = false;
            }
            return $this->render(
                '@app/views/ad-copy/create',
                [
                    'model' => $model,
                    'pmlsDd' => $pmlsDd,
                    'destinationUrl' => $destinationUrl ? $destinationUrl->url : '',
                    'propositionsDd' => $propositionsDd,
                    'copyId' => $copyId,
                ]
            );
        }
    }

    /**
     * @param array $post
     * @param object $model
     * @param object $version
     * @param int|null $id
     * @param int|false $copyId
     * @param array $pmlsToLink
     * @param array $adCopyGroups
     * @return boolean
     */
    public function afterCreateModel(
        array $post,
        $model,
        $version,
        $id = null,
        $copyId = false,
        $pmlsToLink = [],
        $adCopyGroups = []
    )
    {
        // adding pmls
        foreach ($pmlsToLink as $pmlToLink) {
            $model->link('pmls', $pmlToLink);
        }
        // accounts
        $accountsToLink = [];
        if ($this->modelType == AdBase::AD_EXTENSION_TYPE_CALLOUT || $this->modelType == AdBase::AD_EXTENSION_TYPE_SNIPPET || $this->modelType == AdBase::AD_EXTENSION_TYPE) {
            // google
            if (isset($post['AccountsGoogle']) && !empty($post['AccountsGoogle'])) {
                foreach ($post['AccountsGoogle'] as $accId) {
                    $accToLink = Accounts::findOne($accId);
                    if ($accToLink) {
                        $accountsToLink[] = $accToLink;
                    }
                }
            }
            // bing
            if (isset($post['AccountsBing']) && !empty($post['AccountsBing'])) {
                foreach ($post['AccountsBing'] as $accId) {
                    $accToLink = Accounts::findOne($accId);
                    if ($accToLink) {
                        $accountsToLink[] = $accToLink;
                    }
                }
            }
            if (empty($accountsToLink)) {
                $errorMsg = 'No account specified. Please try again.';
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    Yii::$app->response->statusCode = 300;
                    return $errorMsg;
                } else {
                    Logger::log('danger', $errorMsg);
                    return $this->redirect(['create', 'id' => $id]);
                }
            }
        }
        foreach ($accountsToLink as $accToLink) {
            $model->link('accounts', $accToLink);
        }
        // generating version params
        $itemId = $model->id;
        $groupId = $copyId ? $version->getGroupId() : $version->getGroupId($id);
        $versionNumber = $id && !$copyId ? $version->getNewVersionNumber($groupId) : 1;
        $versionDescription = isset($post['Version']['description']) ? $post['Version']['description'] : '';
        // loading version params
        $version->item_id = $itemId;
        $version->version = $versionNumber;
        $version->group_id = $groupId;
        $version->description = $versionDescription;
        // saving
        $res = $version->save();
        if ($res) {
            // adding ad copy groups
            if (!empty($adCopyGroups)) {
                if (isset($post['expandedAdCopyGroups'])) {
                    foreach ($adCopyGroups as $adCopyGroup) {
                        $version->link('expandedAdCopyGroups', $adCopyGroup);
                    }
                }
            }
            $version->setCurrent(is_null($id));
            // generating ads if needed
            if ($this->modelType == AdBase::EXPANDED_AD_COPY_TYPE) {
                $model->generateAdsTask();
            }
        }
    }

    /**
     * @param type $model
     * @param type $version
     * @param type $controllerUrl
     */
    public function initBaseVariables(&$model, &$version, &$controllerUrl)
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $model =  new AdExtension();
                $version = new VersionsAdExtension();
                $controllerUrl = 'ad-extension';
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $model =  new AdExtensionCallout();
                $version = new VersionsAdExtensionCallout();
                $controllerUrl = 'ad-extension-callout';
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $model =  new AdExtensionSnippet();
                $version = new VersionsAdExtensionSnippet();
                $controllerUrl = 'ad-extension-snippet';
                break;
            default:
                $model =  new ExpandedAdCopy();
                $version = new VersionsExpandedAdCopy();
                $controllerUrl = 'expanded-ad-copy';
                break;
        }
    }

    /**
     * Deletes an existing version of model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->version && $model->version->current) {
            $model->version->setLastCurrent();
        }
        $model->delete();
        Logger::log('info', 'Model ID #' . $id . ' has been deleted');
        return $this->redirect(['index']);
    }

    /**
     * Deletes an existing model and all it's versions.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDeleteAll($id)
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $tableName =  AdExtension::tableName();
                $versionTableName = VersionsAdExtension::tableName();
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $tableName =  AdExtensionCallout::tableName();
                $versionTableName = VersionsAdExtensionCallout::tableName();
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $tableName =  AdExtensionSnippet::tableName();
                $versionTableName = VersionsAdExtensionSnippet::tableName();
                break;
            default:
                $tableName =  ExpandedAdCopy::tableName();
                $versionTableName = VersionsExpandedAdCopy::tableName();
                break;
        }
        $model = $this->findModel($id);
        $linkedModels = $model->find()
            ->join('JOIN', $versionTableName, $versionTableName.'.item_id = '.$tableName.'.id')
            ->where([$versionTableName.'.group_id' => $model->version->group_id])
            ->all();
        if ($linkedModels) {
            foreach ($linkedModels as $linkedModel) {
                $linkedModel->delete();
            }
        } else { // just in case
            $model->delete();
        }
        Logger::log('info', 'Model ID #' . $id . ' and all its versions have been deleted');
        return $this->redirect(['index']);
    }

    /**
     * Finds the model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $model =  AdExtension::findOne($id);
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $model =  AdExtensionCallout::findOne($id);
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $model =  AdExtensionSnippet::findOne($id);
                break;
            default:
                $model =  ExpandedAdCopy::findOne($id);
                break;
        }
        if ($model !== null) {
            // checking user permissions
            $userId = isset(Yii::$app->user) ? Yii::$app->user->identity->id : false;
            if (!User::isSuperAdmin() && $userId != $model->user_id) {
                throw new ForbiddenHttpException('You don\'t have rights to manage this model.');
            }
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * Creates generated Ads from model in Google/Bing account
     *
     * @param integer $id
     * @param integer $accountId
     *
     * @return mixed
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionAdsCreate($id, $accountId = false)
    {
        // creating ads
        if (Yii::$app->request->isPost) {
            /**
             * @var AdCopy|AdExtension|AdExtensionSnippet|AdExtensionCallout                     $model
             * @var AdCopyTasks|AdExtensionTasks|AdExtensionSnippetTasks|AdExtensionCalloutTasks $task
             * @var Tasks                                                                        $taskMain
             */
            $model = $this->findModel($id);
            $this->resetAccountOverviewSettings($model);
            $task = $model->getSetLiveTask($id, false, $accountId);
            if ($task) {
                $taskMain = $task->getTaskModel()->one();
                if (!$taskMain->isExecutedNotCorrectly()) {
                    TaskManager::addByAnotherTask($taskMain);
                    // rendering view
                    $successMsg = 'Tasks was successfully created.';
                    if (Yii::$app->request->isAjax) {
                        Yii::$app->response->format = Response::FORMAT_JSON;
                        return [
                            'success'   => $successMsg,
                            'id'        => $model->id,
                            'type'      => 'tasks'
                        ];
                    } else {
                        return $this->render(
                            '@app/views/tasks/add-task',
                            [
                                'model' => $model,
                                'text' => 'Creating ad is inprogress',
                            ]
                        );
                    }
                }
            }
            if ($model->generateAdsActiveTask) {
                $errorMsg = "Generation Ads for $model->name is inprogress, please repeat operation after it is finished.";
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    Yii::$app->response->statusCode = 300;
                    return $errorMsg;
                } else {
                    throw new ForbiddenHttpException($errorMsg);
                }
            }
            if (0 !== $model->count_valid || AdBase::EXPANDED_AD_COPY_TYPE == $model->modelType) {
                $taskTitle = $taskType = false;
                $this->initTaskVariables($model, $taskTitle, $taskType);
                $tasksCount = 0;
                $accounts = [];
                if ($accountId) {
                    $account = Accounts::findOne($accountId);
                    if ($account) {
                        $accounts = [$account];
                    }
                } else {
                    $accounts = $model->accounts;
                }
                foreach ($accounts as $account) {
                    if (AdBase::EXPANDED_AD_COPY_TYPE == $model->modelType) {
                        $res = $model->createSetLiveTask($account);
                    } else {
                        $res = TaskManager::add(
                            $model->user_id,
                            $taskTitle.'('.ucfirst($account->type).' - '.$account->customer_id.' '.$account->name.')'.': '.$model->name,
                            $model,
                            $taskType,
                            ['accountId' => $account->id]
                        );
                    }
                    if ($res) {
                        $tasksCount++;
                    }
                }
                $successMsg = 'Tasks were successfully created. Tasks count: '.$tasksCount;
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return [
                        'success'   => $successMsg,
                        'id'        => $model->id,
                        'type'      => 'tasks'
                    ];
                } else {
                    Logger::log('info', $successMsg);
                    return $this->render(
                        '@app/views/tasks/add-task',
                        [
                            'model' => $model,
                            'text' => 'Creating ad is inprogress',
                        ]
                    );
                }
            } else {
                $errorMsg = "Items for $model->name were already created before.";
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    Yii::$app->response->statusCode = 300;
                    return $errorMsg;
                } else {
                    throw new ForbiddenHttpException($errorMsg);
                }
            }
        } else {
            return $this->redirect(['view', 'id' => $id]);
        }
    }

    /**
     * @param object $model
     * @param mixed $taskTitle
     * @param mixed $taskType
     */
    public function initTaskVariables($model, &$taskTitle, &$taskType)
    {
        switch ($model->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $taskTitle = 'Set live Ad Extension Sitelink ';
                $taskType = AdExtensionTasks::TASK_CREATE_AD_EXTENSION;
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $taskTitle = 'Set live Ad Extension Callout ';
                $taskType = AdExtensionCalloutTasks::TASK_CREATE_AD_EXTENSION_CALLOUT;
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $taskTitle = 'Set live Ad Extension Structured Snippet ';
                $taskType = AdExtensionSnippetTasks::TASK_CREATE_AD_EXTENSION_SNIPPET;
                break;
            default:
                $taskTitle = 'Set live Expanded Ad Copy ';
                $taskType = ExpandedAdCopyTasks::TASK_CREATE_EXPANDED_AD_COPY;
                break;
        }
    }

    /**
     * Reset statistic Ad Extension or Ad Extension Callout or Ad Extension Snippet Account Overview
     *
     * @param object $model
     * @return boolean
     */
    public function resetAccountOverviewSettings($model)
    {
        switch ($model->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $accountOverview = $model->accountOverviewAdExtension;
                if ($accountOverview) {
                    $result = $accountOverview->result;
                    $result['campaigns'] = '';
                    $result['extensions'] = '';
                    $result['isSync'] = 0;
                    $accountOverview->result = $result;
                    return $accountOverview->save();
                }
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $accountOverview = $model->accountOverviewAdExtensionCallout;
                if ($accountOverview) {
                    $result = $accountOverview->result;
                    $result['extensions'] = '';
                    $result['isSync'] = 0;
                    $accountOverview->result = $result;
                    return $accountOverview->save();
                }
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $accountOverview = $model->accountOverviewAdExtensionSnippet;
                if ($accountOverview) {
                    $result = $accountOverview->result;
                    $result['extensions'] = '';
                    $result['isSync'] = 0;
                    $accountOverview->result = $result;
                    return $accountOverview->save();
                }
                break;
            default:
                $accountOverview = $model->accountOverviewExpandedAdCopy;
                if ($accountOverview) {
                    $result = $accountOverview->result;
                    $result['campaigns'] = '';
                    $result['adgroups'] = '';
                    $result['ads'] = '';
                    $result['isSync'] = 0;
                    $accountOverview->result = $result;
                    return $accountOverview->save();
                }
                break;
        }
        return false;
    }

    /**
     * Creating task for synchronizing sgmtc elements of current Ad Copy with adwords/bing live items
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function actionSynchronize($id)
    {
        if (Yii::$app->request->isPost) {
            $adCopy = $this->findModel($id);
            $tasksCount = [
                'synchronize' => 0,
                'generate' => 0,
                'failed' => 0
            ];
            // sgmtc generation task
            if (!$adCopy->hasSgmtcAds()) {
                if ($adCopy->createGenerateTask(false)) {
                    $tasksCount['generate']++;
                }
            }
            if ($adCopy->version) {
                foreach ($adCopy->accounts as $account) {
                    if ($adCopy->createSynchronizeTask($account)) {
                        $tasksCount['synchronize']++;
                    } else {
                        $tasksCount['failed']++;
                    }
                }
            }
            $infoMsg = 'Tasks create result<br>';
            $infoMsg .= '- synchronization tasks: '.$tasksCount['synchronize'].';<br>';
            $infoMsg .= '- generate ads tasks: '.$tasksCount['generate'].';<br>';
            $infoMsg .= '- failed tasks: '.$tasksCount['failed'].';<br>';
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return [
                    'info'   => $infoMsg,
                    'id'        => $adCopy->id,
                    'type'      => 'tasks'
                ];
            } else {
                Logger::log('info', $infoMsg);
                // rendering view
                return $this->redirect(['tasks/index']);
            }
        } else {
            return $this->redirect(['view', 'id' => $id]);
        }
    }

    public function actionSetLiveResult($id, $taskId = false)
    {
        $model = $this->findModel($id);
        $taskModel = $taskId ? $model->getSetLiveTask($id, $taskId) : $model->getSetLiveTask($id);
        $result = $taskModel ? $taskModel->result : null;
        if (!$result) {
            return $this->redirect(['view', 'id' => $id]);
        }
        $viewName =  $this->modelType == AdBase::EXPANDED_AD_COPY_TYPE ? '@app/views/ad-copy/ads-create-result' : '@app/views/ad-copy/exts-create-result';
        $viewArgs = [
            'model' => $model,
            'taskInfo' => $taskModel,
        ];
        if (isset($result['resultAll'])) {
            $viewArgs['resultAll'] = $result['resultAll'];
            $viewArgs['totalExpect'] = $result['totalExpect'];
            $viewArgs['totalSuccess'] = $result['totalSuccess'];
        }
        return $this->renderView($viewName, $viewArgs);
    }

    /**
     * Downloading Ad Copy templates
     *
     * @return mixed
     */
    public function actionMassDownload()
    {
        $idsAll = Yii::$app->request->post('acMassEditAll');
        if ($idsAll) {
            $ids = explode(',', $idsAll);
        } else {
            $ids = Yii::$app->request->post('acMassEdit');
        }
        if (!empty($ids)) {
            $this->downloadModels($ids);
        }
    }



    /**
     * Action for Edit button
     */
    public function actionButtons()
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
        }
        // atts
        $idsAll = Yii::$app->request->post('idsAll');
        $ids = $idsAll ? explode(',', $idsAll) : Yii::$app->request->post('ids', []);
        $type = Yii::$app->request->post('type');
        // validation
        if (empty($ids) && 'upload' != $type) {
            if (Yii::$app->request->isAjax) {
                return ['errors' => 'No ids were'];
            } else {
                Logger::log('danger', 'No items were selected');
                return $this->redirect(['index']);
            }
        }
        $result = ['type' => $type];
        // folders
        if (is_array($ids)) {
            $foldersIds = FolderHelper::extractFolderIds($ids);
            $ids = array_diff($ids, $foldersIds);
        } else {
            $foldersIds = Yii::$app->request->post('folderIds', []);
        }
        switch ($type) {
            case 'delete':
                $this->_buttonsDelete($ids, $foldersIds, $result);
                break;
            case 'delete-version':
                $this->_buttonsDeleteVersion($ids, $result);
                break;
            case 'duplicate':
                $this->_buttonsDuplicate($ids, $foldersIds, $result);
                break;
            case 'download':
                return $this->downloadModels($ids);
                break;
            case 'upload':
                $uploadResult = $this->uploadModels();
                $result = ArrayHelper::merge($result, $uploadResult);
                $this->showErrorsModal($result);
                break;
            case 'remove-from-folder':
                $this->_buttonsRemoveFromFolder($ids, $result);
                break;
            default:
                return ['errors' => 'Unknown action type'];
                break;
        }
        return $result;
    }

    private function _buttonsDelete($ids, $foldersIds, array &$result = [])
    {
        $foldersRemoved = $this->deleteFolders($foldersIds);
        $idsRemoved = $this->deleteIds($ids);
        if (AdBase::EXPANDED_AD_COPY_TYPE == $this->modelType) {
            $modelNames = 'Expanded Ad Copies';
        } else {
            $modelNames = 'Ad Extensions';
        }
        if (!empty($idsRemoved)) {
            $resultMsg = $modelNames.' were successfully removed. Ids: '.implode(', ', $idsRemoved);
            if (!empty($foldersRemoved)) {
                $resultMsg .= ' Folders removed: '.implode(', ', $foldersRemoved);
            }
        } elseif (!empty($foldersRemoved)) {
            $resultMsg = 'Models were successfully removed on folder level. Folders removed: '.implode(', ', $foldersRemoved);
        } else {
            $resultMsg = 'No '.$modelNames.' were removed';
        }
        $result['success'] = $resultMsg;
        $result['ids'] = $idsRemoved;
    }

    private function _buttonsDeleteVersion($ids, array &$result = [])
    {
        foreach ($ids as $id) {
            $idsRemoved = $this->deleteVersion($id);
        }
        $resultMsg = 'Ad Copies were successfully removed. 
            Ids: '.implode(', ', $ids);
        $result['success'] = $resultMsg;
        $result['ids'] = $ids;
    }

    /**
     * Deleting one model
     *
     * @param integer $id
     * @return object
     */
    public function deleteVersion($id)
    {
        $model = $this->findModel($id);
        $modelId = $model->id;
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        if ($model->version && $model->version->current) {
            $model->version->setLastCurrent();
        }
        $model->delete();
        $currentVersion = $model->version->getCurrent($model->version->group_id);
        $transaction->commit();
        return $currentVersion;
    }

    private function _buttonsDuplicate($ids, $foldersIds, array &$result = [])
    {
        $id = true === $ids ? false : (int) $ids;
        if (!empty($foldersIds)) {
            $ids = FolderHelper::getFoldersIds($foldersIds);
            $modelsCount = $this->duplicateFolders($ids);
            if ($modelsCount) {
                $result = [
                    'reset' => true,
                    'success' => 'Folders ('.count($ids).') were successfully duplicated. New items count: '.$modelsCount.'.'
                ];
            } else {
                $result = ['errors' => 'No folders/items were duplicated.'];
            }
        } elseif (!$id) {
            $errorMsg = 'No id was selected.';
            $result = ['errors' => $errorMsg];
        } else {
            $model = $this->findModel($id);
            if (!$model) {
                $errorMsg = 'Model was not found.';
                $result = ['errors' => $errorMsg];
            } else {
                // for create form
                if (AdBase::AD_EXTENSION_TYPE == $this->modelType || AdBase::EXPANDED_AD_COPY_TYPE == $this->modelType) {
                    $pml = new ProductModifierLists();
                    $pmlsDd = $pml->currentVersionsDd;
                    $destinationUrl = DestinationUrl::find()->one();
                    $query = Proposition::find();
                    $propositions = $query->all();
                    $propositionsDd = [];
                    if ($propositions) {
                        foreach ($propositions as $proposition) {
                            $propositionsDd[$proposition->id] = $proposition->name;
                        }
                    }
                } else {
                    $pmlsDd = $destinationUrl = $propositionsDd = false;
                }
                $result['success'] = $this->renderAjax(
                    '/ad-copy/index-create',
                    [
                        'model' => $model,
                        'pmlsDd' => $pmlsDd,
                        'destinationUrl' => $destinationUrl,
                        'propositionsDd' => $propositionsDd
                    ]
                );
            }
        }
    }

    private function _buttonsRemoveFromFolder($ids, array &$result = [])
    {
        $idsRemoved = $this->removeFolders($ids);
        if (!empty($idsRemoved)) {
            $resultMsg = 'Product Modifier Lists were removed from their folders. Ids: '.implode(', ', $idsRemoved);
        } else {
            $resultMsg = 'No Product Modifier Lists were removed';
        }
        $result['success'] = $resultMsg;
        $result['pjaxIndexReset'] = true;
    }

    /**
     * Removing all models and all it's versions
     *
     * @return string
     * @throws ForbiddenHttpException
     */
    private function deleteIds($ids)
    {
        switch ($this->modelType) {
            case AdBase::AD_EXTENSION_TYPE:
                $model = new AdExtension();
                break;
            case AdBase::AD_EXTENSION_TYPE_CALLOUT:
                $model = new AdExtensionCallout();
                break;
            case AdBase::AD_EXTENSION_TYPE_SNIPPET:
                $model = new AdExtensionSnippet();
                break;
            default:
                $model = new ExpandedAdCopy();
                break;
        }
        $models = $model::findAll($ids);
        $idsRemoved = [];
        foreach ($models as $model) {
            $idsRemoved[] = $model->id;
            $model->deleteAllVersions();
        }
        return $idsRemoved;
    }

    /**
     * Removing all models and all it's versions
     *
     * @param array $ids
     * @return string
     * @throws ForbiddenHttpException
     */
    private function deleteFolders($ids)
    {
        $model = AdBase::getFolderModelByType($this->modelType);
        $foldersRemoved = [];
        $folders = $model::find()
            ->where(['id' => $ids])
            ->andWhere([$model::tableName().'.user_id' => Yii::$app->user->identity->id])
            ->all();
        foreach ($folders as $folder) {
            foreach ($folder->models as $model) {
                $model->deleteAllVersions();
            }
            $foldersRemoved[] = $folder->id;
            $folder->delete();
        }
        return $foldersRemoved;
    }

    /**
     * Removing all models from their folders
     *
     * @param array $ids
     * @return string
     * @throws ForbiddenHttpException
     */
    private function removeFolders($ids)
    {
        $model = AdBase::getModelByType($this->modelType);
        $models = $model::findAll($ids);
        $idsRemoved = [];
        foreach ($models as $model) {
            $version = $model->version;
            if (!isset($idsRemoved[$version->group_id])) {
                $folder = $version->folder;
                if ($folder) {
                    $idsRemoved[$model->version->group_id] = $model->version->group_id;
                    $version->unlink('folder', $folder, true);
                    if (empty($folder->models)) {
                        $folder->delete();
                    }
                }
            }
        }
        return $idsRemoved;
    }

    /**
     * Duplicating models on folder level
     *
     * @param array $ids
     * @return integer
     * @throws ForbiddenHttpException
     */
    private function duplicateFolders($ids)
    {
        $model = AdBase::getFolderModelByType($this->modelType);
        $folders = $model::findAll($ids);
        $modelsDuplicated = 0;
        foreach ($folders as $folder) {
            $newFolder = AdBase::getFolderModelByType($this->modelType);
            $newFolder->name = $folder->name.' copy';
            if ($newFolder->save()) {
                foreach ($folder->models as $model) {
                    if ($res = $model->duplicateModel($newFolder)) {
                        $modelsDuplicated++;
                    }
                }
            }
        }
        return $modelsDuplicated;
    }

    /**
     * Method for downloading models based on index search args
     *
     * @param yii\data\ActiveDataProvider $dataProvider
     * @param array $currentColumns
     */
    protected function downloadSheet($dataProvider, $currentColumns, $model)
    {
        $modelType = $model->modelType;
        $models = $dataProvider->getModels();
        $modelsArr = [];
        if ($models) {
            // headings
            $modelsArrHeadings = [
                'Name',
                'Description',
                'Current Version'
            ];
            $modelsArrHeadings = ArrayHelper::merge($modelsArrHeadings, $currentColumns);
            $modelsArr[0] = $modelsArrHeadings;
            // ad copies
            foreach ($models as $model) {
                $atts = [];
                foreach ($modelsArrHeadings as $headingKey => $fieldName) {
                    switch ($fieldName) {
                        case 'Name':
                            $atts[$headingKey] = $model->name;
                            break;
                        case 'Description':
                            $atts[$headingKey] = !$model->version || $model->version && empty($model->version->description) ? null : $model->version->description;
                            break;
                        case 'Current Version':
                            $atts[$headingKey] = $model->version ? $model->version->version : '';
                            break;
                        case MenuWidget::BTN_COLUMN_PMLS_USED:
                            $pmlString = '';
                            if ($model->pmls) {
                                foreach ($model->pmls as $pmlLinked) {
                                    $pmlString .= $pmlLinked->id.' - '.$pmlLinked->name.', ';
                                }
                            }
                            $atts[$headingKey] = rtrim($pmlString, ', ');
                            break;
                        case MenuWidget::BTN_COLUMN_AD_COUNT:
                            $atts[$headingKey] = $model->count;
                            break;
                        case MenuWidget::BTN_COLUMN_AD_COUNT_VALID:
                            $atts[$headingKey] = $model->count_valid;
                            break;
                        case MenuWidget::BTN_COLUMN_HEADLINE:
                            $atts[$headingKey] = $model->headline;
                            break;
                        case MenuWidget::BTN_COLUMN_DESC_1:
                            $atts[$headingKey] = $model->description_line_one;
                            break;
                        case MenuWidget::BTN_COLUMN_DESC_2:
                            $atts[$headingKey] = $model->description_line_two;
                            break;
                        case MenuWidget::BTN_COLUMN_DISPLAY_URL:
                            $atts[$headingKey] = $model->display_url;
                            break;
                        case MenuWidget::BTN_COLUMN_DEST_URL:
                            $atts[$headingKey] = $model->destination_url;
                            break;
                        case MenuWidget::BTN_COLUMN_EDIT_DATE:
                            $atts[$headingKey] = $model->created_at;
                            break;
                        case MenuWidget::BTN_COLUMN_CALLOUT_1:
                            $atts[$headingKey] = $model->callout_1;
                            break;
                        case MenuWidget::BTN_COLUMN_CALLOUT_2:
                            $atts[$headingKey] = $model->callout_2;
                            break;
                        case MenuWidget::BTN_COLUMN_CALLOUT_3:
                            $atts[$headingKey] = $model->callout_3;
                            break;
                        case MenuWidget::BTN_COLUMN_CALLOUT_4:
                            $atts[$headingKey] = $model->callout_4;
                            break;
                        case MenuWidget::BTN_COLUMN_CALLOUT_5:
                            $atts[$headingKey] = $model->callout_5;
                            break;
                        case MenuWidget::BTN_COLUMN_HEADER_TYPE:
                            $atts[$headingKey] = $model->header;
                            break;
                        case MenuWidget::BTN_COLUMN_VALUE_1:
                            $atts[$headingKey] = $model->value_1;
                            break;
                        case MenuWidget::BTN_COLUMN_VALUE_2:
                            $atts[$headingKey] = $model->value_2;
                            break;
                        case MenuWidget::BTN_COLUMN_VALUE_3:
                            $atts[$headingKey] = $model->value_3;
                            break;
                        case MenuWidget::BTN_COLUMN_VALUE_4:
                            $atts[$headingKey] = $model->value_4;
                            break;
                        case MenuWidget::BTN_COLUMN_HEADLINE_PART_1:
                            $atts[$headingKey] = $model->headline_part_one;
                            break;
                        case MenuWidget::BTN_COLUMN_HEADLINE_PART_2:
                            $atts[$headingKey] = $model->headline_part_two;
                            break;
                        case MenuWidget::BTN_COLUMN_DESCRIPTION:
                            $atts[$headingKey] = $model->description;
                            break;
                        case MenuWidget::BTN_COLUMN_PATH_1:
                            $atts[$headingKey] = $model->path_1;
                            break;
                        case MenuWidget::BTN_COLUMN_PATH_2:
                            $atts[$headingKey] = $model->path_2;
                            break;
                        case MenuWidget::BTN_COLUMN_FINAL_URL:
                            $atts[$headingKey] = $model->final_urls;
                            break;
                        case MenuWidget::BTN_COLUMN_FINAL_MOBILE_URL:
                            $atts[$headingKey] = $model->final_mobile_urls;
                            break;
                        case MenuWidget::BTN_COLUMN_STATUS:
                            if (in_array($modelType, [$model::AD_EXTENSION_TYPE, $model::EXPANDED_AD_COPY_TYPE])) {
                                $status = $model::AD_COPY_STATUS_LIVE == $model->status ? $model::GOOGLE_STATUS_LIVE : $model::GOOGLE_STATUS_PAUSED;
                            } else {
                                $status = '';
                            }
                            $atts[$headingKey] = $status;
                            break;
                        case MenuWidget::BTN_COLUMN_STATUS_ADWORDS:
                            $atts[$headingKey] = $model->version->live;
                            break;
                        case MenuWidget::BTN_COLUMN_LABELS:
                            $acGroupsString = '';
                            if ($model::EXPANDED_AD_COPY_TYPE == $modelType) {
                                foreach ($model->expandedAdCopyGroups as $acGroup) {
                                    $acGroupsString .= $acGroup->name.', ';
                                }
                                $acGroupsString = rtrim($acGroupsString, ', ');
                            }
                            $atts[$headingKey] = $acGroupsString;
                            break;
                        default:
                            $atts[$headingKey] = '';
                            break;
                    }
                }
                // atts
                $modelsArr[] = $atts;
            }
            // downloading
            $excelData = Export2ExcelCsvBehavior::excelDataFormat($modelsArr);
            $excelCeils = $excelData['excel_ceils'];
            if ($modelType == $model::EXPANDED_AD_COPY_TYPE) {
                $sheetName = 'ExpandedAdCopies';
            } else {
                $sheetName = 'AdExtensions';
            }
            $excelContent = array(
                array(
                    'sheet_name' => $sheetName,
                    'sheet_title' => $modelsArrHeadings,
                    'ceils' => $excelCeils,
                )
            );
            if ($modelType == $model::EXPANDED_AD_COPY_TYPE) {
                $excelFile = 'expanded_ad_copies';
            } else {
                $excelFile = 'ad_extensions';
            }
            $this->export2excel($excelContent, $excelFile);
        }
    }
}
